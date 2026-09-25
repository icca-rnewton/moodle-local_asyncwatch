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

        // What the warning is based on — only meaningful once warning is
        // switched on at all, so hidden until warn_enabled is ticked.
        $mform->addElement('select', 'warn_mode', get_string('warn_mode', 'local_asyncwatch'), [
            'time'  => get_string('warn_mode_time',  'local_asyncwatch'),
            'parts' => get_string('warn_mode_parts', 'local_asyncwatch'),
        ]);
        $mform->setType('warn_mode', PARAM_ALPHA);
        $mform->setDefault('warn_mode', 'time');
        $mform->addHelpButton('warn_mode', 'warn_mode', 'local_asyncwatch');
        $mform->hideIf('warn_mode', 'warn_enabled', 'notchecked');

        // -- Time-based fields (unchanged behaviour; only the show/hide
        //    mechanism changed — see below) --
        $mform->addElement('text', 'warn_value', '', ['size' => 4]);
        $mform->setType('warn_value', PARAM_INT);
        $mform->setDefault('warn_value', 0);
        $mform->hideIf('warn_value', 'warn_enabled', 'notchecked');
        $mform->hideIf('warn_value', 'warn_mode', 'neq', 'time');

        $unit_options = [
            'hours' => get_string('warn_unit_hours', 'local_asyncwatch'),
            'days'  => get_string('warn_unit_days',  'local_asyncwatch'),
            'weeks' => get_string('warn_unit_weeks', 'local_asyncwatch'),
        ];
        $mform->addElement('select', 'warn_unit',
            get_string('warn_window', 'local_asyncwatch'), $unit_options);
        $mform->setType('warn_unit', PARAM_ALPHA);
        $mform->setDefault('warn_unit', 'hours');
        $mform->hideIf('warn_unit', 'warn_enabled', 'notchecked');
        $mform->hideIf('warn_unit', 'warn_mode', 'neq', 'time');

        // -- Parts-based fields --
        $mform->addElement('select', 'warn_parts_style',
            get_string('warn_parts_style', 'local_asyncwatch'), [
                'gap' => get_string('warn_parts_style_gap', 'local_asyncwatch'),
                'min' => get_string('warn_parts_style_min', 'local_asyncwatch'),
                'pct' => get_string('warn_parts_style_pct', 'local_asyncwatch'),
            ]);
        $mform->setType('warn_parts_style', PARAM_ALPHA);
        $mform->setDefault('warn_parts_style', 'gap');
        $mform->addHelpButton('warn_parts_style', 'warn_parts_style', 'local_asyncwatch');
        $mform->hideIf('warn_parts_style', 'warn_enabled', 'notchecked');
        $mform->hideIf('warn_parts_style', 'warn_mode', 'neq', 'parts');

        $mform->addElement('text', 'warn_parts_value',
            get_string('warn_parts_value', 'local_asyncwatch'), ['size' => 4]);
        $mform->setType('warn_parts_value', PARAM_INT);
        $mform->setDefault('warn_parts_value', 0);
        $mform->hideIf('warn_parts_value', 'warn_enabled', 'notchecked');
        $mform->hideIf('warn_parts_value', 'warn_mode', 'neq', 'parts');

        $mform->addElement('static', 'warn_parts_preview', '',
            '<div id="id_warn_parts_preview" class="text-muted small mt-1"></div>');
        $mform->hideIf('warn_parts_preview', 'warn_enabled', 'notchecked');
        $mform->hideIf('warn_parts_preview', 'warn_mode', 'neq', 'parts');

        // Live preview text — genuinely computed, not a simple show/hide,
        // so this is the one piece that needs real JS rather than hideIf().
        // Deliberately going through $PAGE->requires->js_amd_inline() and
        // NOT a raw <script> tag in a static element — the latter is
        // silently killed by Moodle's own DOM rebuild after page load and
        // was exactly the bug in this form's old warn_value/warn_unit
        // toggle, which hideIf() above has now replaced properly.
        global $PAGE;
        $tpl        = json_encode(get_string('warn_parts_preview_template',    'local_asyncwatch'));
        $needs_tpl  = json_encode(get_string('warn_parts_preview_needs_parts', 'local_asyncwatch'));
        $PAGE->requires->js_amd_inline("
require(['jquery'], function() {
    function computeGap(partsRequired, style, value) {
        value = Math.max(0, value);
        if (style === 'min') return Math.max(0, partsRequired - value);
        if (style === 'pct') return Math.max(0, Math.ceil(partsRequired * value / 100));
        return value;
    }
    function update() {
        var partsEl   = document.getElementById('id_parts_required');
        var styleEl   = document.getElementById('id_warn_parts_style');
        var valueEl   = document.getElementById('id_warn_parts_value');
        var previewEl = document.getElementById('id_warn_parts_preview');
        if (!partsEl || !styleEl || !valueEl || !previewEl) return;
        var partsRequired = parseInt(partsEl.value, 10) || 0;
        var value         = parseInt(valueEl.value, 10) || 0;
        if (partsRequired <= 0) {
            previewEl.textContent = {$needs_tpl};
            return;
        }
        var gap       = computeGap(partsRequired, styleEl.value, value);
        var threshold = Math.max(0, partsRequired - gap);
        previewEl.textContent = {$tpl}
            .replace('%%REQUIRED%%', partsRequired)
            .replace('%%GAP%%', gap)
            .replace('%%THRESHOLD%%', threshold);
    }
    function init() {
        ['id_parts_required', 'id_warn_parts_style', 'id_warn_parts_value', 'id_warn_mode', 'id_warn_enabled']
            .forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.addEventListener('change', update);
            });
        update();
    }
    window.addEventListener('load', function() { init(); setTimeout(init, 500); });
});
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
        $mform->addHelpButton('notify_staff_breach', 'notify_staff', 'local_asyncwatch');

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
        $mform->addHelpButton('notify_staff_warning', 'notify_staff', 'local_asyncwatch');

        // ── Additional staff recipients ───────────────────────────────────────
        $mform->addElement('header', 'extra_recipients_header',
            get_string('extra_recipients_header', 'local_asyncwatch'));

        $overseer_names = $this->_customdata['overseer_names'] ?? [];
        if (!empty($overseer_names)) {
            $mform->addElement('static', 'extra_recipients_overseers', get_string('extra_recipients_overseers', 'local_asyncwatch'),
                implode(', ', array_map('s', $overseer_names)));
        } else {
            $mform->addElement('static', 'extra_recipients_overseers', '',
                \html_writer::tag('p',
                    get_string('extra_recipients_no_overseers', 'local_asyncwatch'),
                    ['class' => 'text-muted small mb-2']
                )
            );
        }
        $mform->addElement('static', 'extra_recipients_desc', '',
            \html_writer::tag('p',
                get_string('extra_recipients_desc', 'local_asyncwatch'),
                ['class' => 'text-muted small mb-2']
            )
        );

        $extra_recipient_options = $this->_customdata['extra_recipient_options'] ?? [];
        $el = $mform->addElement('autocomplete', 'extra_recipient_ids',
            get_string('extra_recipients', 'local_asyncwatch'), $extra_recipient_options);
        $el->setMultiple(true);
        $mform->setType('extra_recipient_ids', PARAM_INT);
        $mform->addHelpButton('extra_recipient_ids', 'extra_recipients', 'local_asyncwatch');

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
            if (($data['warn_mode'] ?? 'time') === 'parts') {
                if ((int)($data['warn_parts_value'] ?? 0) < 1) {
                    $errors['warn_parts_value'] = get_string('warn_parts_value_required', 'local_asyncwatch');
                }
            } else {
                if ((int)($data['warn_value'] ?? 0) < 1) {
                    $errors['warn_value'] = get_string('warn_value_required', 'local_asyncwatch');
                }
            }
        }
        return $errors;
    }

    /**
     * Convert form fields → a single warn_hours integer for storage.
     */
    public static function warn_to_hours(array $formdata): int {
        if (empty($formdata['warn_enabled']) || ($formdata['warn_mode'] ?? 'time') === 'parts') {
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

    /**
     * Convert form fields → a single normalized warn_parts_gap integer for
     * storage, regardless of which input style (gap / minimum / percentage)
     * was actually used. $parts_required is the rule's own required-parts
     * count, needed to convert 'min' and 'pct' styles into a gap. Rounds
     * UP for percentage, per the deliberate choice that a warning should
     * err on triggering a little early rather than a little late.
     */
    public static function warn_to_parts_gap(array $formdata, int $parts_required): int {
        if (empty($formdata['warn_enabled']) || ($formdata['warn_mode'] ?? 'time') !== 'parts') {
            return 0;
        }
        $value = max(0, (int)($formdata['warn_parts_value'] ?? 0));
        switch ($formdata['warn_parts_style'] ?? 'gap') {
            case 'min':
                return max(0, $parts_required - $value);
            case 'pct':
                return max(0, (int)ceil($parts_required * $value / 100));
            default: // 'gap'
                return $value;
        }
    }

    /**
     * Convert a stored warn_parts_gap + warn_parts_style back to form field
     * values for editing — reconstructs the display value in whichever
     * style was last used, so re-opening the form shows the same style and
     * (up to percentage rounding) the same number the admin last entered,
     * rather than always falling back to raw gap terms.
     */
    public static function parts_gap_to_warn_fields(int $gap, string $style, int $parts_required): array {
        switch ($style) {
            case 'min':
                $value = max(0, $parts_required - $gap);
                break;
            case 'pct':
                $value = $parts_required > 0 ? (int)round($gap / $parts_required * 100) : 0;
                break;
            default:
                $style = 'gap';
                $value = $gap;
        }
        return ['warn_parts_style' => $style, 'warn_parts_value' => $value];
    }
}