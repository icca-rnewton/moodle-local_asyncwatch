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
        $mform->addElement('advcheckbox', 'warn_enabled', get_string('warn_enabled', 'local_asyncwatch'), '');
        $mform->setDefault('warn_enabled', 0);

        $mform->addElement('text', 'warn_value', '', ['size' => 4]);
        $mform->setType('warn_value', PARAM_INT);
        $mform->setDefault('warn_value', 0);

        $unit_options = [
            'hours' => get_string('warn_unit_hours', 'local_asyncwatch'),
            'days'  => get_string('warn_unit_days',  'local_asyncwatch'),
            'weeks' => get_string('warn_unit_weeks', 'local_asyncwatch'),
        ];
        $mform->addElement('select', 'warn_unit', get_string('warn_window', 'local_asyncwatch'), $unit_options);
        $mform->setType('warn_unit', PARAM_ALPHA);
        $mform->setDefault('warn_unit', 'hours');

        $mform->addElement('static', 'warn_js', '', "
<script>
(function() {
    function toggleWarn() {
        var cb   = document.getElementById('id_warn_enabled');
        var val  = document.getElementById('id_warn_value');
        var unit = document.getElementById('id_warn_unit');
        if (!cb || !val || !unit) return;
        var on = cb.checked;
        val.disabled  = !on; val.style.opacity  = on ? '' : '0.4';
        unit.disabled = !on; unit.style.opacity = on ? '' : '0.4';
        val.style.pointerEvents  = on ? '' : 'none';
        unit.style.pointerEvents = on ? '' : 'none';
    }
    window.addEventListener('load', function() {
        var cb = document.getElementById('id_warn_enabled');
        if (cb) { cb.addEventListener('change', toggleWarn); toggleWarn(); }
        setTimeout(toggleWarn, 500);
    });
})();
</script>
        ");

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if (!empty($data['warn_enabled']) && (int)($data['warn_value'] ?? 0) < 1) {
            $errors['warn_value'] = get_string('warn_value_required', 'local_asyncwatch');
        }
        return $errors;
    }
}