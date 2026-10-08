<?php
/**
 * Moodle form for a cross-course rule's cohort deadline override.
 *
 * Mirrors override_form.php (per-course, group-based) exactly, but keyed on
 * cohort instead of course group. Its static warn_to_minutes()/
 * minutes_to_fields() helpers are reused here rather than duplicated.
 *
 * @package    local_asyncwatch
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_asyncwatch\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class global_override_form extends \moodleform {

    public function definition(): void {
        $mform   = $this->_form;
        $cohorts = $this->_customdata['cohorts']; // [cohortid => name]
        $ruleid  = $this->_customdata['ruleid'];

        $mform->addElement('hidden', 'ruleid', $ruleid);
        $mform->setType('ruleid', PARAM_INT);

        $mform->addElement('hidden', 'overrideid', 0);
        $mform->setType('overrideid', PARAM_INT);

        // Cohort selector.
        $cohort_label = $this->_customdata['cohort_label'] ?? get_string('globalrule_cohorts', 'local_asyncwatch');
        $mform->addElement('select', 'cohortid', $cohort_label, $cohorts);
        $mform->setType('cohortid', PARAM_INT);
        $mform->addRule('cohortid', null, 'required', null, 'client');

        // Deadline — Moodle's native date+time selector.
        $mform->addElement('date_time_selector', 'deadline', get_string('deadline', 'local_asyncwatch'));
        $mform->addRule('deadline', null, 'required', null, 'client');

        // Early warning — same pattern as override_form / rule_form.
        // Warning fields: time window, or for a parts-mode rule the At
        // Risk band — see override_form::add_warning_elements().
        \local_asyncwatch\form\override_form::add_warning_elements($mform, $this->_customdata['rule'] ?? null);

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    public function validation($data, $files): array {
        global $DB;
        $errors = parent::validation($data, $files);
        $errors = array_merge($errors,
            \local_asyncwatch\form\override_form::validate_warning($data, $this->_customdata['rule'] ?? null));

        // This form is shared between two different tables — cross-course
        // rule cohort overrides (globaloverrides.php) and per-course
        // cohort overrides (overrides.php) — so the caller has to say
        // which one it's validating against. Defaults to the cross-course
        // table since that was this form's original, primary use.
        $table = $this->_customdata['table'] ?? 'asyncwatch_global_rule_overrides';

        $ruleid     = (int)($data['ruleid']     ?? 0);
        $cohortid   = (int)($data['cohortid']   ?? 0);
        $overrideid = (int)($data['overrideid'] ?? 0);
        if ($ruleid && $cohortid) {
            $existing = $DB->get_record($table, ['ruleid' => $ruleid, 'cohortid' => $cohortid], 'id');
            if ($existing && (int)$existing->id !== $overrideid) {
                $errors['cohortid'] = get_string('override_duplicate', 'local_asyncwatch');
            }
        }

        return $errors;
    }
}