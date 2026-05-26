<?php
/**
 * Moodle form for a group deadline override.
 *
 * @package    local_asyncwatch
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_asyncwatch\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class override_form extends \moodleform {

    public function definition(): void {
        $mform   = $this->_form;
        $groups  = $this->_customdata['groups'];  // [groupid => name]
        $ruleid  = $this->_customdata['ruleid'];

        $mform->addElement('hidden', 'ruleid', $ruleid);
        $mform->setType('ruleid', PARAM_INT);

        $mform->addElement('hidden', 'overrideid', 0);
        $mform->setType('overrideid', PARAM_INT);

        // Group selector.
        $mform->addElement('select', 'groupid',
            get_string('group'), $groups);
        $mform->setType('groupid', PARAM_INT);
        $mform->addRule('groupid', null, 'required', null, 'client');

        // Deadline — Moodle's native date+time selector.
        $mform->addElement('date_time_selector', 'deadline',
            get_string('deadline', 'local_asyncwatch'));
        $mform->addRule('deadline', null, 'required', null, 'client');

        // Early warning — same pattern as rule_form.
        $mform->addElement('advcheckbox', 'warn_enabled',
            get_string('warn_enabled', 'local_asyncwatch'), '');
        $mform->setDefault('warn_enabled', 0);

        $mform->addElement('text', 'warn_value', '', ['size' => 4]);
        $mform->setType('warn_value', PARAM_INT);
        $mform->setDefault('warn_value', 0);

        $unit_options = [
            'hours' => get_string('warn_unit_hours', 'local_asyncwatch'),
            'days'  => get_string('warn_unit_days',  'local_asyncwatch'),
            'weeks' => get_string('warn_unit_weeks', 'local_asyncwatch'),
        ];
        $mform->addElement('select', 'warn_unit',
            get_string('warn_window', 'local_asyncwatch'), $unit_options);
        $mform->setType('warn_unit', PARAM_ALPHA);
        $mform->setDefault('warn_unit', 'hours');

        // Inline JS to grey out warn fields when disabled.
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

    /**
     * Convert warn form fields to minutes for storage.
     */
    public static function warn_to_minutes(array $data): int {
        if (empty($data['warn_enabled'])) return 0;
        $val  = max(1, (int)($data['warn_value'] ?? 1));
        switch ($data['warn_unit'] ?? 'hours') {
            case 'weeks': return $val * 7 * 24 * 60;
            case 'days':  return $val * 24 * 60;
            default:      return $val * 60;
        }
    }

    /**
     * Convert stored minutes back to form fields for editing.
     */
    public static function minutes_to_fields(int $warn_minutes): array {
        if ($warn_minutes <= 0) return ['warn_enabled' => 0, 'warn_value' => 0, 'warn_unit' => 'hours'];
        if ($warn_minutes % (7*24*60) === 0) return ['warn_enabled' => 1, 'warn_value' => $warn_minutes/(7*24*60), 'warn_unit' => 'weeks'];
        if ($warn_minutes % (24*60)   === 0) return ['warn_enabled' => 1, 'warn_value' => $warn_minutes/(24*60),   'warn_unit' => 'days'];
        if ($warn_minutes % 60        === 0) return ['warn_enabled' => 1, 'warn_value' => $warn_minutes/60,         'warn_unit' => 'hours'];
        return ['warn_enabled' => 1, 'warn_value' => $warn_minutes, 'warn_unit' => 'hours'];
    }
}
