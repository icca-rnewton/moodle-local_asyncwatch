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

        // The DB's unique (ruleid, groupid) constraint already prevents a
        // duplicate override outright — this catches it earlier, as a
        // normal form error, instead of a raw database exception.
        $ruleid     = (int)($data['ruleid']     ?? 0);
        $groupid    = (int)($data['groupid']    ?? 0);
        $overrideid = (int)($data['overrideid'] ?? 0);
        if ($ruleid && $groupid) {
            $existing = $DB->get_record('asyncwatch_rule_overrides', ['ruleid' => $ruleid, 'groupid' => $groupid], 'id');
            if ($existing && (int)$existing->id !== $overrideid) {
                $errors['groupid'] = get_string('override_duplicate', 'local_asyncwatch');
            }
        }

        return $errors;
    }

    /**
     * The override's warning fields, shared by the group override form and
     * the cohort override form (course cohort overrides and cross-course
     * overrides both use global_override_form).
     *
     * Time-mode rule (or no rule passed): the original early-warning
     * window fields. Parts-mode rule: no time fields at all — instead an
     * optional At Risk band in the same input style as the rule itself.
     * Left unticked, the override inherits the rule's own band.
     */
    public static function add_warning_elements(\MoodleQuickForm $mform, ?\stdClass $rule): void {
        if ($rule && ($rule->warn_mode ?? 'time') === 'parts') {
            $style = in_array($rule->warn_parts_style ?? 'gap', ['gap', 'min', 'pct'], true)
                ? $rule->warn_parts_style : 'gap';
            $mform->addElement('advcheckbox', 'parts_override',
                get_string('override_parts_enabled', 'local_asyncwatch'), '');
            $mform->setDefault('parts_override', 0);
            $mform->addHelpButton('parts_override', 'override_parts_enabled', 'local_asyncwatch');
            $mform->addElement('static', 'parts_rule_band', '',
                \html_writer::tag('span', get_string('override_parts_rule_band', 'local_asyncwatch',
                    \local_asyncwatch\helper::format_warn_display($rule)), ['class' => 'text-muted small']));
            $mform->addElement('text', 'warn_parts_value',
                get_string('warn_parts_style_' . $style, 'local_asyncwatch'), ['size' => 4]);
            $mform->setType('warn_parts_value', PARAM_INT);
            $mform->setDefault('warn_parts_value', 0);
            $mform->hideIf('warn_parts_value', 'parts_override', 'notchecked');
            return;
        }

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

            // Inline JS to grey out warn fields when disabled. Goes through
            // js_amd_inline() rather than a raw <script> tag in a static
            // element — the latter is silently killed by Moodle's own DOM
            // rebuild after page load (confirmed broken in this exact form
            // and fixed here; same bug also found and fixed in rule_form.php,
            // global_rule_form.php, global_override_form.php, part_form.php).
            global $PAGE;
            $PAGE->requires->js_amd_inline("
require(['jquery'], function() {
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
});
            ");
    }

    /**
     * Validation for add_warning_elements()'s fields.
     */
    public static function validate_warning(array $data, ?\stdClass $rule): array {
        $errors = [];
        if ($rule && ($rule->warn_mode ?? 'time') === 'parts') {
            if (empty($data['parts_override'])) {
                return $errors;
            }
            $required = (int)$rule->parts_required;
            if ((int)($data['warn_parts_value'] ?? 0) < 1) {
                $errors['warn_parts_value'] = get_string('warn_parts_value_required', 'local_asyncwatch');
                return $errors;
            }
            // Same range rule as the rule form: a band of at least 1 part
            // that still leaves room for Behind below it.
            $gap = self::parts_override_to_gap($data, $rule);
            if ($gap === null || $gap < 1 || $gap >= $required) {
                $errors['warn_parts_value'] = get_string('warn_parts_gap_range', 'local_asyncwatch',
                    (object)['gap' => (int)$gap, 'required' => $required]);
            }
            return $errors;
        }
        if (!empty($data['warn_enabled']) && (int)($data['warn_value'] ?? 0) < 1) {
            $errors['warn_value'] = get_string('warn_value_required', 'local_asyncwatch');
        }
        return $errors;
    }

    /**
     * Form data → stored override band. Null = no band of its own
     * (inherit the rule's). Uses the rule's own input style and the same
     * conversion as the rule form, so "minimum 7" means the same thing on
     * a rule and on its overrides.
     */
    public static function parts_override_to_gap(array $data, \stdClass $rule): ?int {
        if (empty($data['parts_override'])) {
            return null;
        }
        return rule_form::warn_to_parts_gap([
            'warn_enabled'     => 1,
            'warn_mode'        => 'parts',
            'warn_parts_style' => $rule->warn_parts_style ?? 'gap',
            'warn_parts_value' => (int)($data['warn_parts_value'] ?? 0),
        ], (int)$rule->parts_required);
    }

    /**
     * Stored override band → form fields for editing.
     */
    public static function gap_to_parts_override_fields($gap, \stdClass $rule): array {
        if ($gap === null || $gap === '') {
            return ['parts_override' => 0, 'warn_parts_value' => 0];
        }
        $fields = rule_form::parts_gap_to_warn_fields((int)$gap,
            $rule->warn_parts_style ?? 'gap', (int)$rule->parts_required);
        return ['parts_override' => 1, 'warn_parts_value' => $fields['warn_parts_value']];
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