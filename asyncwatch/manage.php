<?php
/**
 * AsyncWatch management page (Parts tab + Rules tab).
 *
 * URL: /local/asyncwatch/manage.php?courseid=X[&tab=parts|rules][&action=...][&id=...]
 *
 * @package    local_asyncwatch
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_asyncwatch\helper;
use local_asyncwatch\form\part_form;
use local_asyncwatch\form\rule_form;

// ── Parameters ──────────────────────────────────────────────────────────────
$courseid = required_param('courseid', PARAM_INT);
$tab      = optional_param('tab',    'parts',  PARAM_ALPHA);
$action   = optional_param('action', '',       PARAM_ALPHA);
$id       = optional_param('id',     0,        PARAM_INT);

// ── Auth / context ───────────────────────────────────────────────────────────
$course  = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login($course);
require_capability('local/asyncwatch:manage', $context);

// ── Page setup ───────────────────────────────────────────────────────────────
// Base URL for the tab (no action/id) — used for redirects and tab links.
$baseurl = new moodle_url('/local/asyncwatch/manage.php', ['courseid' => $courseid, 'tab' => $tab]);

// Full URL including action + id — this is what the form must POST to so that
// $action is still set when Moodle processes the submission.
$formurl = new moodle_url('/local/asyncwatch/manage.php', [
    'courseid' => $courseid,
    'tab'      => $tab,
    'action'   => $action,
    'id'       => $id,
]);

$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('manage', 'local_asyncwatch'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');


// ── Handle deletions (GET with confirm) ──────────────────────────────────────
if ($action === 'deletepart' && $id && confirm_sesskey()) {
    $part = $DB->get_record('asyncwatch_parts', ['id' => $id], 'id,courseid', MUST_EXIST);
    if ((int)$part->courseid !== $courseid) {
        throw new \moodle_exception('invalidrecord', 'error');
    }
    // Warn if rules exist — deleting a part reduces total parts count.
    $rules = helper::get_rules($courseid);
    helper::delete_part($id);
    $msg = get_string('partdeleted', 'local_asyncwatch');
    if (!empty($rules)) {
        $msg .= ' ' . get_string('partdeleted_rules_warning', 'local_asyncwatch');
    }
    redirect($baseurl, $msg, null, \core\output\notification::NOTIFY_SUCCESS);
}
if ($action === 'deleterule' && $id && confirm_sesskey()) {
    $rule = $DB->get_record('asyncwatch_rules', ['id' => $id], 'id,courseid', MUST_EXIST);
    if ((int)$rule->courseid !== $courseid) {
        throw new \moodle_exception('invalidrecord', 'error');
    }
    helper::delete_rule($id);
    redirect($baseurl, get_string('ruledeleteconfirm', 'local_asyncwatch'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// ── Handle bulk deletions (POST) ─────────────────────────────────────────────
if ($action === 'bulkdeleteparts' && confirm_sesskey()) {
    $ids = optional_param_array('bulkids', [], PARAM_INT);
    $rules = helper::get_rules($courseid);
    foreach ($ids as $pid) {
        $part = $DB->get_record('asyncwatch_parts', ['id' => (int)$pid], 'id,courseid');
        if (!$part || (int)$part->courseid !== $courseid) continue;
        helper::delete_part((int)$pid);
    }
    $msg = get_string('bulkdeleted', 'local_asyncwatch', count($ids));
    if (!empty($rules) && !empty($ids)) {
        $msg .= ' ' . get_string('partdeleted_rules_warning', 'local_asyncwatch');
    }
    redirect($baseurl, $msg, null, \core\output\notification::NOTIFY_SUCCESS);
}
if ($action === 'bulkdeleterules' && confirm_sesskey()) {
    $ids = optional_param_array('bulkids', [], PARAM_INT);
    foreach ($ids as $rid) {
        $rule = $DB->get_record('asyncwatch_rules', ['id' => (int)$rid], 'id,courseid');
        if (!$rule || (int)$rule->courseid !== $courseid) continue;
        helper::delete_rule((int)$rid);
    }
    redirect($baseurl, get_string('bulkdeleted', 'local_asyncwatch', count($ids)), null, \core\output\notification::NOTIFY_SUCCESS);
}

// ── Forms ─────────────────────────────────────────────────────────────────────

// --- PART FORM ---
$part_form = null;
if ($tab === 'parts' && in_array($action, ['addpart', 'editpart'])) {
    // Pass $formurl so the form's action attribute preserves action+id on POST.
    $part_form = new part_form($formurl->out(false), ['courseid' => $courseid, 'partid' => $id]);

    if ($part_form->is_cancelled()) {
        redirect($baseurl);
    }

    if ($formdata = $part_form->get_data()) {
        $record = (object)[
            'courseid'  => $courseid,
            'name'      => $formdata->name,
            'sortorder' => 0,
        ];
        if ($id) {
            $record->id = $id;
        }
        $partid = helper::save_part($record);
        $cmids  = part_form::extract_cmids((array)$formdata);
        helper::set_part_activities($partid, $cmids);
        redirect($baseurl, get_string('partsaved', 'local_asyncwatch'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    // Pre-populate form when editing.
    if ($action === 'editpart' && $id) {
        $part = helper::get_part($id);
        $part_form->set_data(['name' => $part->name, 'partid' => $id, 'courseid' => $courseid]);
    }
}

// --- RULE FORM ---
$rule_form = null;
if ($tab === 'rules' && in_array($action, ['addrule', 'editrule'])) {
    $total_parts = count(helper::get_parts($courseid));
    // Look up current ruleset assignment so the form can pre-select it.
    $current_rulesetid = 0;
    if ($id) {
        $srec = $DB->get_record('asyncwatch_ruleset_rules', ['ruleid' => $id], 'rulesetid');
        $current_rulesetid = $srec ? (int)$srec->rulesetid : 0;
    }
    $rule_form = new rule_form($formurl->out(false), [
        'courseid'          => $courseid,
        'ruleid'            => $id,
        'total_parts'       => $total_parts,
        'current_rulesetid' => $current_rulesetid,
    ]);

    if ($rule_form->is_cancelled()) {
        redirect($baseurl);
    }

    if ($formdata = $rule_form->get_data()) {
        $record = (object)[
            'courseid'              => $courseid,
            'name'                  => $formdata->name,
            'parts_required'        => (int)$formdata->parts_required,
            'deadline'              => (int)$formdata->deadline,
            'warn_hours'            => rule_form::warn_to_hours((array)$formdata),
            'enabled'               => (int)$formdata->enabled,
            'notify_learner_breach' => (int)($formdata->notify_learner_breach  ?? 0),
            'notify_staff_breach'   => (int)($formdata->notify_staff_breach    ?? 0),
            'notify_learner_warning'=> (int)($formdata->notify_learner_warning ?? 0),
            'notify_staff_warning'  => (int)($formdata->notify_staff_warning   ?? 0),
        ];
        if ($id) {
            $record->id = $id;
        }
        $ruleid = helper::save_rule($record);

        // Handle rule set assignment.
        $rulesetid = (int)($formdata->rulesetid ?? 0);
        // Remove from any existing set first.
        $DB->delete_records('asyncwatch_ruleset_rules', ['ruleid' => $ruleid]);
        if ($rulesetid > 0) {
            // Add to new set (avoid duplicate).
            if (!$DB->record_exists('asyncwatch_ruleset_rules', ['rulesetid' => $rulesetid, 'ruleid' => $ruleid])) {
                $DB->insert_record('asyncwatch_ruleset_rules', (object)['rulesetid' => $rulesetid, 'ruleid' => $ruleid]);
            }
        }

        redirect($baseurl, get_string('rulesaved', 'local_asyncwatch'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    if ($action === 'editrule' && $id) {
        $rule = $DB->get_record('asyncwatch_rules', ['id' => $id], '*', MUST_EXIST);
        $warn_fields = rule_form::hours_to_warn_fields((int)$rule->warn_hours);
        $current_set = $DB->get_record('asyncwatch_ruleset_rules', ['ruleid' => $id], 'rulesetid');
        $rule_form->set_data([
            'ruleid'               => $id,
            'courseid'             => $courseid,
            'name'                 => $rule->name,
            'parts_required'       => $rule->parts_required,
            'deadline'             => $rule->deadline,
            'enabled'              => $rule->enabled,
            'notify_learner_breach'  => $rule->notify_learner_breach  ?? 0,
            'notify_staff_breach'    => $rule->notify_staff_breach    ?? 0,
            'notify_learner_warning' => $rule->notify_learner_warning ?? 0,
            'notify_staff_warning'   => $rule->notify_staff_warning   ?? 0,
            'warn_enabled'           => $warn_fields['warn_enabled'],
            'warn_value'             => $warn_fields['warn_value'],
            'warn_unit'              => $warn_fields['warn_unit'],
            'rulesetid'              => $current_set ? (int)$current_set->rulesetid : 0,
        ]);
    }
}

// ── Render ────────────────────────────────────────────────────────────────────
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manage', 'local_asyncwatch'));

// Tab bar.
$tabs = [
    new tabobject('parts',         new moodle_url($baseurl, ['tab' => 'parts']),  get_string('tab_parts',         'local_asyncwatch')),
    new tabobject('rules',         new moodle_url($baseurl, ['tab' => 'rules']),  get_string('tab_rules',         'local_asyncwatch')),
    new tabobject('rulesets',      new moodle_url('/local/asyncwatch/rulesets.php',      ['courseid' => $courseid]), get_string('tab_rulesets',      'local_asyncwatch')),
    new tabobject('report',        new moodle_url('/local/asyncwatch/report.php',        ['courseid' => $courseid]), get_string('tab_report',        'local_asyncwatch')),
    new tabobject('notifications', new moodle_url('/local/asyncwatch/notifications.php', ['courseid' => $courseid]), get_string('tab_notifications', 'local_asyncwatch')),
];
echo $OUTPUT->tabtree($tabs, $tab);

// ── PARTS tab ─────────────────────────────────────────────────────────────────
if ($tab === 'parts') {
    if ($part_form) {
        $part_form->display();
    } else {
        $parts = helper::get_parts($courseid);
        $total = count($parts);

        echo html_writer::tag('p', get_string('totalparts', 'local_asyncwatch', $total));

        if ($total) {
            $bulk_url = new moodle_url($baseurl, ['action' => 'bulkdeleteparts', 'sesskey' => sesskey()]);
            echo '<form method="post" action="' . $bulk_url->out(false) . '" id="parts-bulk-form">';

            $table = new html_table();
            $table->head = [
                html_writer::checkbox('selectall_parts', '1', false, '', ['id' => 'selectall-parts', 'title' => get_string('selectall')]),
                get_string('partname',       'local_asyncwatch'),
                get_string('partactivities', 'local_asyncwatch'),
                get_string('actions'),
            ];
            $table->attributes['class'] = 'generaltable';

            $i = 1;
            foreach ($parts as $part) {
                $cmids      = helper::get_part_cmids((int)$part->id);
                $edit_url   = new moodle_url($baseurl, ['action' => 'editpart',   'id' => $part->id]);
                $delete_url = new moodle_url($baseurl, ['action' => 'deletepart', 'id' => $part->id, 'sesskey' => sesskey()]);

                $actions =
                    html_writer::link($edit_url,   $OUTPUT->pix_icon('t/edit',   get_string('edit'))) . ' ' .
                    html_writer::link($delete_url, $OUTPUT->pix_icon('t/delete', get_string('delete')),
                        ['onclick' => 'return confirm(' . json_encode(get_string('partdeleteconfirm', 'local_asyncwatch')) . ')']);

                $name_cell = format_string($part->name);
                if (!empty($part->is_auto)) {
                    $name_cell .= ' ' . html_writer::tag('span',
                        get_string('autoparts_badge', 'local_asyncwatch'),
                        ['class' => 'badge badge-info bg-info text-white ml-1', 'style' => 'font-size:0.85em;']
                    );
                }
                $table->data[] = [
                    html_writer::checkbox('bulkids[]', $part->id, false, '', ['class' => 'bulk-checkbox-parts']),
                    $name_cell,
                    count($cmids) . ' ' . get_string('activities'),
                    $actions,
                ];
                $i++;
            }
            echo html_writer::table($table);
            echo '<div class="d-flex align-items-center mt-3" style="gap:0.5rem;">';
            $add_url_inner      = new moodle_url($baseurl, ['action' => 'addpart']);
            $autopart_url_inner = new moodle_url('/local/asyncwatch/autoparts.php', ['courseid' => $courseid]);
            echo '<a href="' . $add_url_inner->out(false) . '" class="btn btn-primary">' . get_string('addpart', 'local_asyncwatch') . '</a>';
            echo '<a href="' . $autopart_url_inner->out(false) . '" class="btn btn-secondary">' . get_string('autoparts_btn', 'local_asyncwatch') . '</a>';
            echo '<button type="submit" class="btn btn-danger" onclick="return confirm(' . json_encode(get_string('bulkdeleteconfirm', 'local_asyncwatch')) . ')">' . get_string('bulkdelete', 'local_asyncwatch') . '</button>';
            echo '</div>';
            echo '</form>';
        } else {
            echo $OUTPUT->notification(get_string('noparts', 'local_asyncwatch'), 'info');
            $add_url      = new moodle_url($baseurl, ['action' => 'addpart']);
            $autopart_url = new moodle_url('/local/asyncwatch/autoparts.php', ['courseid' => $courseid]);
            echo '<div class="d-flex align-items-center mt-3" style="gap:0.5rem;">';
            echo '<a href="' . $add_url->out(false) . '" class="btn btn-primary">' . get_string('addpart', 'local_asyncwatch') . '</a>';
            echo '<a href="' . $autopart_url->out(false) . '" class="btn btn-secondary">' . get_string('autoparts_btn', 'local_asyncwatch') . '</a>';
            echo '</div>';
        }
    }
}

// ── RULES tab ─────────────────────────────────────────────────────────────────
if ($tab === 'rules') {
    if ($rule_form) {
        $rule_form->display();
    } else {
        $rules       = helper::get_rules($courseid);
        $total_parts = count(helper::get_parts($courseid));

        if ($total_parts === 0) {
            echo $OUTPUT->notification(get_string('noparts', 'local_asyncwatch'), 'warning');
        }

        if (!empty($rules)) {
            $bulk_url = new moodle_url($baseurl, ['action' => 'bulkdeleterules', 'sesskey' => sesskey()]);
            echo '<form method="post" action="' . $bulk_url->out(false) . '" id="rules-bulk-form">';

            $table = new html_table();
            $all_setrule_ids = helper::get_all_setrule_ids($courseid);

            $table->head = [
                html_writer::checkbox('selectall_rules', '1', false, '', ['id' => 'selectall-rules', 'title' => get_string('selectall')]),
                get_string('rulename',           'local_asyncwatch'),
                get_string('tab_rulesets',       'local_asyncwatch'),
                get_string('parts_required',     'local_asyncwatch'),
                get_string('default_deadline',  'local_asyncwatch'),
                get_string('default_warn',      'local_asyncwatch'),
                get_string('rule_enabled',       'local_asyncwatch'),
                get_string('notify_breach_col',  'local_asyncwatch'),
                get_string('notify_warning_col', 'local_asyncwatch'),
                get_string('actions'),
            ];
            $table->attributes['class'] = 'generaltable';

            foreach ($rules as $rule) {
                $edit_url   = new moodle_url($baseurl, ['action' => 'editrule',   'id' => $rule->id]);
                $delete_url = new moodle_url($baseurl, ['action' => 'deleterule', 'id' => $rule->id, 'sesskey' => sesskey()]);

                $override_url = new moodle_url('/local/asyncwatch/overrides.php', ['ruleid' => $rule->id]);
                $override_count = $DB->count_records('asyncwatch_rule_overrides', ['ruleid' => $rule->id]);
                $override_badge = $override_count > 0
                    ? ' ' . html_writer::tag('span', $override_count, ['class' => 'badge badge-info bg-info text-white', 'style' => 'font-size:0.95em;', 'title' => get_string('overrides_count', 'local_asyncwatch', $override_count)])
                    : '';

                $actions =
                    html_writer::link($edit_url,     $OUTPUT->pix_icon('t/edit',   get_string('edit'))) . ' ' .
                    html_writer::link($override_url, $OUTPUT->pix_icon('t/groups', get_string('overrides', 'local_asyncwatch')) . $override_badge,
                        ['title' => get_string('overrides', 'local_asyncwatch')]) . ' ' .
                    html_writer::link($delete_url,   $OUTPUT->pix_icon('t/delete', get_string('delete')),
                        ['onclick' => 'return confirm(' . json_encode(get_string('ruledeleteconfirm', 'local_asyncwatch')) . ')']);

                // Build mini tick/cross summary for each notification column.
                $breach_cell  = ($rule->notify_learner_breach  ? get_string('notify_learner_short', 'local_asyncwatch') : '—')
                              . ' / '
                              . ($rule->notify_staff_breach    ? get_string('notify_staff_short',   'local_asyncwatch') : '—');
                $warning_cell = ($rule->notify_learner_warning ? get_string('notify_learner_short', 'local_asyncwatch') : '—')
                              . ' / '
                              . ($rule->notify_staff_warning   ? get_string('notify_staff_short',   'local_asyncwatch') : '—');

                // Display warn value in human-readable form (stored as minutes).
                $warn_display = '—';
                if ($rule->warn_hours > 0) {
                    $wm = (int)$rule->warn_hours;
                    if ($wm % (7 * 24 * 60) === 0)      $warn_label = ($wm / (7*24*60)) . ' week(s)';
                    elseif ($wm % (24 * 60) === 0)       $warn_label = ($wm / (24*60))   . ' day(s)';
                    elseif ($wm % 60 === 0)              $warn_label = ($wm / 60)         . ' hour(s)';
                    else                                 $warn_label = $wm                . ' minute(s)';
                    $warn_start   = $rule->deadline - ($wm * MINSECS);
                    $warn_display = $warn_label . ' (' . userdate($warn_start, get_string('aw_datetimefmt', 'local_asyncwatch')) . ')';
                }

                $set_name = helper::get_rule_set_name_for_rule((int)$rule->id);
                $set_badge = $set_name
                    ? html_writer::tag('span', s($set_name), ['class' => 'badge badge-info bg-info text-white', 'style' => 'font-size:0.95em;'])
                    : html_writer::tag('span', get_string('rule_global', 'local_asyncwatch'), ['class' => 'badge badge-secondary bg-secondary text-white', 'style' => 'font-size:0.95em;']);

                $table->data[] = [
                    html_writer::checkbox('bulkids[]', $rule->id, false, '', ['class' => 'bulk-checkbox-rules']),
                    format_string($rule->name),
                    $set_badge,
                    $rule->parts_required . ' / ' . $total_parts,
                    userdate($rule->deadline, get_string('aw_datetimefmt', 'local_asyncwatch')),
                    $warn_display,
                    $rule->enabled ? '✓' : '✗',
                    $breach_cell,
                    $warning_cell,
                    $actions,
                ];
            }
            echo html_writer::table($table);
            echo '<div class="d-flex align-items-center mt-3" style="gap:0.5rem;">';
            $add_url_inner = new moodle_url($baseurl, ['action' => 'addrule']);
            echo '<a href="' . $add_url_inner->out(false) . '" class="btn btn-primary">' . get_string('addrule', 'local_asyncwatch') . '</a>';
            echo '<button type="submit" class="btn btn-danger" onclick="return confirm(' . json_encode(get_string('bulkdeleteconfirm', 'local_asyncwatch')) . ')">' . get_string('bulkdelete', 'local_asyncwatch') . '</button>';
            echo '</div>';
            echo '</form>';
        } else {
            echo $OUTPUT->notification(get_string('norules', 'local_asyncwatch'), 'info');
            $add_url = new moodle_url($baseurl, ['action' => 'addrule']);
            echo '<div class="d-flex align-items-center mt-3" style="gap:0.5rem;">';
            echo '<a href="' . $add_url->out(false) . '" class="btn btn-primary">' . get_string('addrule', 'local_asyncwatch') . '</a>';
            echo '</div>';
        }
    }
}

// Select-all checkbox JS for bulk tables.
echo '
<script>
(function() {
    function bindSelectAll(selectAllId, checkboxClass) {
        var sa = document.getElementById(selectAllId);
        if (!sa) return;
        sa.addEventListener("change", function() {
            document.querySelectorAll("." + checkboxClass).forEach(function(cb) {
                cb.checked = sa.checked;
            });
        });
    }
    bindSelectAll("selectall-parts", "bulk-checkbox-parts");
    bindSelectAll("selectall-rules", "bulk-checkbox-rules");
})();
</script>';

echo $OUTPUT->footer();
