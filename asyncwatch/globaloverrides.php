<?php
/**
 * AsyncWatch cross-course rule cohort override management.
 *
 * Mirrors overrides.php (per-course, group-based) — reached via a link on
 * globalrules.php rather than being its own admin-tree entry, same as the
 * per-course version is reached via a link on manage.php rather than being
 * its own course-nav tab.
 *
 * URL: /local/asyncwatch/globaloverrides.php?ruleid=X[&action=...][&id=...]
 *
 * @package    local_asyncwatch
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_asyncwatch\helper;
use local_asyncwatch\form\global_override_form;
use local_asyncwatch\form\override_form;

// ── Params & auth ─────────────────────────────────────────────────────────────
$ruleid = required_param('ruleid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$id     = optional_param('id',     0,  PARAM_INT);

$rule    = helper::get_global_rule($ruleid);
$context = context_system::instance();

require_login();
require_capability('local/asyncwatch:manageglobal', $context);

// ── URLs ──────────────────────────────────────────────────────────────────────
$pageurl    = new moodle_url('/local/asyncwatch/globaloverrides.php', ['ruleid' => $ruleid]);
$formurl    = new moodle_url('/local/asyncwatch/globaloverrides.php', ['ruleid' => $ruleid, 'action' => $action, 'id' => $id]);
$rules_url  = new moodle_url('/local/asyncwatch/globalrules.php');

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('overrides_title_cohort', 'local_asyncwatch'));
$PAGE->set_heading(get_string('globalrules', 'local_asyncwatch'));
$PAGE->set_pagelayout('admin');

// ── Handle delete ─────────────────────────────────────────────────────────────
if ($action === 'deleteoverride' && $id && confirm_sesskey()) {
    $override = $DB->get_record('asyncwatch_global_rule_overrides', ['id' => $id], 'id,ruleid', MUST_EXIST);
    if ((int)$override->ruleid !== (int)$rule->id) {
        throw new \moodle_exception('invalidrecord', 'error');
    }
    helper::delete_global_rule_override($id);
    redirect($pageurl, get_string('overridedeleted', 'local_asyncwatch'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// ── Build cohort list — only cohorts this rule actually targets, since an
//    override for a cohort outside the rule's targeting would never match
//    anyone (or, if the rule has no cohort targeting, would silently narrow
//    it) ─────────────────────────────────────────────────────────────────────
$rule_cohortids = helper::get_global_rule_cohortids($ruleid);
$all_cohorts_raw = helper::get_all_cohorts();
$cohort_options  = [];
foreach ($all_cohorts_raw as $ch) {
    if (!empty($rule_cohortids) && !in_array((int)$ch->id, $rule_cohortids)) {
        continue;
    }
    $cohort_options[(int)$ch->id] = format_string($ch->name);
}

// ── Form (add / edit) ─────────────────────────────────────────────────────────
$form = null;
if (in_array($action, ['addoverride', 'editoverride'])) {

    if (empty($cohort_options)) {
        // No cohorts to override against — send them back rather than show
        // a form with nothing to pick.
        redirect($pageurl, get_string('globalrule_nocohorts_for_override', 'local_asyncwatch'), null,
            \core\output\notification::NOTIFY_WARNING);
    }

    $form = new global_override_form($formurl->out(false), [
        'cohorts' => $cohort_options,
        'ruleid'  => $ruleid,
    ]);

    if ($form->is_cancelled()) {
        redirect($pageurl);
    }

    if ($data = $form->get_data()) {
        $record = (object)[
            'ruleid'     => $ruleid,
            'cohortid'   => (int)$data->cohortid,
            'deadline'   => (int)$data->deadline,
            'warn_hours' => override_form::warn_to_minutes((array)$data),
        ];
        if (!empty($data->overrideid)) {
            $record->id = (int)$data->overrideid;
        }
        helper::save_global_rule_override($record);
        redirect($pageurl, get_string('overridesaved', 'local_asyncwatch'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    }

    if ($action === 'editoverride' && $id) {
        $ov   = $DB->get_record('asyncwatch_global_rule_overrides', ['id' => $id], '*', MUST_EXIST);
        $warn = override_form::minutes_to_fields((int)$ov->warn_hours);
        $form->set_data(array_merge([
            'overrideid' => $id,
            'ruleid'     => $ruleid,
            'cohortid'   => $ov->cohortid,
            'deadline'   => $ov->deadline,
        ], $warn));
    }
}

// ── Render ────────────────────────────────────────────────────────────────────
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('overrides_title_cohort', 'local_asyncwatch') . ': ' . format_string($rule->name), 3);
echo html_writer::link($rules_url, '← ' . get_string('globalrules', 'local_asyncwatch'),
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
echo '<br><small class="text-muted">' . get_string('override_default_desc_cohort', 'local_asyncwatch') . '</small>';
if (empty($rule_cohortids)) {
    echo '<br><small class="text-muted">' . get_string('globalrule_override_anycohort_note', 'local_asyncwatch') . '</small>';
}
echo '</div>';

if ($form) {
    $form->display();
} else {
    // ── Overrides list ────────────────────────────────────────────────────────
    $overrides = helper::get_global_rule_overrides($ruleid);
    $all_cohort_names = [];
    foreach ($all_cohorts_raw as $ch) {
        $all_cohort_names[(int)$ch->id] = format_string($ch->name);
    }

    if (!empty($overrides)) {
        $table = new html_table();
        $table->attributes['class'] = 'generaltable';
        $table->head = [
            get_string('globalrule_cohorts', 'local_asyncwatch'),
            get_string('deadline',    'local_asyncwatch'),
            get_string('warn_window', 'local_asyncwatch'),
            get_string('actions'),
        ];

        foreach ($overrides as $ov) {
            $cohort_name = $all_cohort_names[(int)$ov->cohortid] ?? get_string('deletedcohort', 'local_asyncwatch');

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

            $table->data[] = [$cohort_name, userdate($ov->deadline), $wdisp, $actions];
        }
        echo html_writer::table($table);
    } else {
        echo $OUTPUT->notification(get_string('nooverrides', 'local_asyncwatch'), 'info');
    }

    $add_url = new moodle_url($pageurl, ['action' => 'addoverride']);
    echo $OUTPUT->single_button($add_url, get_string('addoverride', 'local_asyncwatch'), 'get');
}

echo $OUTPUT->footer();