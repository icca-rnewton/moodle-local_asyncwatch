<?php
/**
 * Privacy provider for local_asyncwatch.
 *
 * Personal data stored by this plugin is limited to the notification
 * send-log: which learner was sent a warning/breach email, for which rule,
 * and when. Rule configuration itself (deadlines, restrictions, overrides)
 * is not "personal data" in the GDPR sense — it doesn't identify or relate
 * to a specific person until a notification actually gets logged against
 * a userid.
 *
 * userid = 0 is used in both notification tables as a sentinel row marking
 * a staff digest send (not tied to any individual) — this is filtered out
 * of every export/delete/userlist operation below, since it is not a real
 * data subject.
 *
 * The profile-field sync feature (see classes/task/check_progress.php)
 * writes a computed status string into a Moodle CORE custom profile field
 * ({user_info_data}). That table has its own core Privacy provider which
 * already handles its export/delete — this plugin only ever writes to it,
 * never reads it back for privacy purposes, so no export/delete code is
 * needed here for it. It is still disclosed below per the Privacy API
 * convention for plugins that write into a shared subsystem table.
 *
 * The site-wide cross-course staff recipient list
 * (local_asyncwatch/global_staff_recipients, plugin config) identifies
 * staff as notification recipients, not learners being tracked. It is
 * admin-configured operational data (equivalent to "who receives alerts"
 * in many plugins) rather than personal data about a tracked data subject,
 * so it is intentionally not modelled as exportable/erasable subject data
 * here. Flagging this as a deliberate scoping decision, not an oversight.
 *
 * @package    local_asyncwatch
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_asyncwatch\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\local\request\transform;

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe what personal data this plugin stores.
     */
    public static function get_metadata(collection $collection): collection {

        $collection->add_database_table(
            'asyncwatch_notifications',
            [
                'ruleid'   => 'privacy:metadata:asyncwatch_notifications:ruleid',
                'userid'   => 'privacy:metadata:asyncwatch_notifications:userid',
                'type'     => 'privacy:metadata:asyncwatch_notifications:type',
                'timesent' => 'privacy:metadata:asyncwatch_notifications:timesent',
            ],
            'privacy:metadata:asyncwatch_notifications'
        );

        $collection->add_database_table(
            'asyncwatch_global_notifications',
            [
                'ruleid'   => 'privacy:metadata:asyncwatch_global_notifications:ruleid',
                'userid'   => 'privacy:metadata:asyncwatch_global_notifications:userid',
                'type'     => 'privacy:metadata:asyncwatch_global_notifications:type',
                'timesent' => 'privacy:metadata:asyncwatch_global_notifications:timesent',
            ],
            'privacy:metadata:asyncwatch_global_notifications'
        );

        // Disclosure only — this plugin writes here but never reads it
        // back; export/delete for this table is core Moodle's own
        // responsibility, not this provider's.
        $collection->add_database_table(
            'user_info_data',
            [
                'userid' => 'privacy:metadata:user_info_data:userid',
                'data'   => 'privacy:metadata:user_info_data:data',
            ],
            'privacy:metadata:user_info_data'
        );

        return $collection;
    }

    /**
     * Every context that holds notification data for this user.
     * Per-course notifications -> that course's context.
     * Cross-course (global) notifications -> the system context, since
     * global rules have no single owning course.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        if ($userid <= 0) {
            return $contextlist;
        }

        $sql = "SELECT ctx.id
                  FROM {asyncwatch_notifications} n
                  JOIN {asyncwatch_rules} r ON r.id = n.ruleid
                  JOIN {context} ctx ON ctx.instanceid = r.courseid AND ctx.contextlevel = :contextcourse
                 WHERE n.userid = :userid1";
        $contextlist->add_from_sql($sql, [
            'contextcourse' => CONTEXT_COURSE,
            'userid1'       => $userid,
        ]);

        if ($DB->record_exists('asyncwatch_global_notifications', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    /**
     * Every user who has notification data in a given context — needed
     * for bulk/admin-initiated deletion requests, not just individual
     * subject access requests.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if ($context->contextlevel === CONTEXT_COURSE) {
            $sql = "SELECT n.userid
                      FROM {asyncwatch_notifications} n
                      JOIN {asyncwatch_rules} r ON r.id = n.ruleid
                     WHERE r.courseid = :courseid AND n.userid <> 0";
            $userlist->add_from_sql('userid', $sql, ['courseid' => $context->instanceid]);
        }

        if ($context->contextlevel === CONTEXT_SYSTEM) {
            $sql = "SELECT userid FROM {asyncwatch_global_notifications} WHERE userid <> 0";
            $userlist->add_from_sql('userid', $sql, []);
        }
    }

    /**
     * Export this user's notification history for each approved context.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int)$contextlist->get_user()->id;
        if ($userid <= 0) {
            return;
        }

        foreach ($contextlist->get_contexts() as $context) {

            if ($context->contextlevel === CONTEXT_COURSE) {
                $sql = "SELECT n.type, n.timesent, r.name AS rulename
                          FROM {asyncwatch_notifications} n
                          JOIN {asyncwatch_rules} r ON r.id = n.ruleid
                         WHERE n.userid = :userid AND r.courseid = :courseid
                      ORDER BY n.timesent ASC";
                $rows = $DB->get_records_sql($sql, ['userid' => $userid, 'courseid' => $context->instanceid]);
                self::export_notification_rows($context, $rows);
            }

            if ($context->contextlevel === CONTEXT_SYSTEM) {
                $sql = "SELECT n.type, n.timesent, r.name AS rulename
                          FROM {asyncwatch_global_notifications} n
                          JOIN {asyncwatch_global_rules} r ON r.id = n.ruleid
                         WHERE n.userid = :userid
                      ORDER BY n.timesent ASC";
                $rows = $DB->get_records_sql($sql, ['userid' => $userid]);
                self::export_notification_rows($context, $rows);
            }
        }
    }

    /**
     * Shared export writer for both notification tables.
     */
    private static function export_notification_rows(\context $context, array $rows): void {
        if (empty($rows)) {
            return;
        }
        $data = [];
        foreach ($rows as $row) {
            $data[] = (object)[
                'rule'     => $row->rulename,
                'type'     => $row->type,
                'timesent' => transform::datetime((int)$row->timesent),
            ];
        }
        writer::with_context($context)->export_data(
            [get_string('pluginname', 'local_asyncwatch')],
            (object)['notifications' => $data]
        );
    }

    /**
     * Delete ALL users' notification data in a context — used when an
     * entire course (or the whole site, for global rules) is being wiped,
     * not tied to any one subject access request.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel === CONTEXT_COURSE) {
            $ruleids = $DB->get_fieldset_select(
                'asyncwatch_rules', 'id', 'courseid = :courseid', ['courseid' => $context->instanceid]
            );
            if (!empty($ruleids)) {
                list($insql, $params) = $DB->get_in_or_equal($ruleids);
                $DB->delete_records_select('asyncwatch_notifications', "ruleid $insql AND userid <> 0", $params);
            }
        }

        if ($context->contextlevel === CONTEXT_SYSTEM) {
            $DB->delete_records_select('asyncwatch_global_notifications', 'userid <> 0');
        }
    }

    /**
     * Delete one user's notification data, for each approved context.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int)$contextlist->get_user()->id;
        if ($userid <= 0) {
            return; // Never touch the staff-digest sentinel row.
        }

        foreach ($contextlist->get_contexts() as $context) {

            if ($context->contextlevel === CONTEXT_COURSE) {
                $ruleids = $DB->get_fieldset_select(
                    'asyncwatch_rules', 'id', 'courseid = :courseid', ['courseid' => $context->instanceid]
                );
                if (!empty($ruleids)) {
                    list($insql, $params) = $DB->get_in_or_equal($ruleids);
                    $params[] = $userid;
                    $DB->delete_records_select('asyncwatch_notifications', "ruleid $insql AND userid = ?", $params);
                }
            }

            if ($context->contextlevel === CONTEXT_SYSTEM) {
                $DB->delete_records('asyncwatch_global_notifications', ['userid' => $userid]);
            }
        }
    }

    /**
     * Delete a specific set of approved users' notification data within
     * one context — the bulk-deletion counterpart to delete_data_for_user().
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        $userids = array_filter(array_map('intval', $userlist->get_userids()), fn($id) => $id > 0);
        if (empty($userids)) {
            return;
        }
        list($uinsql, $uparams) = $DB->get_in_or_equal($userids);

        if ($context->contextlevel === CONTEXT_COURSE) {
            $ruleids = $DB->get_fieldset_select(
                'asyncwatch_rules', 'id', 'courseid = :courseid', ['courseid' => $context->instanceid]
            );
            if (!empty($ruleids)) {
                list($rinsql, $rparams) = $DB->get_in_or_equal($ruleids);
                $DB->delete_records_select(
                    'asyncwatch_notifications',
                    "ruleid $rinsql AND userid $uinsql",
                    array_merge($rparams, $uparams)
                );
            }
        }

        if ($context->contextlevel === CONTEXT_SYSTEM) {
            $DB->delete_records_select('asyncwatch_global_notifications', "userid $uinsql", $uparams);
        }
    }
}