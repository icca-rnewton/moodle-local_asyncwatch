<?php
/**
 * AsyncWatch upgrade steps.
 *
 * @package    local_asyncwatch
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_local_asyncwatch_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026051500) {

        // 1. Add notify_enabled to asyncwatch_rules.
        $table = new xmldb_table('asyncwatch_rules');
        $field = new xmldb_field(
            'notify_enabled', XMLDB_TYPE_INTEGER, '1', null,
            XMLDB_NOTNULL, null, '0', 'enabled'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // 2. Create asyncwatch_ntpl.
        $table = new xmldb_table('asyncwatch_ntpl');
        $table->add_field('id',               XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('courseid',         XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL);
        $table->add_field('learner_subject',  XMLDB_TYPE_CHAR,    '255', null, false, null, '');
        $table->add_field('learner_body',     XMLDB_TYPE_TEXT,    null,  null, false);
        $table->add_field('staff_subject',    XMLDB_TYPE_CHAR,    '255', null, false, null, '');
        $table->add_field('staff_body',       XMLDB_TYPE_TEXT,    null,  null, false);
        $table->add_field('staff_recipients', XMLDB_TYPE_TEXT,    null,  null, false);
        $table->add_field('timecreated',      XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified',     XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026051500, 'local', 'asyncwatch');
    }

    if ($oldversion < 2026051600) {

        // Replace notify_enabled with 4 granular columns on asyncwatch_rules.
        $table = new xmldb_table('asyncwatch_rules');
        $fields = [
            new xmldb_field('notify_learner_breach',  XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'notify_enabled'),
            new xmldb_field('notify_staff_breach',    XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'notify_learner_breach'),
            new xmldb_field('notify_learner_warning', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'notify_staff_breach'),
            new xmldb_field('notify_staff_warning',   XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'notify_learner_warning'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Migrate existing notify_enabled = 1 rows to notify_learner_breach + notify_staff_breach.
        $DB->execute("UPDATE {asyncwatch_rules} SET notify_learner_breach = notify_enabled, notify_staff_breach = notify_enabled");

        // Add warning template fields to asyncwatch_ntpl.
        $tpl_table = new xmldb_table('asyncwatch_ntpl');
        $tpl_fields = [
            new xmldb_field('learner_warning_subject', XMLDB_TYPE_CHAR, '255', null, false, null, '', 'staff_body'),
            new xmldb_field('learner_warning_body',    XMLDB_TYPE_TEXT, null,  null, false, null, null, 'learner_warning_subject'),
            new xmldb_field('staff_warning_subject',   XMLDB_TYPE_CHAR, '255', null, false, null, '', 'learner_warning_body'),
            new xmldb_field('staff_warning_body',      XMLDB_TYPE_TEXT, null,  null, false, null, null, 'staff_warning_subject'),
        ];
        foreach ($tpl_fields as $field) {
            if (!$dbman->field_exists($tpl_table, $field)) {
                $dbman->add_field($tpl_table, $field);
            }
        }

        upgrade_plugin_savepoint(true, 2026051600, 'local', 'asyncwatch');
    }

    if ($oldversion < 2026051700) {
        // Rename warn_hours semantics to warn_minutes.
        // Multiply all existing values by 60 so stored unit is now minutes.
        $DB->execute("UPDATE {asyncwatch_rules} SET warn_hours = warn_hours * 60 WHERE warn_hours > 0");
        upgrade_plugin_savepoint(true, 2026051700, 'local', 'asyncwatch');
    }

    if ($oldversion < 2026051800) {

        // asyncwatch_rule_sets — named sets per course.
        $table = new xmldb_table('asyncwatch_rule_sets');
        $table->add_field('id',           XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('courseid',     XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('name',         XMLDB_TYPE_CHAR,   '255', null, XMLDB_NOTNULL);
        $table->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // asyncwatch_ruleset_rules — which rules belong to which set.
        $table = new xmldb_table('asyncwatch_ruleset_rules');
        $table->add_field('id',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('rulesetid',XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('ruleid',   XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // asyncwatch_ruleset_groups — which course groups a set applies to.
        $table = new xmldb_table('asyncwatch_ruleset_groups');
        $table->add_field('id',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('rulesetid',XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('groupid',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026051800, 'local', 'asyncwatch');
    }

    if ($oldversion < 2026051900) {

        // asyncwatch_rule_overrides — per-group deadline overrides for a rule.
        $table = new xmldb_table('asyncwatch_rule_overrides');
        $table->add_field('id',           XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('ruleid',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('groupid',      XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('deadline',     XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('warn_hours',   XMLDB_TYPE_INTEGER, '10', null, false, null, '0');
        $table->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026051900, 'local', 'asyncwatch');
    }

    if ($oldversion < 2026052000) {
        // Add is_auto flag to asyncwatch_parts.
        $table = new xmldb_table('asyncwatch_parts');
        $field = new xmldb_field('is_auto', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'sortorder');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026052000, 'local', 'asyncwatch');
    }


    if ($oldversion < 2026061000) {
        $dbman = $DB->get_manager();

        // 1. Add UNIQUE index on asyncwatch_ruleset_rules(ruleid) —
        //    a rule can belong to at most one rule set.
        $table = new xmldb_table('asyncwatch_ruleset_rules');
        $index = new xmldb_index('uniq_ruleid', XMLDB_INDEX_UNIQUE, ['ruleid']);
        if (!$dbman->index_exists($table, $index)) {
            // Remove any duplicate rows first (keep lowest id per ruleid).
            $DB->execute("
                DELETE FROM {asyncwatch_ruleset_rules}
                 WHERE id NOT IN (
                     SELECT MIN(id) FROM {asyncwatch_ruleset_rules} GROUP BY ruleid
                 )
            ");
            $dbman->add_index($table, $index);
        }
        $index2 = new xmldb_index('uniq_rulesetid_ruleid', XMLDB_INDEX_UNIQUE, ['rulesetid', 'ruleid']);
        if (!$dbman->index_exists($table, $index2)) {
            $dbman->add_index($table, $index2);
        }

        // 2. Add UNIQUE index on asyncwatch_ruleset_groups(rulesetid, groupid).
        $table = new xmldb_table('asyncwatch_ruleset_groups');
        $index = new xmldb_index('uniq_rulesetid_groupid', XMLDB_INDEX_UNIQUE, ['rulesetid', 'groupid']);
        if (!$dbman->index_exists($table, $index)) {
            $DB->execute("
                DELETE FROM {asyncwatch_ruleset_groups}
                 WHERE id NOT IN (
                     SELECT MIN(id) FROM {asyncwatch_ruleset_groups} GROUP BY rulesetid, groupid
                 )
            ");
            $dbman->add_index($table, $index);
        }

        // 3. Make asyncwatch_notifications(ruleid, userid, type) UNIQUE.
        $table = new xmldb_table('asyncwatch_notifications');
        $old_index = new xmldb_index('idx_rule_user_type', XMLDB_INDEX_NOTUNIQUE, ['ruleid', 'userid', 'type']);
        $new_index = new xmldb_index('idx_rule_user_type', XMLDB_INDEX_UNIQUE,    ['ruleid', 'userid', 'type']);
        if ($dbman->index_exists($table, $old_index)) {
            // Remove duplicate notification rows first (keep most recent).
            $DB->execute("
                DELETE FROM {asyncwatch_notifications}
                 WHERE id NOT IN (
                     SELECT MAX(id) FROM {asyncwatch_notifications} GROUP BY ruleid, userid, type
                 )
            ");
            $dbman->drop_index($table, $old_index);
            $dbman->add_index($table, $new_index);
        }

        // 4. Add UNIQUE index on asyncwatch_ntpl(courseid) — one row per course.
        $table = new xmldb_table('asyncwatch_ntpl');
        $index = new xmldb_index('uniq_courseid', XMLDB_INDEX_UNIQUE, ['courseid']);
        if (!$dbman->index_exists($table, $index)) {
            $DB->execute("
                DELETE FROM {asyncwatch_ntpl}
                 WHERE id NOT IN (
                     SELECT MIN(id) FROM {asyncwatch_ntpl} GROUP BY courseid
                 )
            ");
            $dbman->add_index($table, $index);
        }

        // 5. Drop legacy notify_enabled column from asyncwatch_rules.
        $table = new xmldb_table('asyncwatch_rules');
        $field = new xmldb_field('notify_enabled');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026061000, 'local', 'asyncwatch');
    }

    if ($oldversion < 2026072300) {

        // Cross-course rules — new tables, additive only. Kept fully
        // separate from asyncwatch_rules/asyncwatch_notifications to avoid
        // id collisions between the two rule tables. Parts stay per-course
        // and are untouched by this.

        // 1. asyncwatch_global_rules
        $table = new xmldb_table('asyncwatch_global_rules');
        $table->add_field('id',                    XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('name',                   XMLDB_TYPE_CHAR,    '255', null, XMLDB_NOTNULL);
        $table->add_field('parts_required',          XMLDB_TYPE_INTEGER, '6',   null, XMLDB_NOTNULL);
        $table->add_field('deadline',                XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL);
        $table->add_field('warn_hours',               XMLDB_TYPE_INTEGER, '6',   null, false, null, '0');
        $table->add_field('enabled',                  XMLDB_TYPE_INTEGER, '1',   null, XMLDB_NOTNULL, null, '1');
        $table->add_field('notify_learner_breach',    XMLDB_TYPE_INTEGER, '1',   null, XMLDB_NOTNULL, null, '0');
        $table->add_field('notify_staff_breach',      XMLDB_TYPE_INTEGER, '1',   null, XMLDB_NOTNULL, null, '0');
        $table->add_field('notify_learner_warning',   XMLDB_TYPE_INTEGER, '1',   null, XMLDB_NOTNULL, null, '0');
        $table->add_field('notify_staff_warning',     XMLDB_TYPE_INTEGER, '1',   null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated',              XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified',             XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // 2. asyncwatch_global_rule_courses
        $table = new xmldb_table('asyncwatch_global_rule_courses');
        $table->add_field('id',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('ruleid',   XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        $index = new xmldb_index('uniq_rule_course', XMLDB_INDEX_UNIQUE, ['ruleid', 'courseid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // 3. asyncwatch_global_rule_cohorts
        $table = new xmldb_table('asyncwatch_global_rule_cohorts');
        $table->add_field('id',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('ruleid',   XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('cohortid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        $index = new xmldb_index('uniq_rule_cohort', XMLDB_INDEX_UNIQUE, ['ruleid', 'cohortid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // 4. asyncwatch_global_rule_overrides
        $table = new xmldb_table('asyncwatch_global_rule_overrides');
        $table->add_field('id',           XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('ruleid',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('cohortid',     XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('deadline',     XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('warn_hours',   XMLDB_TYPE_INTEGER, '10', null, false, null, '0');
        $table->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        $index = new xmldb_index('uniq_rule_cohort_ov', XMLDB_INDEX_UNIQUE, ['ruleid', 'cohortid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // 5. asyncwatch_global_notifications — separate send-log so ids
        //    never collide with asyncwatch_notifications' ruleid space.
        $table = new xmldb_table('asyncwatch_global_notifications');
        $table->add_field('id',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('ruleid',   XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('userid',   XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('type',     XMLDB_TYPE_CHAR,    '20', null, XMLDB_NOTNULL);
        $table->add_field('timesent', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        $index = new xmldb_index('uniq_rule_user_type', XMLDB_INDEX_UNIQUE, ['ruleid', 'userid', 'type']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026072300, 'local', 'asyncwatch');
    }

    if ($oldversion < 2026072309) {

        // Cohort targeting for course-level rules — parallel to the
        // existing group tables. Additive only.

        // 1. asyncwatch_ruleset_cohorts
        $table = new xmldb_table('asyncwatch_ruleset_cohorts');
        $table->add_field('id',        XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('rulesetid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('cohortid',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        $index = new xmldb_index('uniq_rulesetid_cohortid', XMLDB_INDEX_UNIQUE, ['rulesetid', 'cohortid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // 2. asyncwatch_rule_cohort_overrides
        $table = new xmldb_table('asyncwatch_rule_cohort_overrides');
        $table->add_field('id',           XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('ruleid',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('cohortid',     XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('deadline',     XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('warn_hours',   XMLDB_TYPE_INTEGER, '10', null, false, null, '0');
        $table->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        $index = new xmldb_index('uniq_ruleid_cohortid', XMLDB_INDEX_UNIQUE, ['ruleid', 'cohortid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026072309, 'local', 'asyncwatch');
    }

    if ($oldversion < 2026072401) {

        // Profile field sync — a rule can optionally write its computed
        // status (On track / At risk / Behind / Completed) into a user
        // profile custom field. Additive only, same shape on both rule
        // tables.

        $table = new xmldb_table('asyncwatch_rules');
        $field = new xmldb_field(
            'profilefield', XMLDB_TYPE_CHAR, '100', null,
            XMLDB_NOTNULL, null, '', 'notify_staff_warning'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('asyncwatch_global_rules');
        $field = new xmldb_field(
            'profilefield', XMLDB_TYPE_CHAR, '100', null,
            XMLDB_NOTNULL, null, '', 'notify_staff_warning'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026072401, 'local', 'asyncwatch');
    }

    if ($oldversion < 2026072500) {

        // Rule Sets retired — restriction moves inline onto the rule
        // itself (matching how cross-course rules already work), rather
        // than through a separate named-set object. Migrate any existing
        // set-assignment data across before dropping the old tables.

        // 1. New tables.
        $table = new xmldb_table('asyncwatch_rule_restrict_groups');
        $table->add_field('id',      XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('ruleid',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        $index = new xmldb_index('uniq_ruleid_groupid', XMLDB_INDEX_UNIQUE, ['ruleid', 'groupid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        $table = new xmldb_table('asyncwatch_rule_restrict_cohorts');
        $table->add_field('id',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('ruleid',   XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('cohortid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        $index = new xmldb_index('uniq_ruleid_cohortid', XMLDB_INDEX_UNIQUE, ['ruleid', 'cohortid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // 2. Migrate: for every rule that was assigned to a Rule Set, copy
        //    that set's groups/cohorts directly onto the rule.
        $old_rulesets_table = new xmldb_table('asyncwatch_ruleset_rules');
        if ($dbman->table_exists($old_rulesets_table)) {
            $assignments = $DB->get_records('asyncwatch_ruleset_rules');
            foreach ($assignments as $a) {
                $groupids = $DB->get_records('asyncwatch_ruleset_groups', ['rulesetid' => $a->rulesetid], '', 'groupid');
                foreach ($groupids as $g) {
                    if (!$DB->record_exists('asyncwatch_rule_restrict_groups', ['ruleid' => $a->ruleid, 'groupid' => $g->groupid])) {
                        $DB->insert_record('asyncwatch_rule_restrict_groups', (object)[
                            'ruleid' => $a->ruleid, 'groupid' => $g->groupid,
                        ]);
                    }
                }
                $cohortids = $DB->get_records('asyncwatch_ruleset_cohorts', ['rulesetid' => $a->rulesetid], '', 'cohortid');
                foreach ($cohortids as $c) {
                    if (!$DB->record_exists('asyncwatch_rule_restrict_cohorts', ['ruleid' => $a->ruleid, 'cohortid' => $c->cohortid])) {
                        $DB->insert_record('asyncwatch_rule_restrict_cohorts', (object)[
                            'ruleid' => $a->ruleid, 'cohortid' => $c->cohortid,
                        ]);
                    }
                }
            }
        }

        // 3. Drop the old Rule Set tables — order matters (children first).
        $drop_tables = [
            'asyncwatch_ruleset_cohorts',
            'asyncwatch_ruleset_groups',
            'asyncwatch_ruleset_rules',
            'asyncwatch_rule_sets',
        ];
        foreach ($drop_tables as $tname) {
            $t = new xmldb_table($tname);
            if ($dbman->table_exists($t)) {
                $dbman->drop_table($t);
            }
        }

        upgrade_plugin_savepoint(true, 2026072500, 'local', 'asyncwatch');
    }

    if ($oldversion < 2026072600) {

        // Close a schema gap: asyncwatch_rule_overrides (group overrides)
        // never got the unique (ruleid, groupid) constraint that its
        // cohort and global siblings already have. Nothing currently stops
        // duplicate override rows for the same group on the same rule, so
        // de-duplicate first (keep the most lenient/latest deadline, same
        // "best deadline wins" rule used everywhere else in this plugin)
        // before the index is added, or a site with an existing duplicate
        // would fail the upgrade outright.

        $duplicate_groups = $DB->get_records_sql(
            "SELECT ruleid, groupid, COUNT(*) AS cnt
               FROM {asyncwatch_rule_overrides}
              GROUP BY ruleid, groupid
             HAVING COUNT(*) > 1"
        );

        foreach ($duplicate_groups as $dup) {
            $rows = $DB->get_records('asyncwatch_rule_overrides',
                ['ruleid' => $dup->ruleid, 'groupid' => $dup->groupid], 'deadline DESC');
            $keep = array_shift($rows); // Latest deadline wins — keep it.
            foreach ($rows as $extra) {
                $DB->delete_records('asyncwatch_rule_overrides', ['id' => $extra->id]);
            }
        }

        $table = new xmldb_table('asyncwatch_rule_overrides');
        $index = new xmldb_index('uniq_ruleid_groupid', XMLDB_INDEX_UNIQUE, ['ruleid', 'groupid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026072600, 'local', 'asyncwatch');
    }

    return true;
}