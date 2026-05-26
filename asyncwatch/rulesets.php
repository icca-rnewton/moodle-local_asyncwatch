<?php
/**
 * AsyncWatch Rule Sets management page.
 *
 * URL: /local/asyncwatch/rulesets.php?courseid=X[&action=...][&id=...]
 *
 * @package    local_asyncwatch
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use local_asyncwatch\helper;

// ── Params & auth ─────────────────────────────────────────────────────────────
$courseid = required_param('courseid', PARAM_INT);
$action   = optional_param('action',  '', PARAM_ALPHA);
$id       = optional_param('id',       0, PARAM_INT);

$course  = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login($course);
require_capability('local/asyncwatch:manage', $context);

// ── URLs ──────────────────────────────────────────────────────────────────────
$pageurl = new moodle_url('/local/asyncwatch/rulesets.php', ['courseid' => $courseid]);
$formurl = new moodle_url('/local/asyncwatch/rulesets.php', [
    'courseid' => $courseid, 'action' => $action, 'id' => $id,
]);

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('tab_rulesets', 'local_asyncwatch'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// ── Handle deletions ──────────────────────────────────────────────────────────
if ($action === 'deleteruleset' && $id && confirm_sesskey()) {
    helper::delete_rule_set($id);
    redirect($pageurl, get_string('rulesetdeleted', 'local_asyncwatch'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// Bulk delete.
if ($action === 'bulkdeleterulesets' && confirm_sesskey()) {
    $ids = optional_param_array('bulkids', [], PARAM_INT);
    foreach ($ids as $rid) {
        helper::delete_rule_set((int)$rid);
    }
    redirect($pageurl, get_string('bulkdeleted', 'local_asyncwatch', count($ids)), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// ── Data for forms ────────────────────────────────────────────────────────────
$all_rules  = helper::get_rules($courseid);
$all_groups = groups_get_all_groups($courseid);

// ── Render ────────────────────────────────────────────────────────────────────
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('tab_rulesets', 'local_asyncwatch'));

$manage_url = new moodle_url('/local/asyncwatch/manage.php', ['courseid' => $courseid]);
$tabs = [
    new tabobject('parts',    new moodle_url($manage_url, ['tab' => 'parts']),  get_string('tab_parts',    'local_asyncwatch')),
    new tabobject('rules',    new moodle_url($manage_url, ['tab' => 'rules']),  get_string('tab_rules',    'local_asyncwatch')),
    new tabobject('rulesets', $pageurl,                                          get_string('tab_rulesets', 'local_asyncwatch')),
    new tabobject('report',   new moodle_url('/local/asyncwatch/report.php',        ['courseid' => $courseid]), get_string('tab_report',   'local_asyncwatch')),
    new tabobject('notifications', new moodle_url('/local/asyncwatch/notifications.php', ['courseid' => $courseid]), get_string('tab_notifications', 'local_asyncwatch')),
];
echo $OUTPUT->tabtree($tabs, 'rulesets');

// ── Add / Edit form ───────────────────────────────────────────────────────────
if (in_array($action, ['addruleset', 'editruleset'])) {

    // Load existing data for edit.
    $edit_name     = '';
    $edit_ruleids  = [];
    $edit_groupids = [];
    if ($action === 'editruleset' && $id) {
        $rs            = helper::get_rule_set($id);
        $edit_name     = $rs->name;
        $edit_ruleids  = helper::get_ruleset_ruleids($id);
        $edit_groupids = helper::get_ruleset_groupids($id);
    }

    $heading = $action === 'addruleset'
        ? get_string('addruleset',  'local_asyncwatch')
        : get_string('editruleset', 'local_asyncwatch');
    echo $OUTPUT->heading($heading, 4);

    $form_url = new moodle_url('/local/asyncwatch/rulesets.php', [
        'courseid' => $courseid, 'action' => $action, 'id' => $id,
    ]);
    $rs_form = new \local_asyncwatch\form\ruleset_form($form_url->out(false), [
        'all_rules'  => $all_rules,
        'all_groups' => $all_groups,
    ]);

    if ($rs_form->is_cancelled()) {
        redirect($pageurl);
    }

    if ($data = $rs_form->get_data()) {
        $ruleids  = optional_param_array('ruleset_rules',  [], PARAM_INT);
        $groupids = optional_param_array('ruleset_groups', [], PARAM_INT);
        $record   = (object)['courseid' => $courseid, 'name' => trim($data->ruleset_name)];
        if (!empty($data->rulesetid)) $record->id = (int)$data->rulesetid;
        $rulesetid = helper::save_rule_set($record);
        helper::set_ruleset_rules( $rulesetid, $ruleids);
        helper::set_ruleset_groups($rulesetid, $groupids);
        redirect($pageurl, get_string('rulesetsaved', 'local_asyncwatch'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    // Pre-populate.
    $rs_form->set_data([
        'rulesetid'    => $id,
        'courseid'     => $courseid,
        'ruleset_name' => $edit_name,
    ]);

    $rs_form->display();

    // Pre-tick checkboxes via JS (they're in static HTML so set_data won't reach them).
    if (!empty($edit_ruleids) || !empty($edit_groupids)) {
        $rule_ids_js  = json_encode(array_map('intval', $edit_ruleids));
        $group_ids_js = json_encode(array_map('intval', $edit_groupids));
        echo '<script>
(function() {
    var ruleIds  = ' . $rule_ids_js . ';
    var groupIds = ' . $group_ids_js . ';
    ruleIds.forEach(function(id) {
        var cb = document.getElementById("rs_rule_" + id);
        if (cb) cb.checked = true;
    });
    groupIds.forEach(function(id) {
        var cb = document.getElementById("rs_grp_" + id);
        if (cb) cb.checked = true;
    });
})();
</script>';
    }

} else {

    // ── Rule Sets list ────────────────────────────────────────────────────────
    $rulesets = helper::get_rule_sets($courseid);

    // Introductory text.
    echo '<div class="alert alert-info">';
    echo get_string('rulesets_intro', 'local_asyncwatch');
    echo '</div>';

    if (empty($all_groups)) {
        echo $OUTPUT->notification(get_string('rulesets_nogroups_warning', 'local_asyncwatch'), 'warning');
    }

    if (!empty($rulesets)) {
        $bulk_url = new moodle_url($pageurl, ['action' => 'bulkdeleterulesets', 'sesskey' => sesskey()]);
        echo '<form method="post" action="' . $bulk_url->out(false) . '" id="rulesets-bulk-form">';

        $table = new html_table();
        $table->attributes['class'] = 'generaltable';
        $table->head = [
            html_writer::checkbox('selectall_rs', '1', false, '', ['id' => 'selectall-rs']),
            get_string('rulesetname',   'local_asyncwatch'),
            get_string('ruleset_rules', 'local_asyncwatch'),
            get_string('ruleset_groups','local_asyncwatch'),
            get_string('actions'),
        ];

        foreach ($rulesets as $rs) {
            $ruleids  = helper::get_ruleset_ruleids((int)$rs->id);
            $groupids = helper::get_ruleset_groupids((int)$rs->id);

            // Rule names.
            $rule_names = [];
            foreach ($ruleids as $rid) {
                if (isset($all_rules[$rid])) {
                    $rule_names[] = format_string($all_rules[$rid]->name);
                }
            }

            // Group names.
            $group_names = [];
            foreach ($groupids as $gid) {
                if (isset($all_groups[$gid])) {
                    $group_names[] = format_string($all_groups[$gid]->name);
                }
            }

            $edit_url   = new moodle_url($pageurl, ['action' => 'editruleset',   'id' => $rs->id]);
            $delete_url = new moodle_url($pageurl, ['action' => 'deleteruleset', 'id' => $rs->id, 'sesskey' => sesskey()]);

            $actions =
                html_writer::link($edit_url,   $OUTPUT->pix_icon('t/edit',   get_string('edit'))) . ' ' .
                html_writer::link($delete_url, $OUTPUT->pix_icon('t/delete', get_string('delete')),
                    ['onclick' => 'return confirm(' . json_encode(get_string('rulesetdeleteconfirm', 'local_asyncwatch')) . ')']);

            $table->data[] = [
                html_writer::checkbox('bulkids[]', $rs->id, false, '', ['class' => 'bulk-checkbox-rs']),
                format_string($rs->name),
                empty($rule_names)  ? html_writer::tag('em', get_string('none')) : implode(', ', $rule_names),
                empty($group_names) ? html_writer::tag('em', get_string('none')) : implode(', ', $group_names),
                $actions,
            ];
        }

        echo html_writer::table($table);
        echo '<div class="d-flex align-items-center mt-3" style="gap:0.5rem;">';
        $add_url_inner = new moodle_url($pageurl, ['action' => 'addruleset']);
        echo '<a href="' . $add_url_inner->out(false) . '" class="btn btn-primary">' . get_string('addruleset', 'local_asyncwatch') . '</a>';
        echo '<button type="submit" class="btn btn-danger" onclick="return confirm(' . json_encode(get_string('bulkdeleteconfirm', 'local_asyncwatch')) . ')">' . get_string('bulkdelete', 'local_asyncwatch') . '</button>';
        echo '</div>';
        echo '</form>';

    } else {
        echo $OUTPUT->notification(get_string('norulesets', 'local_asyncwatch'), 'info');
        $add_url = new moodle_url($pageurl, ['action' => 'addruleset']);
        echo '<div class="d-flex align-items-center mt-3" style="gap:0.5rem;">';
        echo '<a href="' . $add_url->out(false) . '" class="btn btn-primary">' . get_string('addruleset', 'local_asyncwatch') . '</a>';
        echo '</div>';
    }
}

echo '
<script>
(function() {
    var sa = document.getElementById("selectall-rs");
    if (sa) sa.addEventListener("change", function() {
        document.querySelectorAll(".bulk-checkbox-rs").forEach(function(cb) { cb.checked = sa.checked; });
    });
})();
</script>';

echo $OUTPUT->footer();
