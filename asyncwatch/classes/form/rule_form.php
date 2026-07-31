<?php
/**
 * Form: create / edit a Rule.
 *
 * @package    local_asyncwatch
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_asyncwatch\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class rule_form extends \moodleform {

    public function definition(): void {
        $mform       = $this->_form;
        $courseid    = $this->_customdata['courseid'];
        $ruleid      = $this->_customdata['ruleid'] ?? 0;
        $total_parts = $this->_customdata['total_parts'];

        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'ruleid', $ruleid);
        $mform->setType('ruleid', PARAM_INT);

        // ── Rule name ────────────────────────────────────────────────────
        $mform->addElement('text', 'name', get_string('rulename', 'local_asyncwatch'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addHelpButton('name', 'rulename', 'local_asyncwatch');

        // ── Enable rule ──────────────────────────────────────────────────
        $mform->addElement('advcheckbox', 'enabled',
            get_string('rule_enabled', 'local_asyncwatch'), '');
        $mform->setDefault('enabled', 1);
        $mform->addHelpButton('enabled', 'rule_enabled', 'local_asyncwatch');


        // ── Rule Parameters ──────────────────────────────────────────────
        $mform->addElement('header', 'params_header',
            get_string('rule_params_header', 'local_asyncwatch'));

        // Parts required — standalone select to avoid group-cleaning issues.
        $options = [];
        for ($i = 1; $i <= max(1, $total_parts); $i++) {
            $options[$i] = $i . ' of ' . (int)$total_parts;
        }
        $mform->addElement('select', 'parts_required',
            get_string('parts_required', 'local_asyncwatch'), $options);
        $mform->setType('parts_required', PARAM_INT);
        $mform->addHelpButton('parts_required', 'parts_required', 'local_asyncwatch');

        // Deadline.
        $mform->addElement('date_time_selector', 'deadline',
            get_string('deadline', 'local_asyncwatch'));
        $mform->addHelpButton('deadline', 'deadline', 'local_asyncwatch');

        // Early-warning window — sits alongside deadline as it is relative to it.
        $mform->addElement('advcheckbox', 'warn_enabled',
            get_string('warn_enabled', 'local_asyncwatch'), '');
        $mform->setDefault('warn_enabled', 0);
        $mform->addHelpButton('warn_enabled', 'warn_window', 'local_asyncwatch');

        // warn_value and warn_unit are standalone (not in a group) to avoid
        // Moodle stripping them during form cleaning.
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

        // JS: grey out warn_value + warn_unit when warn_enabled is unchecked.
        // window.load is used (not DOMContentLoaded) so Moodle advcheckbox JS
        // has already run before we attach our listener.
        $mform->addElement('static', 'warn_js', '', "
<script>
(function() {
    function toggleWarn() {
        var cb   = document.getElementById('id_warn_enabled');
        var val  = document.getElementById('id_warn_value');
        var unit = document.getElementById('id_warn_unit');
        if (!cb || !val || !unit) return;
        var on = cb.checked;
        val.disabled  = !on;
        unit.disabled = !on;
        val.style.opacity        = on ? '' : '0.4';
        unit.style.opacity       = on ? '' : '0.4';
        val.style.pointerEvents  = on ? '' : 'none';
        unit.style.pointerEvents = on ? '' : 'none';
    }
    function init() {
        var cb = document.getElementById('id_warn_enabled');
        if (cb) { cb.addEventListener('change', toggleWarn); toggleWarn(); }
    }
    window.addEventListener('load', function() { init(); setTimeout(init, 500); });
})();
</script>
        ");

        // ── Notifications ────────────────────────────────────────────────
        $mform->addElement('header', 'notify_header',
            get_string('notify_header', 'local_asyncwatch'));

        // Breach notifications.
        $mform->addElement('static', 'notify_breach_label', '',
            '<strong>' . get_string('notify_breach_heading', 'local_asyncwatch') . '</strong> '
            . '<span class=\"text-muted small\">' . get_string('notify_breach_desc', 'local_asyncwatch') . '</span>');
        $mform->addElement('advcheckbox', 'notify_learner_breach',
            get_string('notify_learner', 'local_asyncwatch'), '');
        $mform->setDefault('notify_learner_breach', 0);
        $mform->addElement('advcheckbox', 'notify_staff_breach',
            get_string('notify_staff', 'local_asyncwatch'), '');
        $mform->setDefault('notify_staff_breach', 0);

        // Warning notifications.
        $mform->addElement('static', 'notify_warn_label', '',
            '<strong>' . get_string('notify_warning_heading', 'local_asyncwatch') . '</strong> '
            . '<span class=\"text-muted small\">' . get_string('notify_warning_desc', 'local_asyncwatch') . '</span>');
        $mform->addElement('advcheckbox', 'notify_learner_warning',
            get_string('notify_learner', 'local_asyncwatch'), '');
        $mform->setDefault('notify_learner_warning', 0);
        $mform->addElement('advcheckbox', 'notify_staff_warning',
            get_string('notify_staff', 'local_asyncwatch'), '');
        $mform->setDefault('notify_staff_warning', 0);

        // ── Profile field sync ──────────────────────────────────────────────
        $mform->addElement('header', 'profilefield_header',
            get_string('profilefield_header', 'local_asyncwatch'));
        $mform->addElement('static', 'profilefield_desc', '',
            \html_writer::tag('p',
                get_string('profilefield_desc', 'local_asyncwatch'),
                ['class' => 'text-muted small mb-2']
            )
        );
        $field_options = ['' => get_string('profilefield_none', 'local_asyncwatch')]
            + ($this->_customdata['profile_field_options'] ?? []);
        $mform->addElement('select', 'profilefield',
            get_string('profilefield', 'local_asyncwatch'), $field_options);
        $mform->setType('profilefield', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('profilefield', 'profilefield', 'local_asyncwatch');

        // ── Restrictions ─────────────────────────────────────────────────────
        $mform->addElement('header', 'restrict_header',
            get_string('restrict_header', 'local_asyncwatch'));
        $mform->addElement('static', 'restrict_desc', '',
            \html_writer::tag('p',
                get_string('restrict_desc', 'local_asyncwatch'),
                ['class' => 'text-muted small mb-2']
            )
        );

        $group_options = $this->_customdata['group_options'] ?? [];
        $el = $mform->addElement('autocomplete', 'restrict_groupids',
            get_string('restrict_groups', 'local_asyncwatch'), $group_options);
        $el->setMultiple(true);
        $mform->setType('restrict_groupids', PARAM_INT);

        $cohort_options = $this->_customdata['cohort_options'] ?? [];
        $el = $mform->addElement('autocomplete', 'restrict_cohortids',
            get_string('restrict_cohorts', 'local_asyncwatch'), $cohort_options);
        $el->setMultiple(true);
        $mform->setType('restrict_cohortids', PARAM_INT);

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    public function validation($data, $files): array {
        global $DB;
        $errors = parent::validation($data, $files);

        if (empty(trim($data['name'] ?? ''))) {
            $errors['name'] = get_string('required');
        } else {
            // Prevent duplicate rule names within the same course.
            $courseid = (int)($data['courseid'] ?? 0);
            $ruleid   = (int)($data['ruleid']   ?? 0);
            $existing = $DB->get_record('asyncwatch_rules', [
                'courseid' => $courseid,
                'name'     => trim($data['name']),
            ]);
            if ($existing && (int)$existing->id !== $ruleid) {
                $errors['name'] = get_string('rule_name_duplicate', 'local_asyncwatch');
            }
        }

        if (!empty($data['warn_enabled'])) {
            if ((int)($data['warn_value'] ?? 0) < 1) {
                $errors['warn_value'] = get_string('warn_value_required', 'local_asyncwatch');
            }
        }
        return $errors;
    }

    /**
     * Convert form fields → a single warn_hours integer for storage.
     */
    public static function warn_to_hours(array $formdata): int {
        if (empty($formdata['warn_enabled'])) {
            return 0;
        }
        $val  = max(1, (int)($formdata['warn_value'] ?? 1));
        $unit = $formdata['warn_unit'] ?? 'hours';
        // Stored as minutes internally.
        switch ($unit) {
            case 'weeks':   return $val * 7 * 24 * 60;
            case 'days':    return $val * 24 * 60;
            case 'hours':   return $val * 60;
            default:        return $val; // minutes
        }
    }

    /**
     * Convert stored warn_hours back to form field values for editing.
     */
    public static function hours_to_warn_fields(int $warn_minutes): array {
        // Value is stored as minutes. Returns flat fields (no group nesting).
        if ($warn_minutes <= 0) {
            return ['warn_enabled' => 0, 'warn_value' => 3, 'warn_unit' => 'days'];
        }
        $week_mins = 7 * 24 * 60;
        $day_mins  = 24 * 60;
        $hour_mins = 60;
        if ($warn_minutes % $week_mins === 0) {
            return ['warn_enabled' => 1, 'warn_value' => $warn_minutes / $week_mins, 'warn_unit' => 'weeks'];
        }
        if ($warn_minutes % $day_mins === 0) {
            return ['warn_enabled' => 1, 'warn_value' => $warn_minutes / $day_mins,  'warn_unit' => 'days'];
        }
        if ($warn_minutes % $hour_mins === 0) {
            return ['warn_enabled' => 1, 'warn_value' => $warn_minutes / $hour_mins, 'warn_unit' => 'hours'];
        }
        return ['warn_enabled' => 1, 'warn_value' => $warn_minutes, 'warn_unit' => 'minutes'];
    }

    /**
     * @deprecated Use hours_to_warn_fields() instead.
     */
    public static function hours_to_warn_group(int $warn_hours): array {
        return self::hours_to_warn_fields($warn_hours)['warn_group'];
    }
}