<?php
/**
 * Core helper: data access and progress calculation.
 *
 * @package    local_asyncwatch
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_asyncwatch;

defined('MOODLE_INTERNAL') || die();

/**
 * Central helper class for AsyncWatch operations.
 */
class helper {

    // -------------------------------------------------------------------------
    // PARTS
    // -------------------------------------------------------------------------

    /**
     * Return all parts for a course, ordered by sortorder.
     *
     * @param int $courseid
     * @return array of stdClass records
     */
    /**
     * The standard user field list used everywhere this plugin loads
     * enrolled users — includes phonetic/alternate name fields so
     * fullname() doesn't throw a debugging() notice on every call when
     * debug mode is on (see project conventions).
     */
    private const LEARNER_FIELDS =
        'u.id, u.firstname, u.lastname, u.email, u.lastaccess, u.lang, u.timezone, '
        . 'u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename';

    /**
     * Learners actually being tracked for deadline compliance in a course:
     * enrolled, active (not suspended), and excluding anyone who holds a
     * role that can manage AsyncWatch itself in this context (teachers,
     * managers — by default whoever has local/asyncwatch:manage here).
     * Using that capability rather than a hardcoded role shortname list
     * means this stays in sync automatically if the capability's default
     * role set is ever changed.
     *
     * @return array [userid => stdClass{id, firstname, lastname, email, lastaccess, ...}]
     */
    public static function get_active_learners(\context $context): array {
        $enrolled = get_enrolled_users($context, '', 0, self::LEARNER_FIELDS, 'u.lastname ASC, u.firstname ASC', 0, 0, true);
        $staff    = get_users_by_capability($context, 'local/asyncwatch:manage', 'u.id');
        return array_diff_key($enrolled, $staff);
    }

    public static function get_parts(int $courseid): array {
        global $DB;
        return $DB->get_records('asyncwatch_parts', ['courseid' => $courseid], 'sortorder ASC');
    }

    /**
     * Return a single part record.
     */
    public static function get_part(int $partid): \stdClass {
        global $DB;
        return $DB->get_record('asyncwatch_parts', ['id' => $partid], '*', MUST_EXIST);
    }

    /**
     * Save (insert or update) a part.
     *
     * @param stdClass $data  Must have courseid, name, sortorder. Optional id for update.
     * @return int  The part id.
     */
    public static function save_part(\stdClass $data): int {
        global $DB;
        $now = time();
        if (!empty($data->id)) {
            $data->timemodified = $now;
            $DB->update_record('asyncwatch_parts', $data);
            return (int)$data->id;
        }
        $data->timecreated  = $now;
        $data->timemodified = $now;
        return (int)$DB->insert_record('asyncwatch_parts', $data);
    }

    /**
     * Delete a part and all its activity assignments.
     */
    public static function delete_part(int $partid): void {
        global $DB;
        $DB->delete_records('asyncwatch_part_activities', ['partid' => $partid]);
        $DB->delete_records('asyncwatch_parts', ['id' => $partid]);
    }

    // -------------------------------------------------------------------------
    // ACTIVITIES WITHIN PARTS
    // -------------------------------------------------------------------------

    /**
     * Return all cm ids assigned to a part.
     *
     * @param int $partid
     * @return int[]
     */
    public static function get_part_cmids(int $partid): array {
        global $DB;
        $rows = $DB->get_records('asyncwatch_part_activities', ['partid' => $partid], '', 'cmid');
        return array_map('intval', array_keys($rows));
    }

    /**
     * Replace all activity assignments for a part.
     *
     * @param int   $partid
     * @param int[] $cmids
     */
    public static function set_part_activities(int $partid, array $cmids): void {
        global $DB;
        $DB->delete_records('asyncwatch_part_activities', ['partid' => $partid]);
        foreach (array_unique($cmids) as $cmid) {
            $row = (object)['partid' => $partid, 'cmid' => (int)$cmid];
            $DB->insert_record('asyncwatch_part_activities', $row);
        }
    }

    // -------------------------------------------------------------------------
    // RULES
    // -------------------------------------------------------------------------

    /**
     * Return all rules for a course.
     */
    public static function get_rules(int $courseid): array {
        global $DB;
        return $DB->get_records('asyncwatch_rules', ['courseid' => $courseid], 'deadline ASC');
    }

    /**
     * Save (insert or update) a rule.
     */
    public static function save_rule(\stdClass $data): int {
        global $DB;
        $now = time();
        if (!empty($data->id)) {
            $data->timemodified = $now;
            $DB->update_record('asyncwatch_rules', $data);
            return (int)$data->id;
        }
        $data->timecreated  = $now;
        $data->timemodified = $now;
        return (int)$DB->insert_record('asyncwatch_rules', $data);
    }

    /**
     * Delete a rule and its notification log.
     */
    public static function delete_rule(int $ruleid): void {
        global $DB;
        $DB->delete_records('asyncwatch_notifications',         ['ruleid' => $ruleid]);
        $DB->delete_records('asyncwatch_rule_restrict_groups',  ['ruleid' => $ruleid]);
        $DB->delete_records('asyncwatch_rule_restrict_cohorts', ['ruleid' => $ruleid]);
        $DB->delete_records('asyncwatch_rule_overrides',        ['ruleid' => $ruleid]);
        $DB->delete_records('asyncwatch_rule_cohort_overrides', ['ruleid' => $ruleid]);
        $DB->delete_records('asyncwatch_rules',                 ['id'     => $ruleid]);
    }

    // -------------------------------------------------------------------------
    // PROGRESS CALCULATION
    // -------------------------------------------------------------------------

    /**
     * Calculate how many parts a user has fully completed.
     *
     * A part is "complete" when ALL activities assigned to it have their
     * completion state = COMPLETION_COMPLETE (1) or COMPLETION_COMPLETE_PASS (2).
     *
     * @param int $courseid
     * @param int $userid
     * @return array  ['completed' => int, 'total' => int, 'parts' => [partid => bool]]
     */
    public static function get_user_progress(int $courseid, int $userid): array {
        global $DB;

        $parts = self::get_parts($courseid);
        $result = ['completed' => 0, 'total' => count($parts), 'parts' => []];

        if (empty($parts)) {
            return $result;
        }

        // Load all completion records for this user/course in one query.
        $sql = "SELECT cmc.coursemoduleid, cmc.completionstate
                  FROM {course_modules_completion} cmc
                  JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                 WHERE cm.course = :courseid
                   AND cmc.userid = :userid";
        $completions = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);
        $done_cmids  = [];
        foreach ($completions as $c) {
            if ($c->completionstate >= 1) { // 1 = COMPLETION_COMPLETE
                $done_cmids[(int)$c->coursemoduleid] = true;
            }
        }

        foreach ($parts as $part) {
            $cmids = self::get_part_cmids((int)$part->id);
            if (empty($cmids)) {
                // Empty part – skip (treat as incomplete so it doesn't inflate count).
                $result['parts'][$part->id] = false;
                continue;
            }
            $all_done = true;
            foreach ($cmids as $cmid) {
                if (empty($done_cmids[$cmid])) {
                    $all_done = false;
                    break;
                }
            }
            $result['parts'][$part->id] = $all_done;
            if ($all_done) {
                $result['completed']++;
            }
        }

