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
    }

    private function process_rule(\stdClass $rule, int $now, string $site_name): void {
        global $DB;

        $courseid = (int)$rule->courseid;
        $context  = \context_course::instance($courseid);
        $all_students = get_enrolled_users($context, '');

        if (empty($all_students)) return;

        $course      = $DB->get_record('course', ['id' => $courseid], 'id,fullname', MUST_EXIST);
        $total_parts = count(helper::get_parts($courseid));

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

        // ── Bulk load: rule set membership filter ─────────────────────────────
        $set_record = $DB->get_record('asyncwatch_ruleset_rules', ['ruleid' => $rule->id], 'rulesetid');
        $students   = $all_students;

        if ($set_record) {
            $rulesetid = (int)$set_record->rulesetid;
            $set_groupids = helper::get_ruleset_groupids($rulesetid);
            if (empty($set_groupids)) return;

            // Filter students using the pre-loaded group map.
            $students = [];
            foreach ($all_students as $user) {
                $ugroups = $user_groups[(int)$user->id] ?? [];
                if (array_intersect($ugroups, $set_groupids)) {
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

        // ── Bulk load: all user progress at once (3 queries regardless of cohort size) ──
        $all_progress = helper::bulk_get_user_progress($courseid, array_keys($students));

        // ── Per-user loop — all lookups are now memory operations ─────────────
        foreach ($students as $user) {
            $userid = (int)$user->id;

            // Compute effective deadline from pre-loaded data.
            $eff = $this->effective_deadline_from_cache(
                $rule, $userid, $user_groups[$userid] ?? [], $overrides_by_group
            );

            $progress = $all_progress[$userid] ?? ['completed' => 0, 'total' => 0];
            $done     = $progress['completed'];

            $this->maybe_send_breach(
                $rule, $user, $course, $done, $total_parts, $now,
                $tpl, $eff, $already_sent, $site_name
            );
            $this->maybe_send_warning(
                $rule, $user, $course, $done, $total_parts, $now,
                $tpl, $eff, $already_sent, $site_name
            );
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
        array $overrides_by_group
    ): array {
        $best = null;
        foreach ($groupids as $gid) {
            if (!isset($overrides_by_group[$gid])) continue;
            $ov = $overrides_by_group[$gid];
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

    private function maybe_send_breach(
        \stdClass $rule, \stdClass $user, \stdClass $course,
        int $done, int $total, int $now, ?\stdClass $tpl,
        array $eff, array &$already_sent, string $site_name
    ): void {
        $deadline = $eff['deadline'] ?? $rule->deadline;
        if ($now < $deadline) return;
        if ($done >= $rule->parts_required) return;

        $key = $user->id . ':breach';
        if (!empty($already_sent[$key])) return;

        $sent = false;
        $vars = $this->build_vars($user, $course, $rule, $done, $total, $deadline, $site_name);

        if ($rule->notify_learner_breach && $tpl && !empty($tpl->learner_subject)) {
            $subject = self::render_template($tpl->learner_subject, $vars);
            $body    = self::render_template($tpl->learner_body ?? '', $vars);
            if ($subject && $body && helper::send_email($user, $subject, $body)) {
                $sent = true;
                mtrace("  AsyncWatch: breach email → learner {$user->id} rule {$rule->id}");
            }
        }

        if ($rule->notify_staff_breach && $tpl && !empty($tpl->staff_subject)) {
            if ($this->send_staff_email($tpl, 'breach', $rule, $course, $vars)) {
                $sent = true;
            }
        }

        if ($sent) {
            helper::record_notification((int)$rule->id, (int)$user->id, 'breach');
            $already_sent[$key] = true; // Update in-memory cache.
        }
    }

    private function maybe_send_warning(
        \stdClass $rule, \stdClass $user, \stdClass $course,
        int $done, int $total, int $now, ?\stdClass $tpl,
        array $eff, array &$already_sent, string $site_name
    ): void {
        $deadline   = $eff['deadline']   ?? $rule->deadline;
        $warn_hours = $eff['warn_hours'] ?? (int)($rule->warn_hours ?? 0);
        if ($warn_hours <= 0) return;

        $warn_threshold = $deadline - ($warn_hours * MINSECS);
        if ($now < $warn_threshold || $now >= $deadline) return;
        if ($done >= $rule->parts_required) return;

        $key = $user->id . ':warning';
        if (!empty($already_sent[$key])) return;

        $sent = false;
        $vars = $this->build_vars($user, $course, $rule, $done, $total, $deadline, $site_name);

        if ($rule->notify_learner_warning && $tpl && !empty($tpl->learner_warning_subject)) {
            $subject = self::render_template($tpl->learner_warning_subject, $vars);
            $body    = self::render_template($tpl->learner_warning_body ?? '', $vars);
            if ($subject && $body && helper::send_email($user, $subject, $body)) {
                $sent = true;
                mtrace("  AsyncWatch: warning email → learner {$user->id} rule {$rule->id}");
            }
        }

        if ($rule->notify_staff_warning && $tpl && !empty($tpl->staff_warning_subject)) {
            if ($this->send_staff_email($tpl, 'warning', $rule, $course, $vars)) {
                $sent = true;
            }
        }

        if ($sent) {
            helper::record_notification((int)$rule->id, (int)$user->id, 'warning');
            $already_sent[$key] = true;
        }
    }

    private function send_staff_email(
        \stdClass $tpl, string $type, \stdClass $rule,
        \stdClass $course, array $vars
    ): bool {
        $subject_field = $type === 'warning' ? 'staff_warning_subject' : 'staff_subject';
        $body_field    = $type === 'warning' ? 'staff_warning_body'    : 'staff_body';

        $subject = self::render_template($tpl->$subject_field ?? '', $vars);
        $body    = self::render_template($tpl->$body_field    ?? '', $vars);
        if (!$subject || !$body) return false;

        $recipients = json_decode($tpl->staff_recipients ?? '{}', true) ?: [];
        $user_ids   = array_unique(array_map('intval', $recipients['userids'] ?? []));

        $sent = false;
        foreach ($user_ids as $uid) {
            $staff_user = \core_user::get_user($uid);
            if ($staff_user && $staff_user->suspended == 0) {
                if (helper::send_email($staff_user, $subject, $body)) {
                    $sent = true;
                }
                mtrace("  AsyncWatch: {$type} staff email → user {$uid} rule {$rule->id}");
            }
        }
        return $sent;
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
