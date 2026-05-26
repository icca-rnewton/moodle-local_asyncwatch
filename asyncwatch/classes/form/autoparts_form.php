<?php
/**
 * Moodle form for Auto Parts generator step 1.
 *
 * @package    local_asyncwatch
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_asyncwatch\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

class autoparts_form extends \moodleform {

    public function definition(): void {
        $mform              = $this->_form;
        $has_subsections    = $this->_customdata['has_subsections'];
        $section_options    = $this->_customdata['section_options'];    // [id => name]
        $subsection_options = $this->_customdata['subsection_options']; // [id => ['name'=>,'parent'=>]]
        $modtypes           = $this->_customdata['modtypes'];

        // ── Mode ──────────────────────────────────────────────────────────────
        $mode_options = [
            'section' => get_string('autoparts_mode_section', 'local_asyncwatch'),
        ];
        if ($has_subsections) {
            $mode_options['subsection'] = get_string('autoparts_mode_subsection', 'local_asyncwatch');
            $mode_options['both']       = get_string('autoparts_mode_both',       'local_asyncwatch');
        } else {
            $mode_options['subsection'] = get_string('autoparts_mode_subsection', 'local_asyncwatch')
                                        . ' — ' . get_string('autoparts_no_subsections', 'local_asyncwatch');
            $mode_options['both']       = get_string('autoparts_mode_both', 'local_asyncwatch')
                                        . ' — ' . get_string('autoparts_no_subsections', 'local_asyncwatch');
        }

        $mform->addElement('select', 'mode',
            get_string('autoparts_mode', 'local_asyncwatch'), $mode_options);
        $mform->setType('mode', PARAM_ALPHA);
        $mform->setDefault('mode', 'section');
        $mform->addHelpButton('mode', 'autoparts_mode', 'local_asyncwatch');

        if (!$has_subsections) {
            $mform->addElement('static', 'mode_note', '',
                \html_writer::tag('p',
                    get_string('autoparts_no_subsections_note', 'local_asyncwatch'),
                    ['class' => 'text-muted small mt-1']
                )
            );
        }

        // Dynamic description (updated by JS).
        $mform->addElement('static', 'mode_desc', '',
            \html_writer::tag('div',
                get_string('autoparts_mode_section_desc', 'local_asyncwatch'),
                ['id' => 'aw_mode_desc', 'class' => 'text-muted small mb-1']
            )
        );

        // "Include subsection activities" option — only shown for Sections only mode.
        $mform->addElement('static', 'include_subsection_acts_wrap', '',
            '<div id="aw_include_sub_wrap" class="form-check mt-2">'
          . '<input class="form-check-input" type="checkbox" name="include_subsection_activities"'
          . ' id="aw_include_sub_acts" value="1" checked>'
          . '<label class="form-check-label" for="aw_include_sub_acts">'
          . get_string('autoparts_include_subsection_acts', 'local_asyncwatch') . '</label>'
          . '<div class="text-muted small">'
          . get_string('autoparts_include_subsection_acts_desc', 'local_asyncwatch') . '</div>'
          . '</div>'
        );

        // Mode change note.
        $mform->addElement('static', 'mode_reset_note', '',
            \html_writer::tag('p',
                get_string('autoparts_mode_reset_note', 'local_asyncwatch'),
                ['class' => 'text-muted small font-italic', 'id' => 'aw_reset_note',
                 'style' => 'display:none;']
            )
        );

        // ── Sections list ─────────────────────────────────────────────────────
        $mform->addElement('header', 'sections_header',
            get_string('autoparts_sections', 'local_asyncwatch'));

        // Sections block (visible for 'section' and 'both').
        $sec_html  = '<div id="aw_sections_block">';
        $sec_html .= '<p class="text-muted small mb-2">'
                   . get_string('autoparts_sections_only_desc', 'local_asyncwatch') . '</p>';
        $sec_html .= '<div class="form-check mb-1">'
                   . '<input class="form-check-input" type="checkbox" id="aw_select_all_sections" checked>'
                   . '<label class="form-check-label font-weight-bold" for="aw_select_all_sections">'
                   . get_string('selectall') . '</label></div>';
        $sec_html .= '<div style="max-height:200px;overflow-y:auto;border:1px solid #dee2e6;border-radius:4px;padding:8px;">';
        foreach ($section_options as $sec_id => $sec_name) {
            $sec_html .= '<div class="form-check">'
                       . '<input class="form-check-input aw-section-cb" type="checkbox"'
                       . ' name="section_ids[]" value="' . (int)$sec_id . '"'
                       . ' id="aw_sec_' . $sec_id . '" checked>'
                       . '<label class="form-check-label" for="aw_sec_' . $sec_id . '">'
                       . s($sec_name) . '</label></div>';
        }
        $sec_html .= '</div></div>';
        $mform->addElement('static', 'section_list', '', $sec_html);

        // Subsections block (visible for 'subsection' and 'both').
        if ($has_subsections) {
            $sub_html  = '<div id="aw_subsections_block" style="margin-top:1rem;">';
            $sub_html .= '<p class="text-muted small mb-2">'
                       . get_string('autoparts_subsections_only_desc', 'local_asyncwatch') . '</p>';
            $sub_html .= '<div class="form-check mb-1">'
                       . '<input class="form-check-input" type="checkbox" id="aw_select_all_subsections" checked>'
                       . '<label class="form-check-label font-weight-bold" for="aw_select_all_subsections">'
                       . get_string('selectall') . '</label></div>';
            $sub_html .= '<div style="max-height:200px;overflow-y:auto;border:1px solid #dee2e6;border-radius:4px;padding:8px;">';
            $last_parent = null;
            foreach ($subsection_options as $sub_id => $sub) {
                if ($sub['parent'] !== $last_parent) {
                    $sub_html .= '<div class="text-muted small font-weight-bold mt-1 mb-1">'
                               . s($sub['parent']) . '</div>';
                    $last_parent = $sub['parent'];
                }
                $sub_html .= '<div class="form-check">'
                           . '<input class="form-check-input aw-subsection-cb" type="checkbox"'
                           . ' name="subsection_ids[]" value="' . (int)$sub_id . '"'
                           . ' id="aw_sub_' . $sub_id . '" checked>'
                           . '<label class="form-check-label" for="aw_sub_' . $sub_id . '">'
                           . s($sub['name']) . '</label></div>';
            }
            $sub_html .= '</div></div>';
            $mform->addElement('static', 'subsection_list', '', $sub_html);
        }

        // ── Activity types ────────────────────────────────────────────────────
        $mform->addElement('header', 'modtypes_header',
            get_string('autoparts_modtypes', 'local_asyncwatch'));

        $mform->addElement('static', 'modtypes_desc', '',
            \html_writer::tag('p',
                get_string('autoparts_modtypes_desc', 'local_asyncwatch'),
                ['class' => 'text-muted small mb-2']
            )
        );

        $mform->addElement('static', 'types_selectall', '',
            '<div class="form-check mb-1">'
          . '<input class="form-check-input" type="checkbox" id="aw_select_all_types" checked>'
          . '<label class="form-check-label font-weight-bold" for="aw_select_all_types">'
          . get_string('selectall') . '</label></div>'
        );

        $types_html = '<div class="d-flex flex-wrap" style="gap:0.5rem 2rem;">';
        foreach ($modtypes as $modtype) {
            $types_html .= '<div class="form-check">'
                         . '<input class="form-check-input aw-type-cb" type="checkbox"'
                         . ' name="modtypes[]" value="' . s($modtype) . '"'
                         . ' id="aw_type_' . $modtype . '" checked>'
                         . '<label class="form-check-label" for="aw_type_' . $modtype . '">'
                         . s(ucfirst($modtype)) . '</label></div>';
        }
        $types_html .= '</div>';
        $mform->addElement('static', 'type_list', '', $types_html);

        $this->add_action_buttons(true, get_string('autoparts_preview', 'local_asyncwatch'));
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (empty($data['mode'])) {
            $errors['mode'] = get_string('required');
        }
        return $errors;
    }
}