        return $result;
    }

    /**
     * Bulk version of get_user_progress — loads progress for ALL users at once.
     * Returns [userid => ['completed'=>int, 'total'=>int, 'parts'=>[partid=>bool]]]
     *
     * Uses 3 queries total regardless of number of users.
     */
    public static function bulk_get_user_progress(int $courseid, array $userids): array {
        global $DB;

        $parts = self::get_parts($courseid);
        $total = count($parts);

        // Initialise result for all users.
        $results = [];
        foreach ($userids as $uid) {
            $results[$uid] = ['completed' => 0, 'total' => $total, 'parts' => []];
            foreach ($parts as $part) {
                $results[$uid]['parts'][$part->id] = false;
            }
        }

        if (empty($parts) || empty($userids)) return $results;

        // Load all part→cmid mappings in one query.
        $part_ids = array_keys($parts);
        list($in_sql, $params) = $DB->get_in_or_equal($part_ids);
        $part_cmids = []; // [partid => [cmid, ...]]
        $rows = $DB->get_records_sql(
            "SELECT pa.id, pa.partid, pa.cmid FROM {asyncwatch_part_activities} pa WHERE pa.partid $in_sql",
            $params
        );
        foreach ($rows as $row) {
            $part_cmids[(int)$row->partid][] = (int)$row->cmid;
        }

        // Load all completion records for all users in one query.
        list($in_sql2, $params2) = $DB->get_in_or_equal($userids);
        $params2[] = $courseid;
        $comp_rows = $DB->get_records_sql(
            "SELECT cmc.id, cmc.userid, cmc.coursemoduleid, cmc.completionstate
               FROM {course_modules_completion} cmc
               JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
              WHERE cmc.userid $in_sql2 AND cm.course = ?",
            $params2
        );

        // Build completion map: [userid][cmid] => true
        $done = [];
        foreach ($comp_rows as $row) {
            if ((int)$row->completionstate >= 1) {
                $done[(int)$row->userid][(int)$row->coursemoduleid] = true;
            }
        }

        // Compute per-user per-part progress from memory.
        foreach ($userids as $uid) {
            $user_done = $done[$uid] ?? [];
            foreach ($parts as $part) {
                $cmids = $part_cmids[$part->id] ?? [];
                if (empty($cmids)) continue;
                $all_done = true;
                foreach ($cmids as $cmid) {
                    if (empty($user_done[$cmid])) { $all_done = false; break; }
                }
                $results[$uid]['parts'][$part->id] = $all_done;
                if ($all_done) $results[$uid]['completed']++;
            }
        }

        return $results;
    }

    // -------------------------------------------------------------------------
    // ACTIVITY SELECTOR DATA
    // -------------------------------------------------------------------------

    /**
     * Return all course modules for a course, grouped by section, with
     * tracking-on/off flag and module type.
     *
     * @param int $courseid
     * @return array  Keyed by section number, each value an array of cm objects.
     */
    public static function get_course_activities_by_section(int $courseid): array {
        global $DB;
        $modinfo  = get_fast_modinfo($courseid);
        $all_secs = $modinfo->get_section_info_all();

        // Build instance → child section map for subsections.
        $inst_to_sec = [];
        $subsec_ids  = [];
        foreach ($all_secs as $sec) {
            if (($sec->component ?? '') === 'mod_subsection' && !empty($sec->itemid)) {
                $inst_to_sec[(int)$sec->itemid] = $sec;
                $subsec_ids[$sec->id] = true;
            }
        }

        // Get subsection cm instances keyed by cm id.
        $subsec_cms = [];
        if (!empty($subsec_ids)) {
            $rows = $DB->get_records_sql(
                "SELECT cm.id, cm.instance, cm.section as parent_sec_id, sub.name as subname
                   FROM {course_modules} cm
                   JOIN {modules} m ON m.id = cm.module AND m.name = 'subsection'
                   JOIN {subsection} sub ON sub.id = cm.instance
                  WHERE cm.course = :courseid AND cm.deletioninprogress = 0",
                ['courseid' => $courseid]
            );
            foreach ($rows as $row) {
                $subsec_cms[$row->id] = $row;
            }
        }

        // Build sections_by_id for parent lookup.
        $sections_by_id = [];
        foreach ($all_secs as $sec) {
            $sections_by_id[$sec->id] = $sec;
        }

        // Build structured result:
        // [section_id => ['name'=>, 'section_num'=>, 'is_subsection'=>false,
        //                 'parent_id'=>null, 'activities'=>[],
        //                 'subsections'=> [sub_sec_id => ['name'=>,'activities'=>[]]]]]
        $sections = [];

        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->modname === 'subsection') continue; // container, not an activity
            if ($cm->modname === 'label')      continue;
            if (!$cm->uservisible && !$cm->is_visible_on_course_page()) continue;

            $sec_info = $sections_by_id[$cm->section] ?? null;
            if (!$sec_info || $sec_info->section == 0) continue;

            $activity = (object)[
                'cmid'             => (int)$cm->id,
                'name'             => $cm->name,
                'modname'          => $cm->modname,
                'tracking_enabled' => ($cm->completion != 0),
                'icon_url'         => $cm->get_icon_url()->out(false),
            ];

            if (isset($subsec_ids[$sec_info->id])) {
                // Activity is inside a subsection — find parent section.
                // Match via inst_to_sec: find the subsection cm whose child section = sec_info.
                $parent_sec_id = null;
                $subsec_name   = $sec_info->name ?: get_section_name($courseid, $sec_info->section);
                foreach ($subsec_cms as $scm) {
                    $child = $inst_to_sec[(int)$scm->instance] ?? null;
                    if ($child && $child->id === $sec_info->id) {
                        $parent_sec = $sections_by_id[$scm->parent_sec_id] ?? null;
                        if ($parent_sec) {
                            $parent_sec_id = $parent_sec->id;
                            $subsec_name   = $scm->subname ?: $subsec_name;
                        }
                        break;
                    }
                }

                if ($parent_sec_id) {
                    if (!isset($sections[$parent_sec_id])) {
                        $psec = $sections_by_id[$parent_sec_id];
                        $sections[$parent_sec_id] = [
                            'name'        => get_section_name($courseid, $psec->section),
                            'section_num' => $psec->section,
                            'activities'  => [],
                            'subsections' => [],
                        ];
                    }
                    if (!isset($sections[$parent_sec_id]['subsections'][$sec_info->id])) {
                        $sections[$parent_sec_id]['subsections'][$sec_info->id] = [
                            'name'       => $subsec_name,
                            'activities' => [],
                        ];
                    }
                    $sections[$parent_sec_id]['subsections'][$sec_info->id]['activities'][] = $activity;
                    continue;
                }
            }

            // Regular top-level section activity.
            if (!isset($sections[$sec_info->id])) {
                $sections[$sec_info->id] = [
                    'name'        => get_section_name($courseid, $sec_info->section),
                    'section_num' => $sec_info->section,
                    'activities'  => [],
                    'subsections' => [],
                ];
            }
            $sections[$sec_info->id]['activities'][] = $activity;
        }

        // Sort by section number.
        uasort($sections, fn($a, $b) => $a['section_num'] <=> $b['section_num']);

        return $sections;
    }

    // -------------------------------------------------------------------------
    // AUTO PARTS
    // -------------------------------------------------------------------------

    /**
     * Detect whether a course has any formal subsections (Moodle 4.4+).
     * Subsections are course modules with modname = 'subsection'.
     *
     * @param int $courseid
     * @return bool
     */
    public static function course_has_subsections(int $courseid): bool {
        global $DB;
        return $DB->record_exists_sql(
            "SELECT 1 FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
              WHERE cm.course = :courseid AND m.name = 'subsection'",
            ['courseid' => $courseid]
        );
    }

    /**
     * Get all unique module types (modnames) used in a course,
     * only for activities that have completion tracking enabled.
     *
     * @param int $courseid
     * @return string[]  e.g. ['quiz', 'scorm', 'assign']
     */
    public static function get_tracked_modtypes(int $courseid): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT DISTINCT m.name
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
              WHERE cm.course = :courseid
                AND cm.completion != 0
                AND cm.deletioninprogress = 0
              ORDER BY m.name ASC",
            ['courseid' => $courseid]
        );
        return array_keys($rows);
    }

    /**
     * Build the candidate part list for auto-generation without saving anything.
     *
     * Returns an array of candidate parts, each with:
     *   - name         string   Proposed part name
     *   - cmids         int[]    Activity cm IDs to include
     *   - skipped_cmids int[]    Activity cm IDs excluded (tracking off)
     *   - section_id    int      Moodle section ID
     *   - type          string   'section' | 'subsection' | 'remainder'
     *   - skipped_count int      Activities excluded (tracking off / wrong type)
     *   - activities    array    [['cmid'=>, 'name'=>, 'modname'=>]] for included
     *   - skipped_acts  array    [['cmid'=>, 'name'=>, 'modname'=>]] for excluded
     *
     * @param int    $courseid
     * @param string $mode         'section' | 'subsection' | 'both'
     * @param int[]  $section_ids  Which section IDs to include (empty = all)
     * @param string[] $modtypes   Which mod types to include (empty = all tracked)
     * @return array
     */
    public static function build_auto_parts(
        int $courseid,
        string $mode,
        array $section_ids,
        array $subsection_ids,
        array $modtypes,
        bool $include_subsection_activities = true
    ): array {
        $modinfo  = get_fast_modinfo($courseid);
        $sections = $modinfo->get_section_info_all();
        $results  = [];

        // Build a map of subsection cmid → section_info for subsection mode.
        $subsection_modname = null;
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->modname === 'subsection') {
                $subsection_modname = 'subsection';
                break;
            }
        }

        foreach ($sections as $section) {
            // Skip section 0 (general/hidden area).
            if ($section->section == 0) continue;

            // Filter by selected sections if specified.
            if (!empty($section_ids) && !in_array((int)$section->id, $section_ids)) continue;

            $section_name = get_section_name($courseid, $section->section);

            if ($mode === 'section') {
                // Collect activities from this section.
                // If include_subsection_activities is true, also collect from child subsections.
                $candidate = self::collect_section_activities(
                    $modinfo, $section, $modtypes, false
                );
                if ($include_subsection_activities) {
                    // Also gather activities from subsections within this section.
                    $sub_cms = self::get_subsections_in_section($modinfo, $section);
                    // Build instance lookup map.
                    $inst_map = [];
                    foreach ($modinfo->get_section_info_all() as $s) {
                        if (($s->component ?? '') === 'mod_subsection' && !empty($s->itemid)) {
                            $inst_map[(int)$s->itemid] = $s;
                        }
                    }
                    foreach ($sub_cms as $sub_cm) {
                        $sub_s = $inst_map[(int)$sub_cm->instance] ?? null;
                        if (!$sub_s) continue;
                        $sub_candidate = self::collect_section_activities($modinfo, $sub_s, $modtypes, true);
                        $candidate['cmids']         = array_merge($candidate['cmids'], $sub_candidate['cmids']);
                        $candidate['skipped']      += $sub_candidate['skipped'];
                        $candidate['skipped_cmids'] = array_merge($candidate['skipped_cmids'] ?? [], $sub_candidate['skipped_cmids']);
                    }
                }
                if (!empty($candidate['cmids'])) {
                    $results[] = [
                        'name'          => '[Section] ' . $section_name,
                        'cmids'         => $candidate['cmids'],
                        'section_id'    => (int)$section->id,
                        'type'          => 'section',
                        'skipped_count' => $candidate['skipped'],
                        'skipped_cmids' => $candidate['skipped_cmids'] ?? [],
                    ];
                } else {
                    $results[] = [
                        'name'          => '[Section] ' . $section_name,
                        'cmids'         => [],
                        'section_id'    => (int)$section->id,
                        'type'          => 'section',
                        'skipped_count' => $candidate['skipped'],
                        'skipped_cmids' => $candidate['skipped_cmids'] ?? [],
                        'empty'         => true,
                    ];
                }

            } elseif ($mode === 'subsection') {
                // One part per subsection within this section.
                // Build sections_by_id map for this lookup.
                $sections_by_id_local = [];
                foreach ($modinfo->get_section_info_all() as $s) {
                    $sections_by_id_local[$s->id] = $s;
                    if (($s->component ?? '') === 'mod_subsection' && !empty($s->itemid)) {
                        $sections_by_id_local['inst_' . $s->itemid] = $s;
                    }
                }
                $subsections = self::get_subsections_in_section($modinfo, $section);
                foreach ($subsections as $sub_cm) {
                    // Find child section via instance key.
                    $sub_section = $sections_by_id_local['inst_' . $sub_cm->instance] ?? null;
                    $sub_section_candidate = $sub_section;
                    if (!empty($subsection_ids) && $sub_section_candidate
                            && !in_array((int)$sub_section_candidate->id, $subsection_ids)) {
                        continue;
                    }
                    if (!$sub_section) continue;
                    $sub_name = $sub_cm->subname ?: get_section_name($courseid, $sub_section->section);
                    $candidate = self::collect_section_activities($modinfo, $sub_section, $modtypes, true);
                    if (!empty($candidate['cmids'])) {
                        $results[] = [
                            'name'          => '[Subsection] ' . $sub_name,
                            'cmids'         => $candidate['cmids'],
                            'section_id'    => (int)($sub_section->id ?? 0),
                            'type'          => 'subsection',
                            'skipped_count' => $candidate['skipped'],
                        'skipped_cmids' => $candidate['skipped_cmids'] ?? [],
                        ];
                    } else {
                        $results[] = [
                            'name'          => '[Subsection] ' . $sub_name,
                            'cmids'         => [],
                            'section_id'    => (int)($sub_section->id ?? 0),
                            'type'          => 'subsection',
                            'skipped_count' => $candidate['skipped'],
                        'skipped_cmids' => $candidate['skipped_cmids'] ?? [],
                            'empty'         => true,
                        ];
                    }
                }

            } elseif ($mode === 'both') {
                // Subsections become their own parts.
                // Build instance lookup map.
                $inst_map_both = [];
                foreach ($modinfo->get_section_info_all() as $s) {
                    if (($s->component ?? '') === 'mod_subsection' && !empty($s->itemid)) {
                        $inst_map_both[(int)$s->itemid] = $s;
                    }
                }
                $subsections = self::get_subsections_in_section($modinfo, $section);
                $sub_cmids   = [];
                foreach ($subsections as $sub_cm) {
                    $sub_section_candidate = $inst_map_both[(int)$sub_cm->instance] ?? null;
                    if (!empty($subsection_ids) && $sub_section_candidate
                            && !in_array((int)$sub_section_candidate->id, $subsection_ids)) {
                        // Still track cmids so remainder excludes them.
                        if ($sub_section_candidate) {
                            $tmp = self::collect_section_activities($modinfo, $sub_section_candidate, $modtypes, true);
                            $sub_cmids = array_merge($sub_cmids, $tmp['cmids'], $tmp['skipped_cmids']);
                        }
                        continue;
                    }
                    $sub_section = $modinfo->get_section_info_by_id($sub_cm->section ?? 0);
                    if (!$sub_section) continue;
                    $sub_name  = $sub_cm->subname ?: get_section_name($courseid, $sub_section->section);
                    $candidate = self::collect_section_activities($modinfo, $sub_section, $modtypes, true);
                    // Track subsection cm IDs so we can exclude from remainder.
                    $sub_cmids = array_merge($sub_cmids, $candidate['cmids'], $candidate['skipped_cmids']);
                    if (!empty($candidate['cmids'])) {
                        $results[] = [
                            'name'          => '[Subsection] ' . $sub_name,
                            'cmids'         => $candidate['cmids'],
                            'section_id'    => (int)($sub_section->id ?? 0),
                            'type'          => 'subsection',
                            'skipped_count' => $candidate['skipped'],
                        'skipped_cmids' => $candidate['skipped_cmids'] ?? [],
                        ];
                    } else {
                        $results[] = [
                            'name'          => '[Subsection] ' . $sub_name,
                            'cmids'         => [],
                            'section_id'    => (int)($sub_section->id ?? 0),
                            'type'          => 'subsection',
                            'skipped_count' => $candidate['skipped'],
                        'skipped_cmids' => $candidate['skipped_cmids'] ?? [],
                            'empty'         => true,
                        ];
                    }
                }

                // Remainder = activities directly in section, not in subsections.
                $remainder = self::collect_section_activities(
                    $modinfo, $section, $modtypes, false, $sub_cmids
                );
                if (!empty($remainder['cmids'])) {
                    $results[] = [
                        'name'          => '[Section] ' . $section_name . ' — General',
                        'cmids'         => $remainder['cmids'],
                        'section_id'    => (int)$section->id,
                        'type'          => 'remainder',
                        'skipped_count' => $remainder['skipped'],
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Enrich auto-part candidates with activity names for the preview UI.
     * Adds 'activities' (included) and 'skipped_acts' (excluded) arrays to each candidate.
     */
    public static function enrich_candidates(array $candidates, int $courseid): array {
        $modinfo = get_fast_modinfo($courseid);
        $cms     = $modinfo->get_cms();

        foreach ($candidates as &$c) {
            $c['activities']   = [];
            $c['skipped_acts'] = [];
            foreach ($c['cmids'] ?? [] as $cmid) {
                if (isset($cms[$cmid])) {
                    $cm = $cms[$cmid];
                    $c['activities'][] = [
                        'cmid'    => $cmid,
                        'name'    => $cm->name,
                        'modname' => $cm->modname,
                    ];
                }
            }
            foreach ($c['skipped_cmids'] ?? [] as $cmid) {
                if (isset($cms[$cmid])) {
                    $cm = $cms[$cmid];
                    $c['skipped_acts'][] = [
                        'cmid'    => $cmid,
                        'name'    => $cm->name,
                        'modname' => $cm->modname,
                    ];
                }
            }
        }
        unset($c);
        return $candidates;
    }

    /**
     * Collect tracked activities from a section.
     *
     * @param \cm_info[] $modinfo
     * @param \section_info $section
     * @param string[] $modtypes    Empty = all tracked types.
     * @param bool $is_subsection   If true, skip the 'subsection' module itself.
     * @param int[] $exclude_cmids  cmids to exclude (already in subsections).
     * @return array ['cmids' => int[], 'skipped' => int, 'skipped_cmids' => int[]]
     */
    private static function collect_section_activities(
        \course_modinfo $modinfo,
        \section_info $section,
        array $modtypes,
        bool $is_subsection = false,
        array $exclude_cmids = []
    ): array {
        $cmids         = [];
        $skipped       = 0;
        $skipped_cmids = [];

        foreach ($modinfo->get_cms() as $cm) {
            if ((int)$cm->section !== (int)$section->id) continue;
            if ($cm->modname === 'subsection') continue; // Never include subsection containers.
            if ($cm->modname === 'label')      continue; // Skip text/media.
            if (in_array((int)$cm->id, $exclude_cmids)) continue;
            if ($cm->deletioninprogress)       continue;

            // Type filter.
            if (!empty($modtypes) && !in_array($cm->modname, $modtypes)) continue;

            // Tracking check.
            if ($cm->completion == 0) {
                $skipped++;
                $skipped_cmids[] = (int)$cm->id;
                continue;
            }

            $cmids[] = (int)$cm->id;
        }

        return ['cmids' => $cmids, 'skipped' => $skipped, 'skipped_cmids' => $skipped_cmids];
    }

    /**
     * Get the subsection course modules within a section.
     * Returns cm_info objects for each subsection container.
     *
     * @param \course_modinfo $modinfo
     * @param \section_info   $section
     * @return \cm_info[]
     */
    private static function get_subsections_in_section(
        \course_modinfo $modinfo,
        \section_info $section
    ): array {
        global $DB;
        // Use DB query to get instance ID — cm_info->instance may be protected.
        $rows = $DB->get_records_sql(
            "SELECT cm.id, cm.instance, cm.section, sub.name as subname
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = 'subsection'
               JOIN {subsection} sub ON sub.id = cm.instance
              WHERE cm.section = :sectionid AND cm.deletioninprogress = 0",
            ['sectionid' => $section->id]
        );
        return array_values($rows);
    }

    /**
     * Save auto-generated parts to the database.
     * Handles duplicate name resolution per the chosen strategy.
     *
     * @param int    $courseid
     * @param array  $candidates   From build_auto_parts().
     * @param string $dupe_action  'skip' | 'overwrite' | 'rename'
     * @return array  ['created' => int, 'skipped' => int, 'overwritten' => int, 'renamed' => int]
     */
    public static function save_auto_parts(
        int $courseid,
        array $candidates,
        string $dupe_action
    ): array {
        global $DB;

        $stats = ['created' => 0, 'skipped' => 0, 'overwritten' => 0, 'renamed' => 0];

        foreach ($candidates as $candidate) {
            if (!empty($candidate['empty']) || empty($candidate['cmids'])) {
                continue;
            }

            $name    = $candidate['name'];
            $existing = $DB->get_record('asyncwatch_parts', ['courseid' => $courseid, 'name' => $name]);

            if ($existing) {
                if ($dupe_action === 'skip') {
                    $stats['skipped']++;
                    continue;
                } elseif ($dupe_action === 'overwrite') {
                    // Replace activities on existing part.
                    self::set_part_activities((int)$existing->id, $candidate['cmids']);
                    $DB->set_field('asyncwatch_parts', 'is_auto', 1, ['id' => $existing->id]);
                    $stats['overwritten']++;
                    continue;
                } elseif ($dupe_action === 'rename') {
                    // Append number until unique.
                    $i = 2;
                    while ($DB->record_exists('asyncwatch_parts', ['courseid' => $courseid, 'name' => $name . ' (' . $i . ')'])) {
                        $i++;
                    }
                    $name = $name . ' (' . $i . ')';
                    $stats['renamed']++;
                }
            }

            $record = (object)[
                'courseid'     => $courseid,
                'name'         => $name,
                'sortorder'    => 0,
                'is_auto'      => 1,
                'timecreated'  => time(),
                'timemodified' => time(),
            ];
            $partid = (int)$DB->insert_record('asyncwatch_parts', $record);
            self::set_part_activities($partid, $candidate['cmids']);
            $stats['created']++;
        }

        return $stats;
    }

    // -------------------------------------------------------------------------
    // RULE OVERRIDES
    // -------------------------------------------------------------------------

    /**
     * Get all overrides for a rule, keyed by id.
     */
    public static function get_rule_overrides(int $ruleid): array {
        global $DB;
        return $DB->get_records('asyncwatch_rule_overrides', ['ruleid' => $ruleid], 'deadline ASC');
    }

    /**
     * Save (insert or update) a rule override.
     */
    public static function save_rule_override(\stdClass $data): int {
        global $DB;
        $now = time();
        if (!empty($data->id)) {
            $data->timemodified = $now;
            $DB->update_record('asyncwatch_rule_overrides', $data);
            return (int)$data->id;
        }
        $data->timecreated  = $now;
        $data->timemodified = $now;
        return (int)$DB->insert_record('asyncwatch_rule_overrides', $data);
    }

    /**
     * Delete a single override.
     */
    public static function delete_rule_override(int $overrideid): void {
        global $DB;
        $DB->delete_records('asyncwatch_rule_overrides', ['id' => $overrideid]);
    }

    /**
     * Delete all overrides for a rule (called when rule is deleted).
     */
    public static function delete_rule_overrides(int $ruleid): void {
        global $DB;
        $DB->delete_records('asyncwatch_rule_overrides', ['ruleid' => $ruleid]);
    }

    /**
     * Cohort overrides for a course-level rule — parallel to
     * get_rule_overrides()/save_rule_override()/delete_rule_override().
     */
    public static function get_rule_cohort_overrides(int $ruleid): array {
        global $DB;
        return $DB->get_records('asyncwatch_rule_cohort_overrides', ['ruleid' => $ruleid]);
    }

    public static function save_rule_cohort_override(\stdClass $data): int {
        global $DB;
        $now = time();
        if (!empty($data->id)) {
            $data->timemodified = $now;
            $DB->update_record('asyncwatch_rule_cohort_overrides', $data);
            return (int)$data->id;
        }
        $data->timecreated  = $now;
        $data->timemodified = $now;
        return (int)$DB->insert_record('asyncwatch_rule_cohort_overrides', $data);
    }

    public static function delete_rule_cohort_override(int $overrideid): void {
        global $DB;
        $DB->delete_records('asyncwatch_rule_cohort_overrides', ['id' => $overrideid]);
    }

    public static function delete_rule_cohort_overrides(int $ruleid): void {
        global $DB;
        $DB->delete_records('asyncwatch_rule_cohort_overrides', ['ruleid' => $ruleid]);
    }

    /**
     * Get the effective deadline and warn_hours for a user against a rule.
     *
     * If the user's group(s) or cohort(s) have overrides, returns the one
     * with the latest deadline (most lenient) across both. Falls back to
     * the rule defaults.
     *
     * @return array  ['deadline' => int, 'warn_hours' => int, 'override' => stdClass|null]
     */
    public static function get_effective_deadline(
        \stdClass $rule, int $userid, int $courseid
    ): array {
        global $DB;

        $candidates = [];

        // Query group membership directly from DB to avoid stale Moodle cache.
        $groupids = $DB->get_fieldset_sql(
            "SELECT gm.groupid
               FROM {groups_members} gm
               JOIN {groups} g ON g.id = gm.groupid
              WHERE gm.userid = :userid AND g.courseid = :courseid",
            ['userid' => $userid, 'courseid' => $courseid]
        );
        if (!empty($groupids)) {
            list($in_sql, $params) = $DB->get_in_or_equal($groupids);
            $params[] = $rule->id;
            $group_overrides = $DB->get_records_sql(
                "SELECT * FROM {asyncwatch_rule_overrides}
                  WHERE groupid $in_sql AND ruleid = ?",
                $params
            );
            $candidates = array_merge($candidates, array_values($group_overrides));
        }

        // Cohort membership is site-wide, so no course filter needed here.
        $cohortids = $DB->get_fieldset_sql(
            "SELECT cohortid FROM {cohort_members} WHERE userid = :userid",
            ['userid' => $userid]
        );
        if (!empty($cohortids)) {
            list($in_sql, $params) = $DB->get_in_or_equal($cohortids);
            $params[] = $rule->id;
            $cohort_overrides = $DB->get_records_sql(
                "SELECT * FROM {asyncwatch_rule_cohort_overrides}
                  WHERE cohortid $in_sql AND ruleid = ?",
                $params
            );
            $candidates = array_merge($candidates, array_values($cohort_overrides));
        }

        $best_override = null;
        foreach ($candidates as $ov) {
            if ($best_override === null || (int)$ov->deadline > (int)$best_override->deadline) {
                $best_override = $ov;
            }
        }

        if ($best_override) {
            return [
                'deadline'   => (int)$best_override->deadline,
                'warn_hours' => (int)$best_override->warn_hours,
                'override'   => $best_override,
            ];
        }

        return [
            'deadline'   => (int)$rule->deadline,
            'warn_hours' => (int)$rule->warn_hours,
            'override'   => null,
        ];
    }

    /**
     * Same result as get_effective_deadline(), but does zero DB queries —
     * it works entirely from pre-loaded group/cohort membership and
     * override maps, so a caller processing many users against one rule
     * can bulk-load those maps ONCE per rule rather than paying for fresh
     * group/cohort/override lookups on every single (user, rule) pair.
     *
     * Originally lived as a private method on the scheduled task; promoted
     * here so report.php can share it too, rather than either duplicating
     * the logic or falling back to the slow per-call version above.
     *
     * @param array $overrides_by_group  [groupid => override record]
     * @param array $overrides_by_cohort [cohortid => override record]
     */
    public static function effective_deadline_from_cache(
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

    // -------------------------------------------------------------------------
    // RESTRICTIONS
    //
    // A rule can optionally be restricted to specific course groups and/or
    // cohorts — a learner in EITHER a restricted group OR a restricted
    // cohort is in scope. Empty (no restrictions at all) = applies to
    // everyone enrolled. This mirrors how cross-course rule cohort
    // targeting already works (see get_global_rule_cohortids()).
    // -------------------------------------------------------------------------

    public static function get_rule_restrict_groupids(int $ruleid): array {
        global $DB;
        $rows = $DB->get_records('asyncwatch_rule_restrict_groups', ['ruleid' => $ruleid], '', 'groupid');
        return array_map('intval', array_keys($rows));
    }

    public static function set_rule_restrict_groups(int $ruleid, array $groupids): void {
        global $DB;
        $DB->delete_records('asyncwatch_rule_restrict_groups', ['ruleid' => $ruleid]);
        foreach (array_unique(array_map('intval', $groupids)) as $gid) {
            $DB->insert_record('asyncwatch_rule_restrict_groups', (object)['ruleid' => $ruleid, 'groupid' => $gid]);
        }
    }

    public static function get_rule_restrict_cohortids(int $ruleid): array {
        global $DB;
        $rows = $DB->get_records('asyncwatch_rule_restrict_cohorts', ['ruleid' => $ruleid], '', 'cohortid');
        return array_map('intval', array_keys($rows));
    }

    public static function set_rule_restrict_cohorts(int $ruleid, array $cohortids): void {
        global $DB;
        $DB->delete_records('asyncwatch_rule_restrict_cohorts', ['ruleid' => $ruleid]);
        foreach (array_unique(array_map('intval', $cohortids)) as $cid) {
            $DB->insert_record('asyncwatch_rule_restrict_cohorts', (object)['ruleid' => $ruleid, 'cohortid' => $cid]);
        }
    }

    // -------------------------------------------------------------------------
    // NOTIFICATION HELPERS
    // -------------------------------------------------------------------------

    /**
     * Has a notification of this type already been sent for this rule + user?
     */
    /**
     * Has a notification of this type already been sent for this rule + user?
     *
     * @param int|null $within_seconds When null (the default — used for
     *        individual learner notifications), dedup is permanent: a
     *        learner should only ever get one breach/warning email per
     *        rule. When given, only counts as "already sent" if it
     *        happened within that many seconds of now — used for the
     *        staff digest, which is meant to go out once per CRON RUN
     *        (see send_staff_digest()'s docblock), not once ever. Without
     *        this, the very first successful digest permanently silences
     *        every later run — this is what actually happened before.
     */
    public static function notification_already_sent(int $ruleid, int $userid, string $type, ?int $within_seconds = null): bool {
        global $DB;
        $params = ['ruleid' => $ruleid, 'userid' => $userid, 'type' => $type];
        $select = "ruleid = :ruleid AND userid = :userid AND type = :type";
        if ($within_seconds !== null) {
            $select .= " AND timesent >= :since";
            $params['since'] = time() - $within_seconds;
        }
        return $DB->record_exists_select('asyncwatch_notifications', $select, $params);
    }

    /**
     * Record that a notification was sent.
     */
    public static function record_notification(int $ruleid, int $userid, string $type): void {
        global $DB;
        $DB->insert_record('asyncwatch_notifications', (object)[
            'ruleid'    => $ruleid,
            'userid'    => $userid,
            'type'      => $type,
            'timesent'  => time(),
        ]);
    }

    /**
     * Format a timestamp for a SPECIFIC recipient's language and timezone
     * — not the current session's. During a cron run there is no "current
     * user" in any meaningful sense, so a plain userdate() call renders
     * using the server's default language/timezone rather than whichever
     * learner or staff member is actually about to read the notification.
     * force_current_language() affects userdate()'s date-format string
     * (pulled via get_string()); core_date::get_user_timezone() resolves
     * that specific user's timezone setting (including 'server'/99
     * fall-through), not $USER's.
     */
    public static function user_date(int $timestamp, \stdClass $user, string $format = ''): string {
        $previous_lang = force_current_language($user->lang ?? '');
        $formatted = userdate($timestamp, $format, \core_date::get_user_timezone($user));
        force_current_language($previous_lang);
        return $formatted;
    }

    /**
     * Send a learner notification via Moodle's Message API instead of a
     * raw email — this is the correct transport for individual, no-
     * attachment notifications: it respects the recipient's own message
     * processor preferences (email / mobile push / popup — not everyone
     * wants these as email), shows up in Moodle's own notification
     * centre, and honours quiet-hours/digest settings, none of which
     * email_to_user() does.
     *
     * NOT used for the staff digest emails (send_email_with_attachment())
     * — the Message API has no file-attachment mechanism, so a CSV-
     * attached report genuinely cannot go through it. That's kept on
     * email_to_user() deliberately, not by oversight.
     */
    public static function send_message(\stdClass $user, string $subject, string $body): bool {
        $plain = $body;
        $plain = preg_replace('/<\/p>\s*<p[^>]*>/i', "\n\n", $plain);
        $plain = preg_replace('/<p[^>]*>/i',  '',     $plain);
        $plain = preg_replace('/<\/p>/i',     "\n\n", $plain);
        $plain = preg_replace('/<br\s*\/?>/i',"\n",   $plain);
        $plain = strip_tags($plain);
        $plain = html_entity_decode($plain, ENT_QUOTES, 'UTF-8');
        $plain = preg_replace('/\n{3,}/', "\n\n", trim($plain));

        $message = new \core\message\message();
        $message->component         = 'local_asyncwatch';
        $message->name               = 'progressnotification';
        $message->userfrom           = \core_user::get_support_user();
        $message->userto             = $user;
        $message->subject            = $subject;
        $message->fullmessage        = $plain;
        $message->fullmessageformat  = FORMAT_HTML;
        $message->fullmessagehtml    = $body;
        $message->smallmessage       = $subject;
        $message->notification       = 1;
        $message->contexturl         = (new \moodle_url('/local/asyncwatch/report.php'))->out(false);
        $message->contexturlname     = get_string('pluginname', 'local_asyncwatch');

        return (bool)message_send($message);
    }

    /**
     * Send an email to a user with an optional file attachment.
     *
     * @param stdClass $user
     * @param string   $subject
     * @param string   $body           HTML body.
     * @param string   $attachment_path Full filesystem path to the attachment, or '' for none.
     * @param string   $attachname     Filename to show the recipient.
     */
    public static function send_email_with_attachment(
        \stdClass $user, string $subject, string $body,
        string $attachment_path = '', string $attachname = ''
    ): bool {
        global $CFG;
        $support = \core_user::get_support_user();

        $plain = $body;
        $plain = preg_replace('/<\/p>\s*<p[^>]*>/i', "\n\n", $plain);
        $plain = preg_replace('/<p[^>]*>/i',  '',     $plain);
        $plain = preg_replace('/<\/p>/i',     "\n\n", $plain);
        $plain = preg_replace('/<br\s*\/?>/i',"\n",   $plain);
        $plain = strip_tags($plain);
        $plain = html_entity_decode($plain, ENT_QUOTES, 'UTF-8');
        $plain = preg_replace('/\n{3,}/', "\n\n", trim($plain));

        if ($attachment_path === '') {
            return email_to_user($user, $support, $subject, $plain, $body);
        }

        // email_to_user() expects the attachment path relative to $CFG->dataroot.
        $dataroot  = rtrim($CFG->dataroot, '/');
        $real      = realpath($attachment_path);
        $copy_path = null;
        if ($real === false || strpos($real, $dataroot) !== 0) {
            // Attachment isn't inside dataroot (e.g. sys temp dir, or a
            // symlinked dataroot resolving differently through realpath()
            // — not unusual on shared hosting) — copy it into
            // dataroot/temp so email_to_user() can find it.
            $temp_dir = $CFG->dataroot . '/temp/asyncwatch';
            if (!is_dir($temp_dir)) {
                @mkdir($temp_dir, $CFG->directorypermissions ?? 02777, true);
            }
            $copy_path = $temp_dir . '/' . basename($attachment_path);
            @copy($attachment_path, $copy_path);
            $real = $copy_path;
        }
        $relative = ltrim(substr($real, strlen($dataroot)), '/');

        $result = email_to_user($user, $support, $subject, $plain, $body, $relative, $attachname);

        // Clean up the fallback copy — the original at $attachment_path is
        // still the caller's responsibility (see write_csv_tempfile()'s
        // docblock), but this copy only ever exists as an internal detail
        // of this method, so nothing else would ever delete it.
        if ($copy_path !== null && is_file($copy_path)) {
            @unlink($copy_path);
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // PROGRESS ROW STATUS + CSV EXPORT (shared by report.php and staff digest emails)
    // -------------------------------------------------------------------------

    /**
     * Determine the status label for a single rule/user progress row.
     */
    public static function status_for_progress(
        \stdClass $rule, int $done, int $now, int $eff_deadline, int $eff_warn
    ): string {
        if ($done >= $rule->parts_required) return 'completed';
        if ($now >= $eff_deadline) return 'breach';
        if ($eff_warn > 0 && $now >= ($eff_deadline - ($eff_warn * MINSECS))) return 'warning';
        return 'ok';
    }

    // -------------------------------------------------------------------------
    // PROFILE FIELD SYNC
    //
    // A rule (course-level or cross-course) can optionally write its
    // computed status into a user profile custom field. Only text/menu
    // fields are offered, since the value written is always a plain
    // status label (e.g. "On track"). Reads/writes are bulk-loaded per
    // rule in check_progress.php, not per user, to keep the cron task
    // fast on large cohorts.
    // -------------------------------------------------------------------------

    /**
     * Text/menu-type user profile custom fields, suitable for status sync.
     *
     * @param bool $restrict_to_course_safe When true (course-level rules,
     *        manage.php), only offers locked=0 fields that are also on the
     *        site-wide allowlist (Site administration → AsyncWatch) — see
     *        that setting's own description for why this restriction
     *        exists. When false (cross-course rules, globalrules.php —
     *        already gated by the site-wide manageglobal capability),
     *        every unlocked-or-locked text/menu field is offered, same as
     *        before.
     * @param string|null $currently_selected A rule's already-configured
     *        field shortname, if any — kept in the list even if it would
     *        otherwise be excluded, so editing an existing rule doesn't
     *        silently clear a working configuration out from under it.
     * @return array shortname => "Field name (shortname)"
     */
    public static function get_profile_field_options(
        bool $restrict_to_course_safe = false, ?string $currently_selected = null
    ): array {
        global $DB;
        $where = "datatype IN ('text', 'menu')";
        if ($restrict_to_course_safe) {
            $where .= " AND locked = 0";
        }
        $fields = $DB->get_records_select(
            'user_info_field',
            $where,
            null,
            'categoryid ASC, sortorder ASC',
            'id, shortname, name'
        );

        $allowlist = $restrict_to_course_safe ? self::get_course_profile_field_allowlist() : null;

        $options = [];
        foreach ($fields as $f) {
            if ($allowlist !== null && !in_array($f->shortname, $allowlist, true)) {
                continue;
            }
            $options[$f->shortname] = format_string($f->name) . ' (' . $f->shortname . ')';
        }

        if ($currently_selected && !isset($options[$currently_selected])) {
            $existing = $DB->get_record('user_info_field', ['shortname' => $currently_selected], 'name');
            if ($existing) {
                $options[$currently_selected] = format_string($existing->name) . ' (' . $currently_selected . ')';
            }
        }

        return $options;
    }

    /**
     * Shortnames of profile fields a site admin has explicitly allowed
     * course-level rules to write to. Empty by default — nothing is
     * allowed until an admin opts fields in.
     */
    public static function get_course_profile_field_allowlist(): array {
        $raw = get_config('local_asyncwatch', 'course_profile_field_allowlist');
        if (empty($raw)) return [];
        // admin_setting_configmulticheckbox stores checked keys as a
        // comma-separated string.
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    /**
     * Resolve a profile field shortname to its user_info_field id.
     */
    public static function get_profile_field_id(string $shortname): ?int {
        global $DB;
        if ($shortname === '') return null;
        $field = $DB->get_record('user_info_field', ['shortname' => $shortname], 'id');
        return $field ? (int)$field->id : null;
    }

    /**
     * Datatype + configured menu options for a profile field, so a caller
     * can check a value actually belongs to that field's own option set
     * before writing to it.
     */
    public static function get_profile_field_meta(int $fieldid): ?\stdClass {
        global $DB;
        return $DB->get_record('user_info_field', ['id' => $fieldid], 'id, datatype, param1') ?: null;
    }

    /**
     * Every status label this plugin could ever write via profile field
     * sync — used to validate a menu-type field actually has all four
     * options configured before syncing to it at all (see
     * get_profile_field_meta()'s caller in check_progress.php).
     */
    public static function all_status_labels(): array {
        return [
            get_string('status_ok',        'local_asyncwatch'),
            get_string('status_warning',   'local_asyncwatch'),
            get_string('status_breach',    'local_asyncwatch'),
            get_string('status_completed', 'local_asyncwatch'),
        ];
    }

    /**
     * True if a profile field can safely receive every status label this
     * plugin might write to it — always true for text fields, and true
     * for a menu field only if ALL FOUR status labels are among its own
     * configured options. A menu field missing even one status option
     * should never be synced to at all, rather than silently accepting
     * some statuses and not others.
     */
    public static function profile_field_menu_compatible(\stdClass $meta): bool {
        if ($meta->datatype !== 'menu') return true;
        $menu_options = array_map('trim', explode("\n", $meta->param1 ?? ''));
        return empty(array_diff(self::all_status_labels(), $menu_options));
    }

    /**
     * Bulk-load existing user_info_data rows for a set of users under one
     * field, keyed by userid. One query regardless of cohort size.
     *
     * @return array userid => stdClass{id, userid, data}
     */
    public static function bulk_get_profile_field_data(int $fieldid, array $userids): array {
        global $DB;
        if (empty($userids)) return [];
        list($insql, $params) = $DB->get_in_or_equal($userids);
        $params[] = $fieldid;
        $rows = $DB->get_records_sql(
            "SELECT id, userid, data FROM {user_info_data} WHERE userid $insql AND fieldid = ?",
            $params
        );
        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r->userid] = $r;
        }
        return $map;
    }

    /**
     * Write (insert or update) a single user's profile field value.
     * Callers should already have checked the value actually changed —
     * this does not compare, just writes.
     */
    public static function write_profile_field_value(
        int $userid, int $fieldid, string $value, ?\stdClass $existing
    ): void {
        global $DB;
        if ($existing) {
            $existing->data = $value;
            $DB->update_record('user_info_data', $existing);
            return;
        }
        $DB->insert_record('user_info_data', (object)[
            'userid'     => $userid,
            'fieldid'    => $fieldid,
            'data'       => $value,
            'dataformat' => 0,
        ]);
    }

    /**
     * CSV header row for a progress report, given the parts in play.
     *
     * @param bool $include_email Whether to include the Email column — see
     *             csv_row() for why this is optional.
     */
    public static function csv_header(array $parts, bool $include_email = true): array {
        $header = [
            get_string('rulename',       'local_asyncwatch'),
            get_string('learner',        'local_asyncwatch'),
        ];
        if ($include_email) {
            $header[] = 'Email';
        }
        $header = array_merge($header, [
            get_string('parts_complete', 'local_asyncwatch'),
            get_string('status',         'local_asyncwatch'),
            get_string('last_activity',  'local_asyncwatch'),
        ]);
        foreach ($parts as $part) {
            $header[] = format_string($part->name);
        }
        return $header;
    }

    /**
     * Guard against CSV/spreadsheet formula injection: if a cell's value
     * starts with =, +, -, or @, prefix it with a leading apostrophe so
     * Excel/Sheets treats it as literal text rather than evaluating it as
     * a formula when the exported file is opened. Applied to every cell
     * rather than cherry-picking "risky" fields — rule names, learner
     * names, and course names in this plugin all ultimately come from
     * user-editable input somewhere upstream.
     */
    private static function csv_safe_row(array $line): array {
        return array_map(function($value) {
            $value = (string)$value;
            if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
                return "'" . $value;
            }
            return $value;
        }, $line);
    }

    /**
     * CSV data row for a single progress-report row object.
     * $row must have: rule, user, done, total, status, parts, lastaccess.
     *
     * @param bool $include_email Whether to include the learner's email
     *             address. Defaults true so the automated staff digest
     *             (which isn't tied to any one interactive viewer) is
     *             unaffected — but the on-demand, viewer-initiated export
     *             in report.php passes this based on whether the CURRENT
     *             viewer actually has moodle/site:viewuseridentity for
     *             email in this context, since it was previously exporting
     *             email addresses regardless of that permission.
     */
    public static function csv_row(\stdClass $row, array $parts, bool $include_email = true): array {
        $line = [
            format_string($row->rule->name),
            fullname($row->user),
        ];
        if ($include_email) {
            $line[] = $row->user->email;
        }
        $line = array_merge($line, [
            $row->done . ' of ' . $row->total,
            get_string('status_' . $row->status, 'local_asyncwatch'),
            $row->lastaccess ? userdate($row->lastaccess) : '—',
        ]);
        foreach ($parts as $part) {
            $line[] = ($row->parts[$part->id] ?? false) ? '1' : '0';
        }
        return self::csv_safe_row($line);
    }

    /**
     * Render a set of progress-report rows as CSV text.
     */
    public static function rows_to_csv(array $rows, array $parts, bool $include_email = true): string {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, self::csv_header($parts, $include_email));
        foreach ($rows as $row) {
            fputcsv($stream, self::csv_row($row, $parts, $include_email));
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        return $csv;
    }

    /**
     * Write a set of progress-report rows to a temporary CSV file and
     * return its full path. Caller is responsible for deleting it once sent.
     */
    public static function write_csv_tempfile(array $rows, array $parts, string $filename_hint = 'report'): string {
        global $CFG;
        $csv      = self::rows_to_csv($rows, $parts);
        $temp_dir = $CFG->dataroot . '/temp/asyncwatch';
        if (!is_dir($temp_dir)) {
            @mkdir($temp_dir, $CFG->directorypermissions ?? 02777, true);
        }
        $path = $temp_dir . '/' . $filename_hint . '_' . time() . '_' . random_string(6) . '.csv';
        file_put_contents($path, $csv);
        return $path;
    }

    // -------------------------------------------------------------------------
    // CROSS-COURSE (GLOBAL) RULES
    // -------------------------------------------------------------------------

    /**
     * Courses that have at least one Part defined, with a part count each.
     * Used to populate the course picker on the cross-course rule form —
     * courses with no Parts would contribute nothing to any rule.
     *
     * @return array  Keyed by courseid: [id, coursename, partcount]
     */
    public static function get_courses_with_parts(): array {
        global $DB;
        return $DB->get_records_sql(
            "SELECT p.courseid AS id, c.fullname AS coursename, COUNT(p.id) AS partcount
               FROM {asyncwatch_parts} p
               JOIN {course} c ON c.id = p.courseid
              GROUP BY p.courseid, c.fullname
              ORDER BY c.fullname ASC"
        );
    }

    /**
     * Return all cross-course rules, deadline soonest first.
     */
    public static function get_global_rules(): array {
        global $DB;
        return $DB->get_records('asyncwatch_global_rules', [], 'deadline ASC');
    }

    /**
     * Return a single cross-course rule record.
     */
    public static function get_global_rule(int $ruleid): \stdClass {
        global $DB;
        return $DB->get_record('asyncwatch_global_rules', ['id' => $ruleid], '*', MUST_EXIST);
    }

    /**
     * Save (insert or update) a cross-course rule.
     */
    public static function save_global_rule(\stdClass $data): int {
        global $DB;
        $now = time();
        if (!empty($data->id)) {
            $data->timemodified = $now;
            $DB->update_record('asyncwatch_global_rules', $data);
            return (int)$data->id;
        }
        $data->timecreated  = $now;
        $data->timemodified = $now;
        return (int)$DB->insert_record('asyncwatch_global_rules', $data);
    }

    /**
     * Delete a cross-course rule and everything that hangs off it.
     */
    public static function delete_global_rule(int $ruleid): void {
        global $DB;
        $DB->delete_records('asyncwatch_global_notifications',  ['ruleid' => $ruleid]);
        $DB->delete_records('asyncwatch_global_rule_overrides', ['ruleid' => $ruleid]);
        $DB->delete_records('asyncwatch_global_rule_cohorts',   ['ruleid' => $ruleid]);
        $DB->delete_records('asyncwatch_global_rule_courses',   ['ruleid' => $ruleid]);
        $DB->delete_records('asyncwatch_global_rules',          ['id'     => $ruleid]);
    }

    /**
     * Course ids a cross-course rule draws parts from.
     */
    public static function get_global_rule_courseids(int $ruleid): array {
        global $DB;
        $rows = $DB->get_records('asyncwatch_global_rule_courses', ['ruleid' => $ruleid], '', 'courseid');
        return array_map('intval', array_keys($rows));
    }

    /**
     * Replace all course assignments for a cross-course rule.
     */
    public static function set_global_rule_courses(int $ruleid, array $courseids): void {
        global $DB;
        $DB->delete_records('asyncwatch_global_rule_courses', ['ruleid' => $ruleid]);
        foreach (array_unique(array_map('intval', $courseids)) as $cid) {
            $DB->insert_record('asyncwatch_global_rule_courses', (object)['ruleid' => $ruleid, 'courseid' => $cid]);
        }
    }

    /**
     * Cohort ids a cross-course rule is targeted at. Empty = anyone
     * enrolled in the rule's courses (the v1 default).
     */
    public static function get_global_rule_cohortids(int $ruleid): array {
        global $DB;
        $rows = $DB->get_records('asyncwatch_global_rule_cohorts', ['ruleid' => $ruleid], '', 'cohortid');
        return array_map('intval', array_keys($rows));
    }

    /**
     * Replace all cohort targeting for a cross-course rule.
     */
    public static function set_global_rule_cohorts(int $ruleid, array $cohortids): void {
        global $DB;
        $DB->delete_records('asyncwatch_global_rule_cohorts', ['ruleid' => $ruleid]);
        foreach (array_unique(array_map('intval', $cohortids)) as $cid) {
            $DB->insert_record('asyncwatch_global_rule_cohorts', (object)['ruleid' => $ruleid, 'cohortid' => $cid]);
        }
    }

    /**
     * All site cohorts, id => name. Direct query rather than the cohort
     * API — this plugin doesn't need anything beyond id/name and avoids
     * depending on an exact core function signature.
     */
    public static function get_all_cohorts(): array {
        global $DB;
        return $DB->get_records('cohort', [], 'name ASC', 'id, name, idnumber');
    }

    /**
     * Cohorts visible to the current user for a specific course — for
     * course-scoped features (course-level rule Restrictions, group/cohort
     * Overrides). Unlike get_all_cohorts(), this respects Moodle's own
     * cohort visibility rules (category-level cohorts, the cohort
     * "visible" flag) via core's cohort_get_visible_list(), rather than
     * exposing every cohort on the entire site to anyone who happens to
     * have local/asyncwatch:manage in a single course.
     *
     * get_all_cohorts() is still the right call for the two genuinely
     * site-wide admin pages (globalrules.php, globaloverrides.php), which
     * are gated by local/asyncwatch:manageglobal — a real site-wide
     * capability with no course boundary to respect.
     *
     * @return array cohortid => stdClass{id, name}
     */
    public static function get_visible_cohorts_for_course(int $courseid): array {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');

        $course = get_course($courseid);
        $visible = cohort_get_visible_list($course, false); // [cohortid => name]

        $cohorts = [];
        foreach ($visible as $id => $name) {
            $cohorts[(int)$id] = (object)['id' => (int)$id, 'name' => $name];
        }
        return $cohorts;
    }

    /**
     * Sum of Parts across a set of courses — the maximum a cross-course
     * rule's parts_required could sensibly be.
     */
    public static function total_parts_for_courses(array $courseids): int {
        if (empty($courseids)) return 0;
        $courses = self::get_courses_with_parts();
        $total = 0;
        foreach ($courseids as $cid) {
            $total += isset($courses[$cid]) ? (int)$courses[$cid]->partcount : 0;
        }
        return $total;
    }

    /**
     * Users in scope for a cross-course rule: union of enrolment across
     * the rule's courses, intersected with cohort membership if the rule
     * targets one or more cohorts (no cohorts = no restriction, v1 default).
     *
     * @return array [userid => stdClass{id, firstname, lastname, email, lastaccess}]
     */
    public static function get_global_rule_users(int $ruleid): array {
        global $DB;
        $courseids = self::get_global_rule_courseids($ruleid);
        if (empty($courseids)) return [];

        $users = [];
        foreach ($courseids as $cid) {
            // Ignore courses that no longer exist — a global rule can span
            // several courses, and one deleted course shouldn't stop the
            // rest of the rule's courses from being monitored. Without
            // IGNORE_MISSING this throws and, since that propagates past
            // this loop, would otherwise abort the whole rule.
            $context = \context_course::instance($cid, IGNORE_MISSING);
            if (!$context) continue;
            $enrolled = self::get_active_learners($context);
            foreach ($enrolled as $u) {
                $users[(int)$u->id] = $u; // union — de-duplicated by userid.
            }
        }

        $cohortids = self::get_global_rule_cohortids($ruleid);
        if (!empty($cohortids) && !empty($users)) {
            list($in_sql, $params) = $DB->get_in_or_equal($cohortids);
            $rows = $DB->get_records_sql(
                "SELECT DISTINCT userid AS id FROM {cohort_members} WHERE cohortid $in_sql",
                $params
            );
            $cohort_userids = array_map('intval', array_keys($rows));
            $users = array_intersect_key($users, array_flip($cohort_userids));
        }

        return $users;
    }

    /**
     * Bulk progress for a cross-course rule across all its courses, for a
     * given set of users — sums completed Parts per user across courses.
     *
     * @return array [userid => ['completed'=>int, 'total'=>int, 'parts'=>[partid=>bool]]]
     */
    public static function bulk_get_global_rule_progress(int $ruleid, array $userids): array {
        $courseids = self::get_global_rule_courseids($ruleid);
        $total     = self::total_parts_for_courses($courseids);

        $results = [];
        foreach ($userids as $uid) {
            $results[$uid] = ['completed' => 0, 'total' => $total, 'parts' => []];
        }
        if (empty($userids) || empty($courseids)) return $results;

        foreach ($courseids as $cid) {
            $progress = self::bulk_get_user_progress($cid, $userids);
            foreach ($progress as $uid => $p) {
                $results[$uid]['completed'] += $p['completed'];
                // Merge this course's parts map into the combined one —
                // partids are unique across the whole plugin, so no clashes.
                $results[$uid]['parts'] = $results[$uid]['parts'] + $p['parts'];
            }
        }
        return $results;
    }

    /**
     * All Parts belonging to a rule's courses, each labelled with its
     * course so identically-named parts from different courses stay
     * distinguishable in a combined report/CSV.
     *
     * @return array  Keyed by partid: stdClass{id, name, courseid, coursename}
     */
    public static function get_global_rule_parts(int $ruleid): array {
        $courseids = self::get_global_rule_courseids($ruleid);
        $parts = [];
        foreach ($courseids as $cid) {
            $course = get_course($cid);
            foreach (self::get_parts($cid) as $part) {
                $part->coursename = $course->fullname;
                $parts[$part->id]  = $part;
            }
        }
        return $parts;
    }

    /**
     * Cohort deadline overrides for a cross-course rule.
     */
    public static function get_global_rule_overrides(int $ruleid): array {
        global $DB;
        return $DB->get_records('asyncwatch_global_rule_overrides', ['ruleid' => $ruleid]);
    }

    /**
     * Save (insert or update) a cohort override for a cross-course rule.
     */
    public static function save_global_rule_override(\stdClass $data): int {
        global $DB;
        $now = time();
        if (!empty($data->id)) {
            $data->timemodified = $now;
            $DB->update_record('asyncwatch_global_rule_overrides', $data);
            return (int)$data->id;
        }
        $data->timecreated  = $now;
        $data->timemodified = $now;
        return (int)$DB->insert_record('asyncwatch_global_rule_overrides', $data);
    }

    /**
     * Delete a cohort override for a cross-course rule.
     */
    public static function delete_global_rule_override(int $overrideid): void {
        global $DB;
        $DB->delete_records('asyncwatch_global_rule_overrides', ['id' => $overrideid]);
    }

    /**
     * Effective deadline/warn window for a user under a cross-course rule,
     * applying the best (latest-deadline) cohort override the user
     * belongs to, if any. Mirrors get_effective_deadline() for per-course
     * rules, keyed on cohort membership instead of course-group membership.
     */
    public static function get_global_effective_deadline(\stdClass $rule, int $userid): array {
        global $DB;
        $overrides = self::get_global_rule_overrides((int)$rule->id);
        if (empty($overrides)) {
            return ['deadline' => (int)$rule->deadline, 'warn_hours' => (int)$rule->warn_hours, 'override' => null];
        }

        $cohortids = array_map(function($o) { return (int)$o->cohortid; }, $overrides);
        list($in_sql, $params) = $DB->get_in_or_equal($cohortids);
        $params[] = $userid;
        $member_rows = $DB->get_records_sql(
            "SELECT id, cohortid FROM {cohort_members} WHERE cohortid $in_sql AND userid = ?",
            $params
        );
        $member_cohortids = array_map(function($r) { return (int)$r->cohortid; }, $member_rows);

        $best = null;
        foreach ($overrides as $ov) {
            if (!in_array((int)$ov->cohortid, $member_cohortids)) continue;
            if ($best === null || (int)$ov->deadline > (int)$best->deadline) $best = $ov;
        }
        if ($best) {
            return ['deadline' => (int)$best->deadline, 'warn_hours' => (int)$best->warn_hours, 'override' => $best];
        }
        return ['deadline' => (int)$rule->deadline, 'warn_hours' => (int)$rule->warn_hours, 'override' => null];
    }

    /**
     * CSV header for the cross-course report. No per-part columns here —
     * unlike a per-course report, a rule's Parts can come from several
     * courses at once, so a wide per-part breakdown stops being readable.
     *
     * @param bool $include_email See csv_header()'s docblock.
     */
    public static function global_csv_header(bool $include_email = true): array {
        $header = [
            get_string('rulename',              'local_asyncwatch'),
            get_string('learner',                'local_asyncwatch'),
        ];
        if ($include_email) {
            $header[] = 'Email';
        }
        return array_merge($header, [
            get_string('globalrule_col_courses', 'local_asyncwatch'),
            get_string('parts_complete',         'local_asyncwatch'),
            get_string('status',                 'local_asyncwatch'),
            get_string('last_activity',          'local_asyncwatch'),
        ]);
    }

    /**
     * CSV data row for the cross-course report.
     * $row must have: rule, user, done, total, status, lastaccess, coursenames.
     *
     * @param bool $include_email See csv_row()'s docblock.
     */
    public static function global_csv_row(\stdClass $row, bool $include_email = true): array {
        $line = [
            format_string($row->rule->name),
            fullname($row->user),
        ];
        if ($include_email) {
            $line[] = $row->user->email;
        }
        return self::csv_safe_row(array_merge($line, [
            implode(', ', $row->coursenames),
            $row->done . ' of ' . $row->total,
            get_string('status_' . $row->status, 'local_asyncwatch'),
            $row->lastaccess ? userdate($row->lastaccess) : '—',
        ]));
    }

    /**
     * Render cross-course report rows as CSV text.
     */
    public static function global_rows_to_csv(array $rows, bool $include_email = true): string {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, self::global_csv_header($include_email));
        foreach ($rows as $row) {
            fputcsv($stream, self::global_csv_row($row, $include_email));
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        return $csv;
    }

    /**
     * Has a notification of this type already been sent for a cross-course
     * rule? Mirrors notification_already_sent() but against the separate
     * asyncwatch_global_notifications table.
     */
    /**
     * Cross-course counterpart to notification_already_sent() — same
     * $within_seconds behaviour, same reasoning.
     */
    public static function global_notification_already_sent(int $ruleid, int $userid, string $type, ?int $within_seconds = null): bool {
        global $DB;
        $params = ['ruleid' => $ruleid, 'userid' => $userid, 'type' => $type];
        $select = "ruleid = :ruleid AND userid = :userid AND type = :type";
        if ($within_seconds !== null) {
            $select .= " AND timesent >= :since";
            $params['since'] = time() - $within_seconds;
        }
        return $DB->record_exists_select('asyncwatch_global_notifications', $select, $params);
    }

    /**
     * Record that a cross-course notification was sent.
     */
    public static function record_global_notification(int $ruleid, int $userid, string $type): void {
        global $DB;
        $DB->insert_record('asyncwatch_global_notifications', (object)[
            'ruleid'   => $ruleid,
            'userid'   => $userid,
            'type'     => $type,
            'timesent' => time(),
        ]);
    }

    /**
     * Staff who receive cross-course rule report emails — one site-wide
     * list shared by every cross-course rule, same idea as the site-wide
     * email wording. Stored as JSON in plugin config rather than a new
     * table, since it's just a list of user ids.
     *
     * @return int[]
     */
    public static function get_global_staff_recipient_ids(): array {
        $raw = get_config('local_asyncwatch', 'global_staff_recipients');
        if (empty($raw)) return [];
        $ids = json_decode($raw, true);
        return is_array($ids) ? array_map('intval', $ids) : [];
    }

    /**
     * Replace the site-wide cross-course staff recipient list.
     */
    public static function set_global_staff_recipient_ids(array $userids): void {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userids))));
        set_config('global_staff_recipients', json_encode($ids), 'local_asyncwatch');
    }

    /**
     * Write cross-course report rows to a temporary CSV file and return its
     * path. Mirrors write_csv_tempfile() but for the simpler global-rule
     * CSV shape (global_rows_to_csv() — no per-part columns).
     */
    public static function write_global_csv_tempfile(array $rows, string $filename_hint = 'globalreport'): string {
        global $CFG;
        $csv      = self::global_rows_to_csv($rows);
        $temp_dir = $CFG->dataroot . '/temp/asyncwatch';
        if (!is_dir($temp_dir)) {
            @mkdir($temp_dir, $CFG->directorypermissions ?? 02777, true);
        }
        $path = $temp_dir . '/' . $filename_hint . '_' . time() . '_' . random_string(6) . '.csv';
        file_put_contents($path, $csv);
        return $path;
    }
}