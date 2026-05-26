<?php
/**
 * Moodle form for adding/editing a Rule Set.
 *
 * @package    local_asyncwatch
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_asyncwatch\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

class ruleset_form extends \moodleform {

    public function definition(): void {
        $mform     = $this->_form;
        $all_rules = $this->_customdata['all_rules'];  // rule objects keyed by id
        $all_groups= $this->_customdata['all_groups']; // group objects keyed by id

        $mform->addElement('hidden', 'rulesetid', 0);
        $mform->setType('rulesetid', PARAM_INT);

        $mform->addElement('hidden', 'courseid', 0);
        $mform->setType('courseid', PARAM_INT);

        // ── Name ──────────────────────────────────────────────────────────────
        $mform->addElement('text', 'ruleset_name',
            get_string('rulesetname', 'local_asyncwatch'), ['size' => 60]);
        $mform->setType('ruleset_name', PARAM_TEXT);
        $mform->addRule('ruleset_name', null, 'required', null, 'client');

        // ── Rules ─────────────────────────────────────────────────────────────
        $mform->addElement('header', 'rules_header',
            get_string('ruleset_rules', 'local_asyncwatch'));

        $mform->addElement('static', 'rules_desc', '',
            \html_writer::tag('p',
                get_string('ruleset_rules_desc', 'local_asyncwatch'),
                ['class' => 'text-muted small mb-2']
            )
        );

        if (empty($all_rules)) {
            $mform->addElement('static', 'no_rules', '',
                \html_writer::tag('p',
                    get_string('norules', 'local_asyncwatch'),
                    ['class' => 'text-muted font-italic']
                )
            );
        } else {
            $rules_html = '<div style="max-height:220px;overflow-y:auto;border:1px solid #dee2e6;border-radius:4px;padding:8px;">';
            foreach ($all_rules as $rule) {
                $set_name = \local_asyncwatch\helper::get_rule_set_name_for_rule((int)$rule->id);
                $badge = $set_name
                    ? ' <span class="badge badge-info bg-info text-white" style="font-size:0.95em;">' . s($set_name) . '</span>'
                    : ' <span class="badge badge-secondary bg-secondary text-white" style="font-size:0.95em;">' . get_string('rule_global', 'local_asyncwatch') . '</span>';
                $rules_html .= '<div class="form-check">'
                             . '<input class="form-check-input" type="checkbox"'
                             . ' name="ruleset_rules[]" value="' . (int)$rule->id . '"'
                             . ' id="rs_rule_' . $rule->id . '">'
                             . '<label class="form-check-label" for="rs_rule_' . $rule->id . '">'
                             . s(format_string($rule->name)) . $badge
                             . '</label></div>';
            }
            $rules_html .= '</div>';
            $mform->addElement('static', 'rules_list', '', $rules_html);
        }

        // ── Groups ────────────────────────────────────────────────────────────
        $mform->addElement('header', 'groups_header',
            get_string('ruleset_groups', 'local_asyncwatch'));

        $mform->addElement('static', 'groups_desc', '',
            \html_writer::tag('p',
                get_string('ruleset_groups_desc', 'local_asyncwatch'),
                ['class' => 'text-muted small mb-2']
            )
        );

        if (empty($all_groups)) {
            $mform->addElement('static', 'no_groups', '',
                \html_writer::tag('p',
                    get_string('nogroups', 'core_group'),
                    ['class' => 'text-muted font-italic']
                )
            );
        } else {
            $groups_html = '<div style="max-height:220px;overflow-y:auto;border:1px solid #dee2e6;border-radius:4px;padding:8px;">';
            foreach ($all_groups as $group) {
                $groups_html .= '<div class="form-check">'
                              . '<input class="form-check-input" type="checkbox"'
                              . ' name="ruleset_groups[]" value="' . (int)$group->id . '"'
                              . ' id="rs_grp_' . $group->id . '">'
                              . '<label class="form-check-label" for="rs_grp_' . $group->id . '">'
                              . s(format_string($group->name))
                              . '</label></div>';
            }
            $groups_html .= '</div>';
            $mform->addElement('static', 'groups_list', '', $groups_html);
        }

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (empty(trim($data['ruleset_name'] ?? ''))) {
            $errors['ruleset_name'] = get_string('required');
        }
        return $errors;
    }
}
