<?php
/**
 * Scheduled task: check learner progress and dispatch notifications.
 *
 * Optimised for large cohorts — bulk-loads group memberships, overrides,
 * and notification state before the per-user loop to minimise DB queries.
 *
 * @package    local_asyncwatch
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_asyncwatch\task;

defined('MOODLE_INTERNAL') || die();

use local_asyncwatch\helper;
use core\task\scheduled_task;

class check_progress extends scheduled_task {

    public function get_name(): string {
        return get_string('pluginname', 'local_asyncwatch') . ': Check Progress';
    }

    public function execute(): void {
        global $DB, $CFG;
        $now = time();

        // Load site name once for the whole run.
        $site_rec   = $DB->get_record('course', ['id' => SITEID], 'fullname');
        $site_name  = $site_rec ? $site_rec->fullname : $CFG->wwwroot;

        $rules = $DB->get_records('asyncwatch_rules', ['enabled' => 1]);
        foreach ($rules as $rule) {
            $this->process_rule($rule, $now, $site_name);
        }

        $global_rules = $DB->get_records('asyncwatch_global_rules', ['enabled' => 1]);
        foreach ($global_rules as $rule) {
            $this->process_global_rule($rule, $now, $site_name);
        }
    }

    private function process_rule(\stdClass $rule, int $now, string $site_name): void {
        global $DB;

        $courseid = (int)$rule->courseid;
        $context  = \context_course::instance($courseid);
        $all_students = get_enrolled_users($context, '', 0,
            'u.id, u.firstname, u.lastname, u.email, u.lastaccess, '
            . 'u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename'
        );

        if (empty($all_students)) return;

        $course      = $DB->get_record('course', ['id' => $courseid], 'id,fullname', MUST_EXIST);
        $parts       = helper::get_parts($courseid);
        $total_parts = count($parts);

        // ── Bulk load: group memberships for all students in this course ──────
        // One query instead of one per user.
        $user_ids = array_keys($all_students);
        list($in_sql, $params) = $DB->get_in_or_equal($user_ids);
        $params[] = $courseid;
        $membership_rows = $DB->get_records_sql(
            "SELECT gm.userid, gm.groupid
               FROM {groups_members} gm
               JOIN {groups} g ON g.id = gm.groupid
              WHERE gm.userid $in_sql AND g.courseid = ?",
            $params
        );
        // Build map: userid => [groupid, ...]
        $user_groups = [];
        foreach ($membership_rows as $row) {
            $user_groups[$row->userid][] = (int)$row->groupid;
        }

        // ── Bulk load: cohort memberships (site-wide, not course-scoped) ──────
        list($in_sql_c, $params_c) = $DB->get_in_or_equal($user_ids);
        $cohort_membership_rows = $DB->get_records_sql(
            "SELECT id, userid, cohortid FROM {cohort_members} WHERE userid $in_sql_c",
            $params_c
        );
        $user_cohorts = []; // userid => [cohortid, ...]
        foreach ($cohort_membership_rows as $row) {
            $user_cohorts[$row->userid][] = (int)$row->cohortid;
        }

        // ── Bulk load: rule set membership filter ─────────────────────────────
        $set_record = $DB->get_record('asyncwatch_ruleset_rules', ['ruleid' => $rule->id], 'rulesetid');
        $students   = $all_students;

        if ($set_record) {
            $rulesetid     = (int)$set_record->rulesetid;
            $set_groupids  = helper::get_ruleset_groupids($rulesetid);
            $set_cohortids = helper::get_ruleset_cohortids($rulesetid);
            if (empty($set_groupids) && empty($set_cohortids)) return;

            // Filter students using the pre-loaded group/cohort maps — a
            // student in EITHER a targeted group OR a targeted cohort is
            // in scope.
            $students = [];
            foreach ($all_students as $user) {
                $ugroups  = $user_groups[(int)$user->id]  ?? [];
                $ucohorts = $user_cohorts[(int)$user->id] ?? [];
                if (array_intersect($ugroups, $set_groupids) || array_intersect($ucohorts, $set_cohortids)) {
                    $students[$user->id] = $user;
                }
            }
        }

        if (empty($students)) return;

        // ── Bulk load: all overrides for this rule ────────────────────────────
        // One query instead of one per user.
        $overrides_raw = $DB->get_records('asyncwatch_rule_overrides', ['ruleid' => $rule->id]);
        // Map: groupid => override record
        $overrides_by_group = [];
        foreach ($overrides_raw as $ov) {
            $overrides_by_group[(int)$ov->groupid] = $ov;
        }

        $cohort_overrides_raw = $DB->get_records('asyncwatch_rule_cohort_overrides', ['ruleid' => $rule->id]);
        $overrides_by_cohort = [];
        foreach ($cohort_overrides_raw as $ov) {
            $overrides_by_cohort[(int)$ov->cohortid] = $ov;
        }

        // ── Bulk load: existing notifications for this rule ───────────────────
        // One query instead of two per user.
        $student_ids = array_keys($students);
        list($in_sql2, $params2) = $DB->get_in_or_equal($student_ids);
        $params2[] = (int)$rule->id;
        $notif_rows = $DB->get_records_sql(
            "SELECT id, userid, type FROM {asyncwatch_notifications}
              WHERE userid $in_sql2 AND ruleid = ?",
            $params2
        );
        // Build set: "userid:type" => true
        $already_sent = [];
        foreach ($notif_rows as $row) {
            $already_sent[$row->userid . ':' . $row->type] = true;
        }

        // ── Load template once ────────────────────────────────────────────────
        $needs_tpl = $rule->notify_learner_breach  || $rule->notify_staff_breach
                  || $rule->notify_learner_warning  || $rule->notify_staff_warning;
        $tpl = $needs_tpl ? ($DB->get_record('asyncwatch_ntpl', ['courseid' => $courseid]) ?: null) : null;
        if ($needs_tpl && $tpl === null) {
            mtrace("  AsyncWatch: notifications enabled for rule {$rule->id} but no template found for course {$courseid} — skipping notifications.");
        }

        // ── Bulk load: all user progress at once (3 queries regardless of cohort size) ──
        $all_progress = helper::bulk_get_user_progress($courseid, array_keys($students));

        // ── Staff digest state ──────────────────────────────────────────────────
        // Staff notifications are now a single "report" email per rule per run
        // (with a CSV of affected students attached) rather than one email per
        // student. Dedup uses userid=0 as a sentinel in asyncwatch_notifications,
        // keyed on type 'breach_staff' / 'warning_staff'.
        $want_breach_digest  = (bool)$rule->notify_staff_breach;
        $want_warning_digest = (bool)$rule->notify_staff_warning;
        $breach_digest_sent  = $want_breach_digest
            && helper::notification_already_sent((int)$rule->id, 0, 'breach_staff');
        $warning_digest_sent = $want_warning_digest
            && helper::notification_already_sent((int)$rule->id, 0, 'warning_staff');

        $breach_rows  = [];
        $warning_rows = [];

        // ── Per-user loop — all lookups are now memory operations ─────────────
        foreach ($students as $user) {
            $userid = (int)$user->id;

            // Compute effective deadline from pre-loaded data.
            $eff = $this->effective_deadline_from_cache(
                $rule, $userid,
                $user_groups[$userid] ?? [], $overrides_by_group,
                $user_cohorts[$userid] ?? [], $overrides_by_cohort
            );

            $progress = $all_progress[$userid] ?? ['completed' => 0, 'total' => 0, 'parts' => []];
            $done     = $progress['completed'];
            $deadline = $eff['deadline'] ?? $rule->deadline;

            // Learner emails stay individual and personalised.
            $this->maybe_send_learner_breach(
                $rule, $user, $course, $done, $total_parts, $now,
                $tpl, $eff, $already_sent, $site_name
            );
            $this->maybe_send_learner_warning(
                $rule, $user, $course, $done, $total_parts, $now,
                $tpl, $eff, $already_sent, $site_name
            );

            // Collect rows for the staff digest(s), if not already sent this rule.
            if (!$breach_digest_sent && $want_breach_digest
                    && $now >= $deadline && $done < $rule->parts_required) {
                $breach_rows[] = $this->build_csv_row($rule, $user, $done, $total_parts, 'breach', $progress['parts']);
            }

            if (!$warning_digest_sent && $want_warning_digest) {
                $warn_hours = $eff['warn_hours'] ?? (int)($rule->warn_hours ?? 0);
                if ($warn_hours > 0) {
                    $warn_threshold = $deadline - ($warn_hours * MINSECS);
                    if ($now >= $warn_threshold && $now < $deadline && $done < $rule->parts_required) {
                        $warning_rows[] = $this->build_csv_row($rule, $user, $done, $total_parts, 'warning', $progress['parts']);
                    }
                }
            }
        }

        // ── Send staff digests (one email per rule per type, CSV attached) ────
        if (!$breach_digest_sent && !empty($breach_rows) && $tpl) {
            $this->send_staff_digest($tpl, 'breach', $rule, $course, $parts, $breach_rows, $site_name, $now);
        }
        if (!$warning_digest_sent && !empty($warning_rows) && $tpl) {
            $this->send_staff_digest($tpl, 'warning', $rule, $course, $parts, $warning_rows, $site_name, $now);
        }
    }

    /**
     * Build a report row for CSV export, matching the shape helper::csv_row() expects.
     */
    private function build_csv_row(
        \stdClass $rule, \stdClass $user, int $done, int $total, string $status, array $partsmap
    ): \stdClass {
        return (object)[
            'rule'       => $rule,
            'user'       => $user,
            'done'       => $done,
            'total'      => $total,
            'status'     => $status,
            'parts'      => $partsmap,
            'lastaccess' => $user->lastaccess ?? 0,
        ];
    }

    /**
     * Send a single staff "report" email for a rule, with a CSV of the
     * affected students attached, to every configured staff recipient.
     *
     * Subject/body wording is a site-wide admin setting (Site administration
     * → Plugins → Local plugins → AsyncWatch), not per-course — there is one
     * generic template each for Behind and At-risk. Recipients are still
     * configured per course (asyncwatch_ntpl.staff_recipients).
     */
    private function send_staff_digest(
        \stdClass $tpl, string $type, \stdClass $rule, \stdClass $course,
        array $parts, array $rows, string $site_name, int $now
    ): void {
        $config_prefix = $type === 'warning' ? 'staff_warning' : 'staff_breach';
        $default_subject_key = $type === 'warning' ? 'tpl_staff_warning_subject_default' : 'tpl_staff_subject_default';
        $default_body_key    = $type === 'warning' ? 'tpl_staff_warning_body_default'    : 'tpl_staff_body_default';

        $subject_tpl = get_config('local_asyncwatch', $config_prefix . '_subject');
        $body_tpl    = get_config('local_asyncwatch', $config_prefix . '_body');
        if (!$subject_tpl) $subject_tpl = get_string($default_subject_key, 'local_asyncwatch');
        if (!$body_tpl)    $body_tpl    = get_string($default_body_key,    'local_asyncwatch');

        $recipients = json_decode($tpl->staff_recipients ?? '{}', true) ?: [];
        $user_ids   = array_unique(array_map('intval', $recipients['userids'] ?? []));
        if (empty($user_ids)) return;

        $vars = [
            '{{coursename}}'     => $course->fullname,
            '{{rulename}}'       => $rule->name,
            '{{deadline}}'       => userdate($rule->deadline),
            '{{sitename}}'       => $site_name,
            '{{affected_count}}' => count($rows),
        ];
        $subject = self::render_template($subject_tpl, $vars);
        $body    = self::render_template($body_tpl, $vars);
        if (!$subject || !$body) return;

        $csv_path   = helper::write_csv_tempfile($rows, $parts, 'asyncwatch_' . $type . '_rule' . $rule->id);
        $attachname = 'asyncwatch_' . $type . '_report_rule' . $rule->id . '_' . date('Ymd', $now) . '.csv';

        $sent = false;
        foreach ($user_ids as $uid) {
            $staff_user = \core_user::get_user($uid);
            if ($staff_user && $staff_user->suspended == 0) {
                if (helper::send_email_with_attachment($staff_user, $subject, $body, $csv_path, $attachname)) {
                    $sent = true;
                }
            }
        }
        @unlink($csv_path);

        if ($sent) {
            helper::record_notification((int)$rule->id, 0, $type . '_staff');
            mtrace("  AsyncWatch: {$type} staff digest → " . count($user_ids)
                . " recipient(s), " . count($rows) . " affected student(s), rule {$rule->id}");
        }
    }

    // =========================================================================
    // CROSS-COURSE (GLOBAL) RULES
    //
    // Email wording is a site-wide admin setting (not per-course, since a
    // global rule has no single course), and staff recipients are one
    // site-wide list shared by every global rule (see globalrules.php's
    // "Staff recipients" screen). Dedup uses asyncwatch_global_notifications
    // — a separate table from the per-course one, to avoid ruleid collisions
    // between the two rule tables.
    // =========================================================================

    private function process_global_rule(\stdClass $rule, int $now, string $site_name): void {
        global $DB;

        $ruleid    = (int)$rule->id;
        $courseids = helper::get_global_rule_courseids($ruleid);
        if (empty($courseids)) return;

        $users = helper::get_global_rule_users($ruleid);
        if (empty($users)) return;

        $userids  = array_keys($users);
        $progress = helper::bulk_get_global_rule_progress($ruleid, $userids);

        // ── Bulk load: cohort overrides + membership for this rule ────────────
        $overrides = helper::get_global_rule_overrides($ruleid);
        $overrides_by_cohort = [];
        foreach ($overrides as $ov) {
            $overrides_by_cohort[(int)$ov->cohortid] = $ov;
        }
        $user_cohortids = [];
        if (!empty($overrides_by_cohort)) {
            list($in_sql1, $params1) = $DB->get_in_or_equal(array_keys($overrides_by_cohort));
            list($in_sql2, $params2) = $DB->get_in_or_equal($userids);
            $rows = $DB->get_records_sql(
                "SELECT id, userid, cohortid FROM {cohort_members} WHERE cohortid $in_sql1 AND userid $in_sql2",
                array_merge($params1, $params2)
            );
            foreach ($rows as $r) {
                $user_cohortids[(int)$r->userid][] = (int)$r->cohortid;
            }
        }

        // ── Bulk load: existing notifications for this rule ────────────────────
        list($in_sql3, $params3) = $DB->get_in_or_equal($userids);
        $params3[] = $ruleid;
        $notif_rows = $DB->get_records_sql(
            "SELECT id, userid, type FROM {asyncwatch_global_notifications}
              WHERE userid $in_sql3 AND ruleid = ?",
            $params3
        );
        $already_sent = [];
        foreach ($notif_rows as $row) {
            $already_sent[$row->userid . ':' . $row->type] = true;
        }

        $coursenames = [];
        foreach ($courseids as $cid) {
            $coursenames[] = format_string(get_course($cid)->fullname);
        }
        $courses_str = implode(', ', $coursenames);

        $want_breach_digest  = (bool)$rule->notify_staff_breach;
        $want_warning_digest = (bool)$rule->notify_staff_warning;
        $breach_digest_sent  = $want_breach_digest
            && helper::global_notification_already_sent($ruleid, 0, 'breach_staff');
        $warning_digest_sent = $want_warning_digest
            && helper::global_notification_already_sent($ruleid, 0, 'warning_staff');

        $breach_rows  = [];
        $warning_rows = [];

        foreach ($users as $uid => $user) {
            $prog  = $progress[$uid] ?? ['completed' => 0, 'total' => 0];
            $done  = $prog['completed'];
            $total = $prog['total'];

            // Effective deadline from the best cohort override this user is in.
            $best = null;
            foreach ($user_cohortids[$uid] ?? [] as $cid) {
                if (!isset($overrides_by_cohort[$cid])) continue;
                $ov = $overrides_by_cohort[$cid];
                if ($best === null || (int)$ov->deadline > (int)$best->deadline) {
                    $best = $ov;
                }
            }
            $deadline   = $best ? (int)$best->deadline   : (int)$rule->deadline;
            $warn_hours = $best ? (int)$best->warn_hours : (int)$rule->warn_hours;

            $this->maybe_send_global_learner_breach(
                $rule, $user, $done, $total, $now, $deadline, $already_sent, $site_name, $courses_str
            );
            $this->maybe_send_global_learner_warning(
                $rule, $user, $done, $total, $now, $deadline, $warn_hours, $already_sent, $site_name, $courses_str
            );

            if (!$breach_digest_sent && $want_breach_digest
                    && $now >= $deadline && $done < $rule->parts_required) {
                $breach_rows[] = $this->build_global_csv_row($rule, $user, $done, $total, 'breach', $coursenames);
            }
            if (!$warning_digest_sent && $want_warning_digest && $warn_hours > 0) {
                $warn_threshold = $deadline - ($warn_hours * MINSECS);
                if ($now >= $warn_threshold && $now < $deadline && $done < $rule->parts_required) {
                    $warning_rows[] = $this->build_global_csv_row($rule, $user, $done, $total, 'warning', $coursenames);
                }
            }
        }

        if (!$breach_digest_sent && !empty($breach_rows)) {
            $this->send_global_staff_digest('breach', $rule, $breach_rows, $courses_str, $site_name, $now);
        }
        if (!$warning_digest_sent && !empty($warning_rows)) {
            $this->send_global_staff_digest('warning', $rule, $warning_rows, $courses_str, $site_name, $now);
        }
    }

    private function build_global_csv_row(
        \stdClass $rule, \stdClass $user, int $done, int $total, string $status, array $coursenames
    ): \stdClass {
        return (object)[
            'rule'        => $rule,
            'user'        => $user,
            'done'        => $done,
            'total'       => $total,
            'status'      => $status,
            'lastaccess'  => $user->lastaccess ?? 0,
            'coursenames' => $coursenames,
        ];
    }

    private function build_global_vars(
        \stdClass $user, \stdClass $rule, int $done, int $total,
        int $deadline, string $site_name, string $courses_str
    ): array {
        return [
            '{{firstname}}'      => $user->firstname,
            '{{lastname}}'       => $user->lastname,
            '{{fullname}}'       => fullname($user),
            '{{email}}'          => $user->email,
            '{{courses}}'        => $courses_str,
            '{{parts_done}}'     => $done,
            '{{parts_required}}' => $rule->parts_required,
            '{{deadline}}'       => userdate($deadline),
            '{{rulename}}'       => $rule->name,
            '{{sitename}}'       => $site_name,
        ];
    }

    private function maybe_send_global_learner_breach(
        \stdClass $rule, \stdClass $user, int $done, int $total, int $now, int $deadline,
        array &$already_sent, string $site_name, string $courses_str
    ): void {
        if (!$rule->notify_learner_breach) return;
        if ($now < $deadline) return;
        if ($done >= $rule->parts_required) return;

        $key = $user->id . ':breach';
        if (!empty($already_sent[$key])) return;

        $subject_tpl = get_config('local_asyncwatch', 'global_learner_breach_subject');
        $body_tpl    = get_config('local_asyncwatch', 'global_learner_breach_body');
        if (!$subject_tpl) $subject_tpl = get_string('tpl_global_learner_subject_default', 'local_asyncwatch');
        if (!$body_tpl)    $body_tpl    = get_string('tpl_global_learner_body_default',    'local_asyncwatch');

        $vars    = $this->build_global_vars($user, $rule, $done, $total, $deadline, $site_name, $courses_str);
        $subject = self::render_template($subject_tpl, $vars);
        $body    = self::render_template($body_tpl, $vars);
        if (!$subject || !$body) return;

        if (helper::send_email($user, $subject, $body)) {
            helper::record_global_notification((int)$rule->id, (int)$user->id, 'breach');
            $already_sent[$key] = true;
            mtrace("  AsyncWatch: breach email → learner {$user->id} global rule {$rule->id}");
        }
    }

    private function maybe_send_global_learner_warning(
        \stdClass $rule, \stdClass $user, int $done, int $total, int $now, int $deadline, int $warn_hours,
        array &$already_sent, string $site_name, string $courses_str
    ): void {
        if (!$rule->notify_learner_warning) return;
        if ($warn_hours <= 0) return;

        $warn_threshold = $deadline - ($warn_hours * MINSECS);
        if ($now < $warn_threshold || $now >= $deadline) return;
        if ($done >= $rule->parts_required) return;

        $key = $user->id . ':warning';
        if (!empty($already_sent[$key])) return;

        $subject_tpl = get_config('local_asyncwatch', 'global_learner_warning_subject');
        $body_tpl    = get_config('local_asyncwatch', 'global_learner_warning_body');
        if (!$subject_tpl) $subject_tpl = get_string('tpl_global_learner_warning_subject_default', 'local_asyncwatch');
        if (!$body_tpl)    $body_tpl    = get_string('tpl_global_learner_warning_body_default',    'local_asyncwatch');

        $vars    = $this->build_global_vars($user, $rule, $done, $total, $deadline, $site_name, $courses_str);
        $subject = self::render_template($subject_tpl, $vars);
        $body    = self::render_template($body_tpl, $vars);
        if (!$subject || !$body) return;

        if (helper::send_email($user, $subject, $body)) {
            helper::record_global_notification((int)$rule->id, (int)$user->id, 'warning');
            $already_sent[$key] = true;
            mtrace("  AsyncWatch: warning email → learner {$user->id} global rule {$rule->id}");
        }
    }

    private function send_global_staff_digest(
        string $type, \stdClass $rule, array $rows, string $courses_str, string $site_name, int $now
    ): void {
        $recipient_ids = helper::get_global_staff_recipient_ids();
        if (empty($recipient_ids)) return;

        $config_prefix        = $type === 'warning' ? 'global_staff_warning' : 'global_staff_breach';
        $default_subject_key  = $type === 'warning' ? 'tpl_global_staff_warning_subject_default' : 'tpl_global_staff_subject_default';
        $default_body_key     = $type === 'warning' ? 'tpl_global_staff_warning_body_default'     : 'tpl_global_staff_body_default';

        $subject_tpl = get_config('local_asyncwatch', $config_prefix . '_subject');
        $body_tpl    = get_config('local_asyncwatch', $config_prefix . '_body');
        if (!$subject_tpl) $subject_tpl = get_string($default_subject_key, 'local_asyncwatch');
        if (!$body_tpl)    $body_tpl    = get_string($default_body_key,    'local_asyncwatch');

        $vars = [
            '{{courses}}'         => $courses_str,
            '{{rulename}}'        => $rule->name,
            '{{deadline}}'        => userdate($rule->deadline),
            '{{sitename}}'        => $site_name,
            '{{affected_count}}'  => count($rows),
        ];
        $subject = self::render_template($subject_tpl, $vars);
        $body    = self::render_template($body_tpl, $vars);
        if (!$subject || !$body) return;

        $csv_path   = helper::write_global_csv_tempfile($rows, 'asyncwatch_global_' . $type . '_rule' . $rule->id);
        $attachname = 'asyncwatch_global_' . $type . '_report_rule' . $rule->id . '_' . date('Ymd', $now) . '.csv';

        $sent = false;
        foreach ($recipient_ids as $uid) {
            $staff_user = \core_user::get_user($uid);
            if ($staff_user && $staff_user->suspended == 0) {
                if (helper::send_email_with_attachment($staff_user, $subject, $body, $csv_path, $attachname)) {
                    $sent = true;
                }
            }
        }
        @unlink($csv_path);

        if ($sent) {
            helper::record_global_notification((int)$rule->id, 0, $type . '_staff');
            mtrace("  AsyncWatch: {$type} staff digest → " . count($recipient_ids)
                . " recipient(s), " . count($rows) . " affected student(s), global rule {$rule->id}");
        }
    }

    /**
     * Compute effective deadline/warn using pre-loaded group and override data.
     * No DB queries — pure memory lookups.
     */
    private function effective_deadline_from_cache(
        \stdClass $rule,
        int $userid,
        array $groupids,
        array $overrides_by_group,
        array $cohortids = [],
        array $overrides_by_cohort = []
    ): array {
        $best = null;
        foreach ($groupids as $gid) {
            if (!isset($overrides_by_group[$gid])) continue;
            $ov = $overrides_by_group[$gid];
            if ($best === null || (int)$ov->deadline > (int)$best->deadline) {
                $best = $ov;
            }
        }
        foreach ($cohortids as $cid) {
            if (!isset($overrides_by_cohort[$cid])) continue;
            $ov = $overrides_by_cohort[$cid];
            if ($best === null || (int)$ov->deadline > (int)$best->deadline) {
                $best = $ov;
            }
        }

        if ($best) {
            return [
                'deadline'   => (int)$best->deadline,
                'warn_hours' => (int)$best->warn_hours,
                'override'   => $best,
            ];
        }
        return [
            'deadline'   => (int)$rule->deadline,
            'warn_hours' => (int)$rule->warn_hours,
            'override'   => null,

        ];
    }

    /**
     * Send the learner's personal "behind" email, if enabled and not already sent.
     * Staff are handled separately via send_staff_digest() — see process_rule().
     */
    private function maybe_send_learner_breach(
        \stdClass $rule, \stdClass $user, \stdClass $course,
        int $done, int $total, int $now, ?\stdClass $tpl,
        array $eff, array &$already_sent, string $site_name
    ): void {
        if (!$rule->notify_learner_breach || !$tpl || empty($tpl->learner_subject)) return;

        $deadline = $eff['deadline'] ?? $rule->deadline;
        if ($now < $deadline) return;
        if ($done >= $rule->parts_required) return;

        $key = $user->id . ':breach';
        if (!empty($already_sent[$key])) return;

        $vars    = $this->build_vars($user, $course, $rule, $done, $total, $deadline, $site_name);
        $subject = self::render_template($tpl->learner_subject, $vars);
        $body    = self::render_template($tpl->learner_body ?? '', $vars);
        if (!$subject || !$body) return;

        if (helper::send_email($user, $subject, $body)) {
            helper::record_notification((int)$rule->id, (int)$user->id, 'breach');
            $already_sent[$key] = true;
            mtrace("  AsyncWatch: breach email → learner {$user->id} rule {$rule->id}");
        }
    }

    /**
     * Send the learner's personal "at risk" email, if enabled and not already sent.
     * Staff are handled separately via send_staff_digest() — see process_rule().
     */
    private function maybe_send_learner_warning(
        \stdClass $rule, \stdClass $user, \stdClass $course,
        int $done, int $total, int $now, ?\stdClass $tpl,
        array $eff, array &$already_sent, string $site_name
    ): void {
        if (!$rule->notify_learner_warning || !$tpl || empty($tpl->learner_warning_subject)) return;

        $deadline   = $eff['deadline']   ?? $rule->deadline;
        $warn_hours = $eff['warn_hours'] ?? (int)($rule->warn_hours ?? 0);
        if ($warn_hours <= 0) return;

        $warn_threshold = $deadline - ($warn_hours * MINSECS);
        if ($now < $warn_threshold || $now >= $deadline) return;
        if ($done >= $rule->parts_required) return;

        $key = $user->id . ':warning';
        if (!empty($already_sent[$key])) return;

        $vars    = $this->build_vars($user, $course, $rule, $done, $total, $deadline, $site_name);
        $subject = self::render_template($tpl->learner_warning_subject, $vars);
        $body    = self::render_template($tpl->learner_warning_body ?? '', $vars);
        if (!$subject || !$body) return;

        if (helper::send_email($user, $subject, $body)) {
            helper::record_notification((int)$rule->id, (int)$user->id, 'warning');
            $already_sent[$key] = true;
            mtrace("  AsyncWatch: warning email → learner {$user->id} rule {$rule->id}");
        }
    }

    private function build_vars(
        \stdClass $user, \stdClass $course, \stdClass $rule,
        int $done, int $total, int $effective_deadline,
        string $site_name
    ): array {
        $deadline = $effective_deadline > 0 ? $effective_deadline : $rule->deadline;
        return [
            '{{firstname}}'      => $user->firstname,
            '{{lastname}}'       => $user->lastname,
            '{{fullname}}'       => fullname($user),
            '{{email}}'          => $user->email,
            '{{coursename}}'     => $course->fullname,
            '{{parts_done}}'     => $done,
            '{{parts_required}}' => $rule->parts_required,
            '{{deadline}}'       => userdate($deadline),
            '{{rulename}}'       => $rule->name,
            '{{sitename}}'       => $site_name,
        ];
    }

    public static function render_template(string $template, array $vars): string {
        return str_replace(array_keys($vars), array_values($vars), $template);
    }
}