<?php
/**
 * AsyncWatch Auto Parts generator.
 *
 * URL: /local/asyncwatch/autoparts.php?courseid=X[&step=1|2|3]
 *
 * Step 1: Mode + section selector + activity type filter
 * Step 2: Preview (with duplicate resolution)
 * Step 3: Confirm & save
 *
 * @package    local_asyncwatch
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use local_asyncwatch\helper;
use local_asyncwatch\form\autoparts_form;

// ── Auth ──────────────────────────────────────────────────────────────────────
$courseid = required_param('courseid', PARAM_INT);
$step     = optional_param('step', 1, PARAM_INT);

$course  = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);
require_login($course);
require_capability('local/asyncwatch:manage', $context);

$pageurl    = new moodle_url('/local/asyncwatch/autoparts.php', ['courseid' => $courseid]);
$manage_url = new moodle_url('/local/asyncwatch/manage.php', ['courseid' => $courseid, 'tab' => 'parts']);

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('autoparts_title', 'local_asyncwatch'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// ── Course structure data ─────────────────────────────────────────────────────
$has_subsections = helper::course_has_subsections($courseid);
$tracked_modtypes = helper::get_tracked_modtypes($courseid);
$modinfo  = get_fast_modinfo($courseid);
$sections = $modinfo->get_section_info_all();

// Build section lists — top-level sections and subsections separately.
// Subsections have component = 'mod_subsection' in mdl_course_sections.
// Build subsections first so we can exclude their IDs from the top-level list.
$subsection_sec_ids = [];
$subsection_options = [];

if ($has_subsections) {
    global $DB;

    // Build a map of subsection itemid => section info.
    // itemid in mdl_course_sections = instance ID in mdl_subsection.
    $sub_sec_by_instance = [];
    foreach ($sections as $sec) {
        if (($sec->component ?? '') === 'mod_subsection' && !empty($sec->itemid)) {
            $sub_sec_by_instance[(int)$sec->itemid] = $sec;
            $subsection_sec_ids[] = $sec->id;
        }
    }

    // Get subsection CMs directly from DB — cm_info may not expose ->instance.
    $sub_cms_db = $DB->get_records_sql(
        "SELECT cm.id, cm.instance, cm.section as parent_section_id, sub.name as subname
           FROM {course_modules} cm
           JOIN {modules} m ON m.id = cm.module AND m.name = 'subsection'
           JOIN {subsection} sub ON sub.id = cm.instance
          WHERE cm.course = :courseid AND cm.deletioninprogress = 0",
        ['courseid' => $courseid]
    );

    // Build sections map by DB id for parent lookup.
    $sections_by_id = [];
    foreach ($sections as $sec) {
        $sections_by_id[$sec->id] = $sec;
    }

    foreach ($sub_cms_db as $cm_row) {
        $sub_sec = $sub_sec_by_instance[(int)$cm_row->instance] ?? null;
        if (!$sub_sec) continue;

        $parent_sec = $sections_by_id[$cm_row->parent_section_id] ?? null;
        if (!$parent_sec || $parent_sec->section == 0) continue;
        $parent_name = get_section_name($courseid, $parent_sec->section);

        $subsection_options[$sub_sec->id] = [
            'name'   => $cm_row->subname ?: get_section_name($courseid, $sub_sec->section),
            'parent' => $parent_name,
        ];
    }
}

// Top-level sections: skip section 0 and any subsection child sections.
$section_options = [];
foreach ($sections as $sec) {
    if ($sec->section == 0) continue;
    if (in_array($sec->id, $subsection_sec_ids)) continue;
    $section_options[$sec->id] = get_section_name($courseid, $sec->section);
}

// ── Handle Step 3: Save ───────────────────────────────────────────────────────
if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $candidates_json = required_param('candidates_json', PARAM_RAW);
    $dupe_action     = required_param('dupe_action', PARAM_ALPHA);

    if (!in_array($dupe_action, ['skip', 'overwrite', 'rename'], true)) {
        throw new \moodle_exception('invalidparameter', 'error');
    }

    $candidates_raw = json_decode($candidates_json, true) ?: [];

    // Validate and sanitise each candidate coming from the browser.
    $candidates = [];
    foreach ($candidates_raw as $c) {
        $name = clean_param($c['name'] ?? '', PARAM_TEXT);
        if (core_text::strlen($name) > 255) {
            $name = core_text::substr($name, 0, 255);
        }
        if ($name === '') continue;
        $candidates[] = [
            'name'     => $name,
            'cmids'    => array_map('intval', $c['cmids'] ?? []),
            'empty'    => !empty($c['empty']),
        ];
    }

    $stats = helper::save_auto_parts($courseid, $candidates, $dupe_action);

    $msg = get_string('autoparts_saved', 'local_asyncwatch', (object)[
        'created'     => $stats['created'],
        'skipped'     => $stats['skipped'],
        'overwritten' => $stats['overwritten'],
        'renamed'     => $stats['renamed'],
    ]);
    redirect($manage_url, $msg, null, \core\output\notification::NOTIFY_SUCCESS);
}

// ── Handle Step 2: Preview ────────────────────────────────────────────────────
$candidates = [];
$mode       = '';
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode                        = required_param('mode', PARAM_ALPHA);
    $section_ids                 = optional_param_array('section_ids',    [], PARAM_INT);
    $subsection_ids              = optional_param_array('subsection_ids', [], PARAM_INT);
    $modtypes                    = optional_param_array('modtypes',       [], PARAM_ALPHANUMEXT);
    $include_subsection_activities = optional_param('include_subsection_activities', 0, PARAM_INT);

    $candidates = helper::build_auto_parts($courseid, $mode, $section_ids, $subsection_ids, $modtypes, (bool)$include_subsection_activities);
    // Enrich with activity names for preview UI.
    $candidates = helper::enrich_candidates($candidates, $courseid);
}

// ── Render ────────────────────────────────────────────────────────────────────
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('autoparts_title', 'local_asyncwatch'));
echo html_writer::link($manage_url, '← ' . get_string('backtomanage', 'local_asyncwatch'),
    ['class' => 'btn btn-link mb-3 pl-0']);

// ── Always instantiate step 1 form to catch cancel from any step ─────────────
$_step1_form = new autoparts_form(
    (new moodle_url($pageurl, ['step' => 2]))->out(false), [
    'has_subsections'    => $has_subsections,
    'section_options'    => $section_options,
    'subsection_options' => $subsection_options,
    'modtypes'           => $tracked_modtypes,
]);
if ($_step1_form->is_cancelled()) {
    redirect($manage_url);
}

// ════════════════════════════════════════════════════════════════════
// STEP 1 — Configuration (native Moodle form)
// ════════════════════════════════════════════════════════════════════
if ($step === 1) {
    $step1_form = $_step1_form;

    // Mode descriptions for JS.
    $mode_descs = json_encode([
        'section'    => get_string('autoparts_mode_section_desc',    'local_asyncwatch'),
        'subsection' => get_string('autoparts_mode_subsection_desc', 'local_asyncwatch'),
        'both'       => get_string('autoparts_mode_both_desc',       'local_asyncwatch'),
    ]);

    $step1_form->display();

    echo '<script>
(function() {
    var descs   = ' . $mode_descs . ';
    var sel     = document.getElementById("id_mode");
    var descEl  = document.getElementById("aw_mode_desc");
    var noteEl  = document.getElementById("aw_reset_note");
    var secBlk  = document.getElementById("aw_sections_block");
    var subBlk  = document.getElementById("aw_subsections_block");
    var first   = true;

    var incSubWrap = document.getElementById("aw_include_sub_wrap");

    function applyMode(mode) {
        // Show/hide the correct lists.
        if (secBlk) secBlk.style.display = (mode === "section" || mode === "both") ? "" : "none";
        if (subBlk) subBlk.style.display = (mode === "subsection" || mode === "both") ? "" : "none";

        // Show include-subsection-activities checkbox only for "Sections only".
        if (incSubWrap) incSubWrap.style.display = (mode === "section") ? "" : "none";

        // Update description.
        if (descEl) descEl.textContent = descs[mode] || "";

        // Show reset note after first change.
        if (!first && noteEl) noteEl.style.display = "";

        // Auto-reset all checkboxes to checked on mode change.
        if (!first) {
            document.querySelectorAll(".aw-section-cb, .aw-subsection-cb").forEach(function(cb) {
                cb.checked = true;
            });
            var saS = document.getElementById("aw_select_all_sections");
            var saU = document.getElementById("aw_select_all_subsections");
            if (saS) saS.checked = true;
            if (saU) saU.checked = true;
        }
        first = false;
    }

    // Init on load.
    if (sel) {
        applyMode(sel.value);
        sel.addEventListener("change", function() { applyMode(this.value); });
    }

    // Select-all toggles.
    function bindSelectAll(triggerId, childClass) {
        var trigger = document.getElementById(triggerId);
        if (!trigger) return;
        trigger.addEventListener("change", function() {
            document.querySelectorAll("." + childClass).forEach(function(cb) {
                cb.checked = trigger.checked;
            });
        });
    }
    bindSelectAll("aw_select_all_sections",    "aw-section-cb");
    bindSelectAll("aw_select_all_subsections", "aw-subsection-cb");
    bindSelectAll("aw_select_all_types",       "aw-type-cb");
})();
</script>';
}

// ════════════════════════════════════════════════════════════════════
// STEP 2 — Preview
// ════════════════════════════════════════════════════════════════════
if ($step === 2 && !empty($candidates)) {
    $has_any    = false;
    $all_empty  = true;
    $total_acts = 0;
    $total_skip = 0;

    foreach ($candidates as $c) {
        if (!empty($c['cmids'])) { $has_any = true; $all_empty = false; }
        $total_acts += count($c['cmids'] ?? []);
        $total_skip += (int)($c['skipped_count'] ?? 0);
    }

    if ($all_empty) {
        echo $OUTPUT->notification(get_string('autoparts_preview_empty', 'local_asyncwatch'), 'warning');
        echo html_writer::link(new moodle_url($pageurl, ['step' => 1]), '← ' . get_string('back'), ['class' => 'btn btn-secondary']);
    } else {
        // Summary banner.
        echo '<div class="alert alert-info mb-4">';
        echo '<strong>' . get_string('autoparts_preview_summary', 'local_asyncwatch') . '</strong><br>';
        $will_create = count(array_filter($candidates, fn($c) => !empty($c['cmids'])));
        echo get_string('autoparts_preview_will_create', 'local_asyncwatch', (object)[
            'parts' => $will_create, 'activities' => $total_acts,
        ]);
        if ($total_skip > 0) {
            echo '<br>' . get_string('autoparts_preview_skipped_tracking', 'local_asyncwatch', $total_skip);
        }
        $empty_count = count(array_filter($candidates, fn($c) => !empty($c['empty'])));
        if ($empty_count > 0) {
            echo '<br>' . get_string('autoparts_preview_empty_parts', 'local_asyncwatch', $empty_count);
        }
        echo '</div>';

        // Duplicate resolution.
        $dupes = array_filter($candidates, function($c) use ($courseid, $DB) {
            return !empty($c['cmids']) && $DB->record_exists('asyncwatch_parts', ['courseid' => $courseid, 'name' => $c['name']]);
        });

        if (!empty($dupes)) {
            echo '<div class="card mb-4 border-warning">';
            echo '<div class="card-header bg-warning"><strong>' . get_string('autoparts_dupes_found', 'local_asyncwatch') . '</strong></div>';
            echo '<div class="card-body">';
            echo '<p>' . get_string('autoparts_dupes_desc', 'local_asyncwatch') . '</p>';
            echo '<div class="form-check"><input class="form-check-input" type="radio" name="dupe_action_preview" id="da_skip" value="skip" checked>';
            echo '<label class="form-check-label" for="da_skip"><strong>' . get_string('autoparts_dupe_skip', 'local_asyncwatch') . '</strong> — ' . get_string('autoparts_dupe_skip_desc', 'local_asyncwatch') . '</label></div>';
            echo '<div class="form-check"><input class="form-check-input" type="radio" name="dupe_action_preview" id="da_overwrite" value="overwrite">';
            echo '<label class="form-check-label" for="da_overwrite"><strong>' . get_string('autoparts_dupe_overwrite', 'local_asyncwatch') . '</strong> — ' . get_string('autoparts_dupe_overwrite_desc', 'local_asyncwatch') . '</label></div>';
            echo '<div class="form-check"><input class="form-check-input" type="radio" name="dupe_action_preview" id="da_rename" value="rename">';
            echo '<label class="form-check-label" for="da_rename"><strong>' . get_string('autoparts_dupe_rename', 'local_asyncwatch') . '</strong> — ' . get_string('autoparts_dupe_rename_desc', 'local_asyncwatch') . '</label></div>';
            echo '</div></div>';
        }

        // Preview table with accordion activity detail.
        echo '<div class="table-responsive">';
        echo '<table class="generaltable w-100" id="aw-preview-table">';
        echo '<thead><tr>';
        echo '<th>' . get_string('autoparts_col_name',       'local_asyncwatch') . '</th>';
        echo '<th>' . get_string('autoparts_col_type',       'local_asyncwatch') . '</th>';
        echo '<th>' . get_string('autoparts_col_activities', 'local_asyncwatch') . '</th>';
        echo '<th>' . get_string('autoparts_col_skipped',    'local_asyncwatch') . '</th>';
        echo '<th>' . get_string('autoparts_col_status',     'local_asyncwatch') . '</th>';
        echo '<th>' . get_string('autoparts_col_detail',    'local_asyncwatch') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($candidates as $idx => $c) {
            $is_empty = !empty($c['empty']) || empty($c['cmids']);
            $type_badge = match($c['type']) {
                'section'    => html_writer::tag('span', get_string('autoparts_type_section',    'local_asyncwatch'), ['class' => 'badge badge-primary bg-primary text-white',    'style' => 'font-size:0.9em;']),
                'subsection' => html_writer::tag('span', get_string('autoparts_type_subsection', 'local_asyncwatch'), ['class' => 'badge badge-info bg-info text-white',          'style' => 'font-size:0.9em;']),
                'remainder'  => html_writer::tag('span', get_string('autoparts_type_remainder',  'local_asyncwatch'), ['class' => 'badge badge-secondary bg-secondary text-white', 'style' => 'font-size:0.9em;']),
                default      => '',
            };

            $is_dupe = $DB->record_exists('asyncwatch_parts', ['courseid' => $courseid, 'name' => $c['name']]);

            $status_id = 'aw-status-' . $idx;
            $status_val = $is_empty ? 'empty' : ($is_dupe ? 'dupe' : 'create');
            $status = match($status_val) {
                'empty'  => html_writer::tag('span', get_string('autoparts_status_empty',  'local_asyncwatch'), ['class' => 'badge badge-warning bg-warning',              'style' => 'font-size:0.9em;', 'id' => $status_id]),
                'dupe'   => html_writer::tag('span', get_string('autoparts_status_dupe',   'local_asyncwatch'), ['class' => 'badge badge-warning bg-warning',              'style' => 'font-size:0.9em;', 'id' => $status_id]),
                default  => html_writer::tag('span', get_string('autoparts_status_create', 'local_asyncwatch'), ['class' => 'badge badge-success bg-success text-white',   'style' => 'font-size:0.9em;', 'id' => $status_id]),
            };

            $has_detail = !empty($c['activities']) || !empty($c['skipped_acts']);
            $toggle_btn = $has_detail
                ? '<button type="button" class="btn btn-link p-0 aw-toggle-detail" data-idx="' . $idx . '">'
                  . get_string('autoparts_show_activities', 'local_asyncwatch') . '</button>'
                : '';

            $row_style = '';
            echo '<tr class="aw-preview-row" data-idx="' . $idx . '"' . $row_style . '>';
            echo '<td>' . s($c['name'] ?? '') . '</td>';
            echo '<td>' . $type_badge . '</td>';
            echo '<td class="aw-count-' . $idx . '">' . count($c['cmids'] ?? []) . '</td>';
            echo '<td>';
            if ((int)($c['skipped_count'] ?? 0) > 0) {
                echo html_writer::tag('span', (int)$c['skipped_count'], ['class' => 'text-warning font-weight-bold']);
            } else {
                echo '0';
            }
            echo '</td>';
            echo '<td>' . $status . '</td>';
            echo '<td>' . $toggle_btn . '</td>';
            echo '</tr>';

            // Detail row — hidden by default.
            if ($has_detail) {
                echo '<tr class="aw-detail-row" id="aw-detail-' . $idx . '" style="display:none;">';
                echo '<td colspan="6" style="background:#f8f9fa;padding:1rem 1.5rem 1rem 2rem;">';

                // Included activities — grouped by type (preserving course order within each type).
                if (!empty($c['activities'])) {
                    echo '<div class="mb-2"><strong>'
                       . get_string('autoparts_included', 'local_asyncwatch')
                       . '</strong></div>';
                    $inc_grouped = [];
                    foreach ($c['activities'] as $act) {
                        $inc_grouped[$act['modname']][] = $act;
                    }
                    ksort($inc_grouped);
                    foreach ($inc_grouped as $modname => $acts) {
                        echo '<div class="mb-2">';
                        echo '<div class="font-weight-bold mb-1">' . s(ucfirst($modname)) . '</div>';
                        echo '<div class="d-flex flex-wrap" style="gap:0.4rem 1rem;">';
                        foreach ($acts as $act) {
                            echo '<div class="form-check mb-0" style="padding-right:1rem;">';
                            echo '<input class="form-check-input aw-act-cb" type="checkbox"'
                               . ' data-idx="' . $idx . '"'
                               . ' data-cmid="' . (int)$act['cmid'] . '"'
                               . ' id="aw-act-' . $idx . '-' . $act['cmid'] . '" checked>';
                            echo '<label class="form-check-label" for="aw-act-' . $idx . '-' . $act['cmid'] . '">';
                            echo s($act['name']);
                            echo '</label></div>';
                        }
                        echo '</div></div>';
                    }
                }

                // Excluded activities — grouped by type (preserving course order within each type).
                if (!empty($c['skipped_acts'])) {
                    echo '<div class="mt-3 mb-1"><strong class="text-muted">'
                       . get_string('autoparts_excluded', 'local_asyncwatch')
                       . '</strong></div>';
                    $exc_grouped = [];
                    foreach ($c['skipped_acts'] as $act) {
                        $exc_grouped[$act['modname']][] = $act;
                    }
                    ksort($exc_grouped);
                    foreach ($exc_grouped as $modname => $acts) {
                        echo '<div class="mb-2 text-muted">';
                        echo '<div class="font-weight-bold mb-1">' . s(ucfirst($modname)) . '</div>';
                        echo '<div class="d-flex flex-wrap" style="gap:0.4rem 1rem;">';
                        foreach ($acts as $act) {
                            echo '<div class="mb-0">';
                            echo s($act['name']) . ' <em>(' . get_string('autoparts_tracking_off', 'local_asyncwatch') . ')</em>';
                            echo '</div>';
                        }
                        echo '</div></div>';
                    }
                }

                echo '</td></tr>';
            }
        }

        echo '</tbody></table></div>';

        // Confirm form — candidates_json built by JS from checkbox state.
        echo '<form method="post" action="' . (new moodle_url($pageurl, ['step' => 3]))->out(false) . '" class="mt-4" id="aw-confirm-form">';
        echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
        echo '<input type="hidden" name="candidates_json" id="aw-candidates-json" value="">';
        echo '<input type="hidden" name="dupe_action" id="dupe_action_hidden" value="skip">';

        echo '<button type="submit" class="btn btn-primary">' . get_string('autoparts_confirm', 'local_asyncwatch') . '</button>';
        echo ' <a href="' . (new moodle_url($pageurl, ['step' => 1]))->out(false) . '" class="btn btn-secondary ml-2">' . get_string('back') . '</a>';
        echo '</form>';

        // JS — toggle detail rows, update counts/status, build candidates_json on submit.
        $candidates_json = json_encode($candidates, JSON_HEX_TAG);
        echo '<script>
(function() {
    var candidates = ' . $candidates_json . ';

    // Toggle detail rows.
    document.querySelectorAll(".aw-toggle-detail").forEach(function(btn) {
        btn.addEventListener("click", function() {
            var idx = btn.dataset.idx;
            var row = document.getElementById("aw-detail-" + idx);
            if (!row) return;
            var hidden = row.style.display === "none";
            row.style.display = hidden ? "" : "none";
            btn.textContent = hidden ? "' . get_string('autoparts_hide_activities', 'local_asyncwatch') . '" : "' . get_string('autoparts_show_activities', 'local_asyncwatch') . '";
        });
    });

    // Update count and status badge when checkboxes change.
    document.querySelectorAll(".aw-act-cb").forEach(function(cb) {
        cb.addEventListener("change", function() {
            var idx = cb.dataset.idx;
            var checked = document.querySelectorAll(".aw-act-cb[data-idx=\'" + idx + "\']:checked").length;
            var countEl = document.querySelector(".aw-count-" + idx);
            if (countEl) countEl.textContent = checked;
            // Update status badge.
            var statusEl = document.getElementById("aw-status-" + idx);
            if (statusEl) {
                if (checked === 0) {
                    statusEl.className = "badge badge-secondary bg-secondary text-white";
                    statusEl.textContent = "' . get_string('autoparts_status_empty', 'local_asyncwatch') . '";
                } else {
                    statusEl.className = "badge badge-success bg-success text-white";
                    statusEl.textContent = "' . get_string('autoparts_status_create', 'local_asyncwatch') . '";
                }
            }
        });
    });

    // Dupe action sync.
    document.querySelectorAll("[name=dupe_action_preview]").forEach(function(r) {
        r.addEventListener("change", function() {
            document.getElementById("dupe_action_hidden").value = this.value;
        });
    });

    // On submit — rebuild candidates_json from current checkbox state.
    document.getElementById("aw-confirm-form").addEventListener("submit", function() {
        var updated = candidates.map(function(c, idx) {
            var cbs = document.querySelectorAll(".aw-act-cb[data-idx=\'" + idx + "\']");
            if (cbs.length === 0) return c; // No checkboxes — return as-is.
            var checkedCmids = [];
            cbs.forEach(function(cb) {
                if (cb.checked) checkedCmids.push(parseInt(cb.dataset.cmid));
            });
            var updated_c = Object.assign({}, c);
            updated_c.cmids = checkedCmids;
            if (checkedCmids.length === 0) updated_c.empty = true;
            return updated_c;
        });
        document.getElementById("aw-candidates-json").value = JSON.stringify(updated);
    });
})();
</script>';
    }
}

echo $OUTPUT->footer();
