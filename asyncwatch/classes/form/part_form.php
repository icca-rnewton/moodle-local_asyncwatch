<?php
/**
 * Form: create / edit a Part.
 *
 * The activity list is rendered as a static HTML block with full control over
 * markup. Activities are grouped by type within each section, matching the
 * quality of the Auto Parts preview UI.
 *
 * @package    local_asyncwatch
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_asyncwatch\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use local_asyncwatch\helper;

class part_form extends \moodleform {

    public function definition(): void {
        $mform    = $this->_form;
        $courseid = $this->_customdata['courseid'];
        $partid   = $this->_customdata['partid'] ?? 0;

        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'partid', $partid);
        $mform->setType('partid', PARAM_INT);

        // Part name.
        $mform->addElement('text', 'name', get_string('partname', 'local_asyncwatch'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addHelpButton('name', 'partname', 'local_asyncwatch');

        // Activity list header.
        $mform->addElement('header', 'activities_header', get_string('partactivities', 'local_asyncwatch'));

        $sections_by_num    = helper::get_course_activities_by_section($courseid);
        $currently_assigned = $partid ? helper::get_part_cmids($partid) : [];

        $mform->addElement('static', 'activity_list', '',
            self::render_activity_list($sections_by_num, $currently_assigned, $courseid)
        );

        // Register all cm fields so Moodle's form processor accepts them.
        foreach ($sections_by_num as $sec_id => $sec) {
            foreach ($sec['activities'] as $cm) {
                $mform->addElement('hidden', 'asyncwatch_cm_' . $cm->cmid, 0);
                $mform->setType('asyncwatch_cm_' . $cm->cmid, PARAM_INT);
            }
            foreach ($sec['subsections'] as $sub) {
                foreach ($sub['activities'] as $cm) {
                    $mform->addElement('hidden', 'asyncwatch_cm_' . $cm->cmid, 0);
                    $mform->setType('asyncwatch_cm_' . $cm->cmid, PARAM_INT);
                }
            }
        }

        $mform->addElement('static', 'asyncwatch_js', '', self::render_js());

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    private static function render_activity_list(array $sections, array $currently_assigned, int $courseid): string {
        $str_warn     = get_string('tracking_off_warning', 'local_asyncwatch');
        $str_section  = get_string('filter_section',       'local_asyncwatch');
        $str_type     = get_string('filter_type',          'local_asyncwatch');
        $str_tracking = get_string('filter_tracking',      'local_asyncwatch');

        // Collect unique modnames.
        $modnames = [];
        foreach ($sections as $sec) {
            foreach ($sec['activities'] as $cm) {
                $modnames[$cm->modname] = ucfirst($cm->modname);
            }
            foreach ($sec['subsections'] as $sub) {
                foreach ($sub['activities'] as $cm) {
                    $modnames[$cm->modname] = ucfirst($cm->modname);
                }
            }
        }
        ksort($modnames);

        // Count initial selection.
        $initial_count = count($currently_assigned);

        // Section filter options.
        $section_opts = '<option value="">' . get_string('all') . '</option>';
        foreach ($sections as $sec_id => $sec) {
            $section_opts .= '<option value="' . (int)$sec_id . '">' . s(format_string($sec['name'])) . '</option>';
        }

        // Type filter options.
        $type_opts = '<option value="">' . get_string('all') . '</option>';
        foreach ($modnames as $mn => $label) {
            $type_opts .= '<option value="' . s($mn) . '">' . s($label) . '</option>';
        }

        // ── Toolbar ───────────────────────────────────────────────────────
        $html  = '<div style="margin-bottom:1rem;">';

        // Selection count bar.
        $html .= '<div class="d-flex align-items-center justify-content-between mb-2">';
        $html .= '<span id="aw-selected-count" class="badge badge-primary" style="font-size:0.95em;padding:0.4em 0.8em;">';
        $html .= $initial_count . ' ' . ($initial_count === 1 ? 'activity' : 'activities') . ' selected';
        $html .= '</span>';
        $html .= '<div>';
        $html .= '<button type="button" id="aw-select-all" class="btn btn-sm btn-outline-secondary mr-1">Select all visible</button>';
        $html .= '<button type="button" id="aw-clear-all" class="btn btn-sm btn-outline-secondary">Clear all</button>';
        $html .= '</div></div>';

        // Filter bar.
        $html .= '<div class="card card-body p-2 mb-3" style="background:#f8f9fa;">';
        $html .= '<div class="d-flex flex-wrap align-items-end" style="gap:0.5rem 1rem;">';

        $html .= '<div><label class="d-block small font-weight-bold mb-1" for="aw-filter-section">' . s($str_section) . '</label>';
        $html .= '<select id="aw-filter-section" class="custom-select custom-select-sm">' . $section_opts . '</select></div>';

        $html .= '<div><label class="d-block small font-weight-bold mb-1" for="aw-filter-type">' . s($str_type) . '</label>';
        $html .= '<select id="aw-filter-type" class="custom-select custom-select-sm">' . $type_opts . '</select></div>';

        $html .= '<div class="d-flex align-items-end" style="padding-bottom:0.15rem;">';
        $html .= '<div class="form-check mb-0">';
        $html .= '<input class="form-check-input" type="checkbox" id="aw-filter-tracking">';
        $html .= '<label class="form-check-label small font-weight-bold" for="aw-filter-tracking">' . s($str_tracking) . '</label>';
        $html .= '</div></div>';

        $html .= '</div></div>';

        // ── Activity list ─────────────────────────────────────────────────
        $html .= '<div id="asyncwatch-activity-list">';

        foreach ($sections as $sec_id => $sec) {
            // Gather all activities in this section (direct + from subsections).
            $all_direct = $sec['activities'];
            $has_content = !empty($all_direct) || !empty($sec['subsections']);
            if (!$has_content) continue;

            $html .= '<div class="asyncwatch-section-block card mb-2" data-section="' . (int)$sec_id . '">';
            $html .= '<div class="card-header py-2 d-flex justify-content-between align-items-center" style="background:#e9ecef;">';
            $html .= '<strong>' . s(format_string($sec['name'])) . '</strong>';
            $html .= '<button type="button" class="btn btn-sm btn-link p-0 aw-sec-toggle" data-sec="' . (int)$sec_id . '" style="font-size:0.8em;text-decoration:none;">Select section</button>';
            $html .= '</div>';
            $html .= '<div class="card-body p-3">';

            // Direct activities grouped by type.
            if (!empty($all_direct)) {
                $html .= self::render_grouped_activities($all_direct, $currently_assigned, $sec_id, $str_warn, 0);
            }

            // Subsections.
            foreach ($sec['subsections'] as $sub_id => $sub) {
                if (empty($sub['activities'])) continue;
                $html .= '<div class="asyncwatch-subsection-block mt-3" data-section="' . (int)$sec_id . '">';
                $html .= '<div class="d-flex align-items-center mb-2">';
                $html .= '<span style="width:3px;height:1.2em;background:#6c757d;border-radius:2px;margin-right:0.5rem;display:inline-block;"></span>';
                $html .= '<span class="text-muted font-weight-bold small">' . s(format_string($sub['name'])) . '</span>';
                $html .= '</div>';
                $html .= self::render_grouped_activities($sub['activities'], $currently_assigned, $sec_id, $str_warn, 1);
                $html .= '</div>';
            }

            $html .= '</div></div>';
        }

        $html .= '</div></div>';
        return $html;
    }

    /**
     * Render a set of activities grouped by modname with bold type headers.
     */
    private static function render_grouped_activities(array $activities, array $currently_assigned, int $sec_id, string $str_warn, int $indent): string {
        // Group by modname, preserving course order within each group.
        $grouped = [];
        foreach ($activities as $cm) {
            $grouped[$cm->modname][] = $cm;
        }
        ksort($grouped);

        $html = '';
        foreach ($grouped as $modname => $cms) {
            $html .= '<div class="aw-type-group mb-3" data-modname="' . s($modname) . '">';
            // Type header with pill badge.
            $html .= '<div class="d-flex align-items-center mb-1">';
            $html .= '<span class="badge" style="background:#dee2e6;color:#495057;font-size:0.8em;margin-right:0.5rem;">'
                   . s(ucfirst($modname)) . '</span>';
            $html .= '<span class="text-muted" style="font-size:0.75em;">' . count($cms) . '</span>';
            $html .= '</div>';

            // Activity checkboxes in a flex-wrap grid.
            $html .= '<div class="d-flex flex-wrap" style="gap:0.3rem 0.5rem;">';
            foreach ($cms as $cm) {
                $html .= self::render_cm_chip($cm, $currently_assigned, $sec_id, $str_warn, $indent);
            }
            $html .= '</div></div>';
        }
        return $html;
    }

    /**
     * Render a single activity as a selectable chip/card.
     */
    private static function render_cm_chip(object $cm, array $currently_assigned, int $sec_id, string $str_warn, int $indent): string {
        $cmid    = (int)$cm->cmid;
        $checked = in_array($cmid, $currently_assigned);
        $tracked = $cm->tracking_enabled ? '1' : '0';
        $modname = $cm->modname;

        $chip_style  = 'display:inline-flex;align-items:center;padding:0.25rem 0.6rem;border-radius:4px;border:1.5px solid;cursor:pointer;';
        $chip_style .= $checked
            ? 'border-color:#0f6cbf;background:#e8f0fc;font-weight:500;'
            : 'border-color:#dee2e6;background:#fff;';

        if (!$cm->tracking_enabled) {
            $chip_style .= 'opacity:0.7;';
        }

        $html  = '<label class="asyncwatch-cm-chip mb-1" style="' . $chip_style . '"'
               . ' data-section="' . $sec_id . '"'
               . ' data-modname="' . s($modname) . '"'
               . ' data-tracking="' . $tracked . '">';

        $html .= '<input type="checkbox" class="asyncwatch-cm-vis"'
               . ' id="aw-cm-' . $cmid . '"'
               . ' data-cmid="' . $cmid . '"'
               . ($checked ? ' checked' : '')
               . ' style="margin-right:0.4rem;">';

        $html .= '<span>' . s(format_string($cm->name)) . '</span>';

        if (!$cm->tracking_enabled) {
            $html .= ' <span class="asyncwatch-warn-icon text-warning ml-1"'
                   . ' title="' . s($str_warn) . '"'
                   . ' data-toggle="tooltip" data-bs-toggle="tooltip"'
                   . ' data-placement="right" aria-label="' . s($str_warn) . '"'
                   . ' style="cursor:help;">⚠</span>';
        }

        $html .= '</label>';
        $html .= '<input type="hidden" name="asyncwatch_cm_' . $cmid . '" id="aw-hidden-' . $cmid . '" value="' . ($checked ? '1' : '0') . '">';

        return $html;
    }

    private static function render_js(): string {
        return <<<'JS'
<script>
(function() {
    function updateCount() {
        var n = document.querySelectorAll('.asyncwatch-cm-vis:checked').length;
        var el = document.getElementById('aw-selected-count');
        if (el) el.textContent = n + ' ' + (n === 1 ? 'activity' : 'activities') + ' selected';
    }

    function applyFilters() {
        var sectionVal   = document.getElementById('aw-filter-section').value;
        var typeVal      = document.getElementById('aw-filter-type').value;
        var trackingOnly = document.getElementById('aw-filter-tracking').checked;

        // Show/hide individual chips.
        document.querySelectorAll('.asyncwatch-cm-chip').forEach(function(chip) {
            var section  = String(chip.dataset.section);
            var modname  = String(chip.dataset.modname);
            var tracking = String(chip.dataset.tracking);
            var ok = (!sectionVal  || section  === sectionVal)
                  && (!typeVal     || modname  === typeVal)
                  && (!trackingOnly || tracking === '1');
            chip.style.display = ok ? '' : 'none';
            // Also hide the adjacent hidden input (it's a sibling).
            var hid = chip.nextElementSibling;
            if (hid && hid.type === 'hidden') hid.style.display = ok ? '' : 'none';
        });

        // Hide type groups where all chips are hidden.
        document.querySelectorAll('.aw-type-group').forEach(function(g) {
            var any = Array.from(g.querySelectorAll('.asyncwatch-cm-chip'))
                          .some(function(c) { return c.style.display !== 'none'; });
            g.style.display = any ? '' : 'none';
        });

        // Hide subsection blocks where all groups are hidden.
        document.querySelectorAll('.asyncwatch-subsection-block').forEach(function(block) {
            var any = Array.from(block.querySelectorAll('.aw-type-group'))
                          .some(function(g) { return g.style.display !== 'none'; });
            block.style.display = any ? '' : 'none';
        });

        // Hide section blocks where everything is hidden.
        document.querySelectorAll('.asyncwatch-section-block').forEach(function(block) {
            var anyChip = Array.from(block.querySelectorAll('.asyncwatch-cm-chip'))
                              .some(function(c) { return c.style.display !== 'none'; });
            block.style.display = anyChip ? '' : 'none';
        });
    }

    function syncChip(cb) {
        var hid = document.getElementById('aw-hidden-' + cb.dataset.cmid);
        if (hid) hid.value = cb.checked ? '1' : '0';

        // Update chip styling.
        var chip = cb.closest('.asyncwatch-cm-chip');
        if (chip) {
            if (cb.checked) {
                chip.style.borderColor = '#0f6cbf';
                chip.style.background  = '#e8f0fc';
                chip.style.fontWeight  = '500';
            } else {
                chip.style.borderColor = '#dee2e6';
                chip.style.background  = '#fff';
                chip.style.fontWeight  = '';
            }
        }
        updateCount();
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Live filters — no Apply button needed.
        ['aw-filter-section', 'aw-filter-type'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('change', applyFilters);
        });
        var trackEl = document.getElementById('aw-filter-tracking');
        if (trackEl) trackEl.addEventListener('change', applyFilters);

        // Chip checkboxes.
        document.querySelectorAll('.asyncwatch-cm-vis').forEach(function(cb) {
            cb.addEventListener('change', function() { syncChip(cb); });
        });

        // Select all visible.
        var selAll = document.getElementById('aw-select-all');
        if (selAll) selAll.addEventListener('click', function() {
            document.querySelectorAll('.asyncwatch-cm-chip').forEach(function(chip) {
                if (chip.style.display === 'none') return;
                var cb = chip.querySelector('.asyncwatch-cm-vis');
                if (cb && !cb.checked) { cb.checked = true; syncChip(cb); }
            });
        });

        // Clear all (visible chips only).
        var clearAll = document.getElementById('aw-clear-all');
        if (clearAll) clearAll.addEventListener('click', function() {
            document.querySelectorAll('.asyncwatch-cm-chip').forEach(function(chip) {
                if (chip.style.display === 'none') return;
                var cb = chip.querySelector('.asyncwatch-cm-vis');
                if (cb && cb.checked) { cb.checked = false; syncChip(cb); }
            });
        });

        // Per-section toggle.
        document.querySelectorAll('.aw-sec-toggle').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var sec = btn.dataset.sec;
                var block = document.querySelector('.asyncwatch-section-block[data-section="' + sec + '"]');
                if (!block) return;
                var chips = block.querySelectorAll('.asyncwatch-cm-chip');
                var allChecked = Array.from(chips).every(function(c) {
                    return c.style.display === 'none' || c.querySelector('.asyncwatch-cm-vis').checked;
                });
                chips.forEach(function(chip) {
                    if (chip.style.display === 'none') return;
                    var cb = chip.querySelector('.asyncwatch-cm-vis');
                    if (!cb) return;
                    cb.checked = !allChecked;
                    syncChip(cb);
                });
            });
        });

        // Tooltips.
        document.querySelectorAll('.asyncwatch-warn-icon').forEach(function(el) {
            if (window.bootstrap && window.bootstrap.Tooltip) {
                new window.bootstrap.Tooltip(el, {placement: 'right'});
            } else if (typeof jQuery !== 'undefined' && typeof jQuery.fn.tooltip === 'function') {
                jQuery(el).tooltip({placement: 'right'});
            }
        });

        updateCount();
    });
})();
</script>
JS;
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (empty(trim($data['name'] ?? ''))) {
            $errors['name'] = get_string('required');
        }
        return $errors;
    }

    public static function extract_cmids(array $data): array {
        $cmids = [];
        foreach ($data as $key => $val) {
            if (str_starts_with($key, 'asyncwatch_cm_') && (int)$val === 1) {
                $cmids[] = (int)substr($key, strlen('asyncwatch_cm_'));
            }
        }
        return $cmids;
    }
}
