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

    return true;
}
