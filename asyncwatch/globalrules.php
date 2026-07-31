<?php
/**
 * AsyncWatch Cross-course Rules management page.
 *
 * Lives outside any course context — registered as an admin external page
 * (see settings.php) and gated by the local/asyncwatch:manageglobal
 * capability, which has no default role (site admins only).
 *
 * URL: /local/asyncwatch/globalrules.php[?action=...][&id=...]
 *
 * @package    local_asyncwatch
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_asyncwatch\helper;
use local_asyncwatch\form\global_rule_form;
use local_asyncwatch\form\global_recipients_form;
use local_asyncwatch\form\rule_form;

// ── Auth / page setup ───────────────────────────────────────────────────────
// Handles require_login(), the capability check, $PAGE->context/url, and
// breadcrumbs, based on the admin_externalpage entry registered in
// settings.php — no manual require_capability() needed here.
admin_externalpage_setup('local_asyncwatch_globalrules');

$action = optional_param('action', '', PARAM_ALPHA);
$id     = optional_param('id',     0,  PARAM_INT);

$pageurl = new moodle_url('/local/asyncwatch/globalrules.php');
$formurl = new moodle_url('/local/asyncwatch/globalrules.php', ['action' => $action, 'id' => $id]);

// ── Handle deletions ─────────────────────────────────────────────────────────
if ($action === 'delete' && $id && confirm_sesskey()) {
    helper::delete_global_rule($id);
    redirect($pageurl, get_string('globalruledeleted', 'local_asyncwatch'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'bulkdelete' && confirm_sesskey()) {
    $ids = optional_param_array('bulkids', [], PARAM_INT);
    foreach ($ids as $rid) {
        helper::delete_global_rule((int)$rid);
    }
    redirect($pageurl, get_string('bulkdeleted', 'local_asyncwatch', count($ids)), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// ── Staff recipients (site-wide, shared by every cross-course rule) ────────
$recipients_form = null;
if ($action === 'recipients') {
    $all_users = $DB->get_records_sql(
        "SELECT id, firstname, lastname, email,
                firstnamephonetic, lastnamephonetic, middlename, alternatename
           FROM {user}
          WHERE deleted = 0 AND suspended = 0 AND id != :guestid
          ORDER BY lastname ASC, firstname ASC",
        ['guestid' => $CFG->siteguest ?? 1]
    );
    $user_options = [];
    foreach ($all_users as $u) {
        $user_options[$u->id] = fullname($u) . ' (' . $u->email . ')';
    }

    $recipients_form = new global_recipients_form($formurl->out(false), ['users' => $user_options]);

    if ($recipients_form->is_cancelled()) {
        redirect($pageurl);
    }

    if ($data = $recipients_form->get_data()) {
        helper::set_global_staff_recipient_ids((array)($data->staff_recipients_ids ?? []));
        redirect($pageurl, get_string('globalrule_recipients_saved', 'local_asyncwatch'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    $recipients_form->set_data([
        'staff_recipients_ids' => helper::get_global_staff_recipient_ids(),
    ]);
}

// ── Data shared by the form and the list ────────────────────────────────────
$courses_with_parts = helper::get_courses_with_parts();
$all_cohorts_raw     = helper::get_all_cohorts();
$cohort_options      = [];
foreach ($all_cohorts_raw as $ch) {
    $label = format_string($ch->name);
    if (!empty($ch->idnumber)) {
        $label .= ' (' . s($ch->idnumber) . ')';
    }
    $cohort_options[(int)$ch->id] = $label;
}

// ── Add / Edit form ──────────────────────────────────────────────────────────
$form = null;
if (in_array($action, ['add', 'edit'])) {
    $form = new global_rule_form($formurl->out(false), [
        'ruleid'                => $id,
        'courses_with_parts'    => $courses_with_parts,
        'cohorts'               => $cohort_options,
        'profile_field_options' => helper::get_profile_field_options(),
    ]);

    if ($form->is_cancelled()) {
        redirect($pageurl);
    }

    if ($formdata = $form->get_data()) {
        $record = (object)[
            'name'                   => trim($formdata->name),
            'parts_required'         => (int)$formdata->parts_required,
            'deadline'               => (int)$formdata->deadline,
            'warn_hours'             => rule_form::warn_to_hours((array)$formdata),
            'enabled'                => (int)$formdata->enabled,
            'notify_learner_breach'  => (int)($formdata->notify_learner_breach  ?? 0),
            'notify_staff_breach'    => (int)($formdata->notify_staff_breach    ?? 0),
            'notify_learner_warning' => (int)($formdata->notify_learner_warning ?? 0),
            'notify_staff_warning'   => (int)($formdata->notify_staff_warning   ?? 0),
            'profilefield'           => trim($formdata->profilefield ?? ''),
        ];
        if ($id) {
            $record->id = $id;
        }
        $ruleid = helper::save_global_rule($record);

        $courseids = array_map('intval', (array)($formdata->courseids ?? []));
        helper::set_global_rule_courses($ruleid, $courseids);

        $cohortids = array_map('intval', (array)($formdata->cohortids ?? []));
        helper::set_global_rule_cohorts($ruleid, $cohortids);

        redirect($pageurl, get_string('globalrulesaved', 'local_asyncwatch'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    if ($action === 'edit' && $id) {
        $rule        = helper::get_global_rule($id);
        $warn_fields = rule_form::hours_to_warn_fields((int)$rule->warn_hours);
        $form->set_data([
            'ruleid'                 => $id,
            'name'                   => $rule->name,
            'enabled'                => $rule->enabled,
            'courseids'              => helper::get_global_rule_courseids($id),
            'cohortids'              => helper::get_global_rule_cohortids($id),
            'parts_required'         => $rule->parts_required,
            'deadline'               => $rule->deadline,
            'notify_learner_breach'  => $rule->notify_learner_breach  ?? 0,
            'notify_staff_breach'    => $rule->notify_staff_breach    ?? 0,
            'notify_learner_warning' => $rule->notify_learner_warning ?? 0,
            'notify_staff_warning'   => $rule->notify_staff_warning   ?? 0,
            'warn_enabled'           => $warn_fields['warn_enabled'],
            'warn_value'             => $warn_fields['warn_value'],
            'warn_unit'              => $warn_fields['warn_unit'],
            'profilefield'           => $rule->profilefield ?? '',
        ]);
    }
}

// ── Render ────────────────────────────────────────────────────────────────────
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('globalrules', 'local_asyncwatch'));

$tabs = [
    new tabobject('globalrules', new moodle_url('/local/asyncwatch/globalrules.php'), get_string('globalrules', 'local_asyncwatch')),
    new tabobject('globalreport', new moodle_url('/local/asyncwatch/globalreport.php'), get_string('globalreport', 'local_asyncwatch')),
];
echo $OUTPUT->tabtree($tabs, 'globalrules');

if ($form) {

    $heading = $action === 'add'
        ? get_string('addglobalrule',  'local_asyncwatch')
        : get_string('editglobalrule', 'local_asyncwatch');
    echo $OUTPUT->heading($heading, 4);

    $form->display();

} else if ($recipients_form) {

    echo $OUTPUT->heading(get_string('globalrule_recipients', 'local_asyncwatch'), 4);
    $recipients_form->display();

} else {

    echo '<div class="alert alert-info">' . get_string('globalrules_intro', 'local_asyncwatch') . '</div>';

    $recipient_count = count(helper::get_global_staff_recipient_ids());
    $recipients_url  = new moodle_url($pageurl, ['action' => 'recipients']);
    echo '<div class="mb-3">'
       . html_writer::link($recipients_url,
           get_string('globalrule_recipients', 'local_asyncwatch') . ' (' . $recipient_count . ')')
       . '</div>';

    $rules = helper::get_global_rules();

    if (!empty($rules)) {
        $bulk_url = new moodle_url($pageurl, ['action' => 'bulkdelete', 'sesskey' => sesskey()]);
        echo '<form method="post" action="' . $bulk_url->out(false) . '" id="globalrules-bulk-form">';

        $table = new html_table();
        $table->attributes['class'] = 'generaltable';
        $table->head = [
            html_writer::checkbox('selectall_gr', '1', false, '', ['id' => 'selectall-gr']),
            get_string('rulename',              'local_asyncwatch'),
            get_string('globalrule_col_courses','local_asyncwatch'),
            get_string('globalrule_col_cohorts','local_asyncwatch'),
            get_string('parts_required',        'local_asyncwatch'),
            get_string('default_deadline',      'local_asyncwatch'),
            get_string('rule_enabled',          'local_asyncwatch'),
            get_string('notify_breach_col',     'local_asyncwatch'),
            get_string('notify_warning_col',    'local_asyncwatch'),
            get_string('actions'),
        ];

        foreach ($rules as $rule) {
            $courseids = helper::get_global_rule_courseids((int)$rule->id);
            $course_names = [];
            $total_parts  = 0;
            foreach ($courseids as $cid) {
                if (isset($courses_with_parts[$cid])) {
                    $course_names[] = format_string($courses_with_parts[$cid]->coursename);
                    $total_parts   += (int)$courses_with_parts[$cid]->partcount;
                }
            }

            $cohortids = helper::get_global_rule_cohortids((int)$rule->id);
            $cohort_names = [];
            foreach ($cohortids as $chid) {
                if (isset($cohort_options[$chid])) {
                    $cohort_names[] = $cohort_options[$chid];
                }
            }

            $edit_url   = new moodle_url($pageurl, ['action' => 'edit',   'id' => $rule->id]);
            $delete_url = new moodle_url($pageurl, ['action' => 'delete', 'id' => $rule->id, 'sesskey' => sesskey()]);

            $override_url   = new moodle_url('/local/asyncwatch/globaloverrides.php', ['ruleid' => $rule->id]);
            $override_count = $DB->count_records('asyncwatch_global_rule_overrides', ['ruleid' => $rule->id]);
            $override_badge = $override_count > 0
                ? ' ' . html_writer::tag('span', $override_count, ['class' => 'badge badge-info bg-info text-white', 'style' => 'font-size:0.95em;', 'title' => get_string('overrides_count_cohort', 'local_asyncwatch', $override_count)])
                : '';

            $actions =
                html_writer::link($edit_url,     $OUTPUT->pix_icon('t/edit',   get_string('edit'))) . ' ' .
                html_writer::link($override_url, $OUTPUT->pix_icon('t/groups', get_string('globalrule_overrides_link', 'local_asyncwatch')) . $override_badge,
                    ['title' => get_string('globalrule_overrides_link', 'local_asyncwatch')]) . ' ' .
                html_writer::link($delete_url,   $OUTPUT->pix_icon('t/delete', get_string('delete')),
                    ['onclick' => 'return confirm(' . json_encode(get_string('globalruledeleteconfirm', 'local_asyncwatch')) . ')']);

            $breach_cell  = ($rule->notify_learner_breach  ? get_string('notify_learner_short', 'local_asyncwatch') : '—')
                          . ' / '
                          . ($rule->notify_staff_breach    ? get_string('notify_staff_short',   'local_asyncwatch') : '—');
            $warning_cell = ($rule->notify_learner_warning ? get_string('notify_learner_short', 'local_asyncwatch') : '—')
                          . ' / '
                          . ($rule->notify_staff_warning   ? get_string('notify_staff_short',   'local_asyncwatch') : '—');

            $table->data[] = [
                html_writer::checkbox('bulkids[]', $rule->id, false, '', ['class' => 'bulk-checkbox-gr']),
                format_string($rule->name),
                empty($course_names) ? html_writer::tag('em', get_string('none')) : implode(', ', $course_names),
                empty($cohort_names) ? html_writer::tag('em', get_string('none')) : implode(', ', $cohort_names),
                $rule->parts_required . ' / ' . $total_parts,
                userdate($rule->deadline, get_string('aw_datetimefmt', 'local_asyncwatch')),
                $rule->enabled ? '✓' : '✗',
                $breach_cell,
                $warning_cell,
                $actions,
            ];
        }

        echo html_writer::table($table);
        echo '<div class="d-flex align-items-center mt-3" style="gap:0.5rem;">';
        $add_url = new moodle_url($pageurl, ['action' => 'add']);
        echo '<a href="' . $add_url->out(false) . '" class="btn btn-primary">' . get_string('addglobalrule', 'local_asyncwatch') . '</a>';
        echo '<button type="submit" class="btn btn-danger" onclick="return confirm(' . json_encode(get_string('bulkdeleteconfirm', 'local_asyncwatch')) . ')">' . get_string('bulkdelete', 'local_asyncwatch') . '</button>';
        echo '</div>';
        echo '</form>';

    } else {
        echo $OUTPUT->notification(get_string('noglobalrules', 'local_asyncwatch'), 'info');
        $add_url = new moodle_url($pageurl, ['action' => 'add']);
        echo '<div class="d-flex align-items-center mt-3" style="gap:0.5rem;">';
        echo '<a href="' . $add_url->out(false) . '" class="btn btn-primary">' . get_string('addglobalrule', 'local_asyncwatch') . '</a>';
        echo '</div>';
    }
}

echo '
<script>
(function() {
    var sa = document.getElementById("selectall-gr");
    if (sa) sa.addEventListener("change", function() {
        document.querySelectorAll(".bulk-checkbox-gr").forEach(function(cb) { cb.checked = sa.checked; });
    });
})();
</script>';

echo $OUTPUT->footer();