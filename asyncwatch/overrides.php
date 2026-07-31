<?php
/**
 * AsyncWatch rule override management.
 *
 * URL: /local/asyncwatch/overrides.php?ruleid=X[&action=...][&id=...]
 *
 * @package    local_asyncwatch
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_asyncwatch\helper;
use local_asyncwatch\form\override_form;
use local_asyncwatch\form\global_override_form;

// ── Params ────────────────────────────────────────────────────────────────────
$ruleid = required_param('ruleid',  PARAM_INT);
$action = optional_param('action',  '', PARAM_ALPHA);
$id     = optional_param('id',       0, PARAM_INT);

$rule     = $DB->get_record('asyncwatch_rules', ['id' => $ruleid], '*', MUST_EXIST);
$courseid = (int)$rule->courseid;
$course   = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context  = context_course::instance($courseid);

require_login($course);
require_capability('local/asyncwatch:manage', $context);

// ── URLs ──────────────────────────────────────────────────────────────────────
$pageurl    = new moodle_url('/local/asyncwatch/overrides.php', ['ruleid' => $ruleid]);
$formurl    = new moodle_url('/local/asyncwatch/overrides.php', ['ruleid' => $ruleid, 'action' => $action, 'id' => $id]);
$manage_url = new moodle_url('/local/asyncwatch/manage.php', ['courseid' => $courseid, 'tab' => 'rules']);

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('overrides_title', 'local_asyncwatch'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// ── Handle delete ─────────────────────────────────────────────────────────────
if ($action === 'deleteoverride' && $id && confirm_sesskey()) {
    $override = $DB->get_record('asyncwatch_rule_overrides', ['id' => $id], 'id,ruleid', MUST_EXIST);
    if ((int)$override->ruleid !== (int)$rule->id) {
        throw new \moodle_exception('invalidrecord', 'error');
    }
    helper::delete_rule_override($id);
    redirect($pageurl, get_string('overridedeleted', 'local_asyncwatch'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'deletecohortoverride' && $id && confirm_sesskey()) {
    $override = $DB->get_record('asyncwatch_rule_cohort_overrides', ['id' => $id], 'id,ruleid', MUST_EXIST);
    if ((int)$override->ruleid !== (int)$rule->id) {
        throw new \moodle_exception('invalidrecord', 'error');
    }
    helper::delete_rule_cohort_override($id);
    redirect($pageurl, get_string('overridedeleted', 'local_asyncwatch'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// ── Build group/cohort lists ────────────────────────────────────────────────────
$all_groups   = groups_get_all_groups($courseid);
$group_options = [];
foreach ($all_groups as $g) {
    $group_options[$g->id] = format_string($g->name);
}

$all_cohorts_raw = helper::get_all_cohorts();
$cohort_options  = [];
foreach ($all_cohorts_raw as $ch) {
    $cohort_options[(int)$ch->id] = format_string($ch->name);
}

// ── Form (add / edit) — group override ──────────────────────────────────────────
$form = null;
if (in_array($action, ['addoverride', 'editoverride'])) {
    $form = new override_form($formurl->out(false), [
        'groups' => $group_options,
        'ruleid' => $ruleid,
    ]);

    if ($form->is_cancelled()) {
        redirect($pageurl);
    }

    if ($data = $form->get_data()) {
        $record = (object)[
            'ruleid'     => $ruleid,
            'groupid'    => (int)$data->groupid,
            'deadline'   => (int)$data->deadline,
            'warn_hours' => override_form::warn_to_minutes((array)$data),
        ];
        if (!empty($data->overrideid)) {
            $record->id = (int)$data->overrideid;
        }
        helper::save_rule_override($record);
        redirect($pageurl, get_string('overridesaved', 'local_asyncwatch'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    // Pre-populate for edit.
    if ($action === 'editoverride' && $id) {
        $ov = $DB->get_record('asyncwatch_rule_overrides', ['id' => $id], '*', MUST_EXIST);
        $warn = override_form::minutes_to_fields((int)$ov->warn_hours);
        $form->set_data(array_merge([
            'overrideid'  => $id,
            'ruleid'      => $ruleid,
            'groupid'     => $ov->groupid,
            'deadline'    => $ov->deadline,
        ], $warn));
    }
}

// ── Form (add / edit) — cohort override ─────────────────────────────────────────
// Reuses global_override_form (built for cross-course rule overrides) —
// it's generic (cohortid + deadline + warn window), just saved to a
// different table here.
$cohort_form = null;
if (in_array($action, ['addcohortoverride', 'editcohortoverride'])) {

    if (empty($cohort_options)) {
        redirect($pageurl, get_string('globalrule_nocohorts_for_override', 'local_asyncwatch'), null,
            \core\output\notification::NOTIFY_WARNING);
    }

    $cohort_form = new global_override_form($formurl->out(false), [
        'cohorts'      => $cohort_options,
        'ruleid'       => $ruleid,
        'cohort_label' => get_string('filter_cohort', 'local_asyncwatch'),
    ]);

    if ($cohort_form->is_cancelled()) {
        redirect($pageurl);
    }

    if ($data = $cohort_form->get_data()) {
        $record = (object)[
            'ruleid'     => $ruleid,
            'cohortid'   => (int)$data->cohortid,
            'deadline'   => (int)$data->deadline,
            'warn_hours' => override_form::warn_to_minutes((array)$data),
        ];
        if (!empty($data->overrideid)) {
            $record->id = (int)$data->overrideid;
        }
        helper::save_rule_cohort_override($record);
        redirect($pageurl, get_string('overridesaved', 'local_asyncwatch'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    if ($action === 'editcohortoverride' && $id) {
        $ov   = $DB->get_record('asyncwatch_rule_cohort_overrides', ['id' => $id], '*', MUST_EXIST);
        $warn = override_form::minutes_to_fields((int)$ov->warn_hours);
        $cohort_form->set_data(array_merge([
            'overrideid' => $id,
            'ruleid'     => $ruleid,
            'cohortid'   => $ov->cohortid,
            'deadline'   => $ov->deadline,
        ], $warn));
    }
}

// ── Render ────────────────────────────────────────────────────────────────────
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('overrides_title', 'local_asyncwatch') . ': ' . format_string($rule->name), 3);
echo html_writer::link($manage_url, '← ' . get_string('backtoruleslist', 'local_asyncwatch'),
    ['class' => 'btn btn-link mb-3 pl-0']);

// Default deadline info box.
$wm = (int)$rule->warn_hours;
$warn_disp = '—';
if ($wm > 0) {
    if ($wm % (7*24*60) === 0)      $warn_disp = ($wm/(7*24*60)) . ' ' . get_string('weeks');
    elseif ($wm % (24*60) === 0)    $warn_disp = ($wm/(24*60))   . ' ' . get_string('days');
    elseif ($wm % 60 === 0)         $warn_disp = ($wm/60)         . ' ' . get_string('hours');
    else                            $warn_disp = $wm               . ' ' . get_string('minutes');
}
echo '<div class="alert alert-info mb-4">';
echo '<strong>' . get_string('override_default_deadline', 'local_asyncwatch') . ':</strong> ' . userdate($rule->deadline);
echo ' &nbsp;|&nbsp; <strong>' . get_string('warn_window', 'local_asyncwatch') . ':</strong> ' . $warn_disp;
echo '<br><small class="text-muted">' . get_string('override_default_desc', 'local_asyncwatch') . '</small>';
echo '</div>';

if ($form) {
    $form->display();
} else if ($cohort_form) {
    echo $OUTPUT->heading(get_string('overrides_title_cohort', 'local_asyncwatch'), 4);
    $cohort_form->display();
} else {
    // ── Group overrides list ──────────────────────────────────────────────────
    $overrides = helper::get_rule_overrides($ruleid);

    if (!empty($overrides)) {
        $table = new html_table();
        $table->attributes['class'] = 'generaltable';
        $table->head = [
            get_string('group'),
            get_string('deadline',    'local_asyncwatch'),
            get_string('warn_window', 'local_asyncwatch'),
            get_string('actions'),
        ];

        foreach ($overrides as $ov) {
            $group_name = isset($all_groups[$ov->groupid])
                ? format_string($all_groups[$ov->groupid]->name)
                : get_string('deletedgroup', 'local_asyncwatch');

            $wm = (int)$ov->warn_hours;
            if ($wm <= 0)                    $wdisp = '—';
            elseif ($wm % (7*24*60) === 0)   $wdisp = ($wm/(7*24*60)) . ' ' . get_string('weeks');
            elseif ($wm % (24*60) === 0)     $wdisp = ($wm/(24*60))   . ' ' . get_string('days');
            elseif ($wm % 60 === 0)          $wdisp = ($wm/60)         . ' ' . get_string('hours');
            else                             $wdisp = $wm               . ' ' . get_string('minutes');

            $edit_url   = new moodle_url($pageurl, ['action' => 'editoverride',   'id' => $ov->id]);
            $delete_url = new moodle_url($pageurl, ['action' => 'deleteoverride', 'id' => $ov->id, 'sesskey' => sesskey()]);

            $actions =
                html_writer::link($edit_url,   $OUTPUT->pix_icon('t/edit',   get_string('edit'))) . ' ' .
                html_writer::link($delete_url, $OUTPUT->pix_icon('t/delete', get_string('delete')),
                    ['onclick' => 'return confirm(' . json_encode(get_string('overridedeleteconfirm', 'local_asyncwatch')) . ')']);

            $table->data[] = [$group_name, userdate($ov->deadline), $wdisp, $actions];
        }
        echo html_writer::table($table);
    } else {
        echo $OUTPUT->notification(get_string('nooverrides', 'local_asyncwatch'), 'info');
    }

    $add_url = new moodle_url($pageurl, ['action' => 'addoverride']);
    echo $OUTPUT->single_button($add_url, get_string('addoverride', 'local_asyncwatch'), 'get');

    // ── Cohort overrides list ─────────────────────────────────────────────────
    echo $OUTPUT->heading(get_string('overrides_title_cohort', 'local_asyncwatch'), 4, 'mt-5');

    $cohort_overrides = helper::get_rule_cohort_overrides($ruleid);

    if (!empty($cohort_overrides)) {
        $ctable = new html_table();
        $ctable->attributes['class'] = 'generaltable';
        $ctable->head = [
            get_string('filter_cohort', 'local_asyncwatch'),
            get_string('deadline',      'local_asyncwatch'),
            get_string('warn_window',   'local_asyncwatch'),
            get_string('actions'),
        ];

        foreach ($cohort_overrides as $ov) {
            $cohort_name = isset($cohort_options[(int)$ov->cohortid])
                ? $cohort_options[(int)$ov->cohortid]
                : get_string('deletedcohort', 'local_asyncwatch');

            $wm = (int)$ov->warn_hours;
            if ($wm <= 0)                    $wdisp = '—';
            elseif ($wm % (7*24*60) === 0)   $wdisp = ($wm/(7*24*60)) . ' ' . get_string('weeks');
            elseif ($wm % (24*60) === 0)     $wdisp = ($wm/(24*60))   . ' ' . get_string('days');
            elseif ($wm % 60 === 0)          $wdisp = ($wm/60)         . ' ' . get_string('hours');
            else                             $wdisp = $wm               . ' ' . get_string('minutes');

            $edit_url   = new moodle_url($pageurl, ['action' => 'editcohortoverride',   'id' => $ov->id]);
            $delete_url = new moodle_url($pageurl, ['action' => 'deletecohortoverride', 'id' => $ov->id, 'sesskey' => sesskey()]);

            $actions =
                html_writer::link($edit_url,   $OUTPUT->pix_icon('t/edit',   get_string('edit'))) . ' ' .
                html_writer::link($delete_url, $OUTPUT->pix_icon('t/delete', get_string('delete')),
                    ['onclick' => 'return confirm(' . json_encode(get_string('overridedeleteconfirm', 'local_asyncwatch')) . ')']);

            $ctable->data[] = [$cohort_name, userdate($ov->deadline), $wdisp, $actions];
        }
        echo html_writer::table($ctable);
    } else {
        echo $OUTPUT->notification(get_string('nooverrides', 'local_asyncwatch'), 'info');
    }

    $add_cohort_url = new moodle_url($pageurl, ['action' => 'addcohortoverride']);
    echo $OUTPUT->single_button($add_cohort_url, get_string('addoverride_cohort', 'local_asyncwatch'), 'get');
}

echo $OUTPUT->footer();