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

        // ── Rule Set assignment ──────────────────────────────────────────────
        $mform->addElement('header', 'ruleset_header',
            get_string('ruleset_assignment_header', 'local_asyncwatch'));

        $mform->addElement('static', 'ruleset_global_note', '',
            \html_writer::tag('p',
                get_string('ruleset_global_note', 'local_asyncwatch'),
                ['class' => 'text-muted small mb-2']
            )
        );

        // Hidden field that Moodle's form processor reads on submit.
        // Kept before the widget so the visible select (which posts last) wins
        // in practice, but we also sync it via JS to be safe.
        $mform->addElement('hidden', 'rulesetid', 0);
        $mform->setType('rulesetid', PARAM_INT);

        // Rule Set selector + inline "New rule set" creator.
        // Rendered as a static HTML block so we can place the "+ New" button
        // right next to the select without fighting moodleform's layout.
        $courseid_for_sets = (int)($this->_customdata['courseid'] ?? 0);
        $existing_sets     = $courseid_for_sets
            ? \local_asyncwatch\helper::get_rule_sets($courseid_for_sets)
            : [];

        $current_rulesetid = (int)($this->_customdata['current_rulesetid'] ?? 0);

        $opts_html = '<option value="0">' . s(get_string('ruleset_none', 'local_asyncwatch')) . '</option>';
        foreach ($existing_sets as $rs) {
            $sel = ((int)$rs->id === $current_rulesetid) ? ' selected' : '';
            $opts_html .= '<option value="' . (int)$rs->id . '"' . $sel . '>' . s(format_string($rs->name)) . '</option>';
        }

        $ajax_url = (new \moodle_url('/local/asyncwatch/ajax_create_ruleset.php'))->out(false);

        $ruleset_html = '
<div id="aw-ruleset-wrapper">
  <div class="d-flex align-items-center" style="gap:0.5rem;flex-wrap:wrap;">
    <select id="aw-rulesetid-vis" class="custom-select" style="width:auto;min-width:200px;">'
      . $opts_html .
    '</select>
    <button type="button" id="aw-new-ruleset-btn" class="btn btn-secondary">+ New rule set</button>
  </div>

  <!-- Inline create form — hidden until button clicked -->
  <div id="aw-new-ruleset-form" style="display:none;margin-top:0.6rem;padding:0.75rem 1rem;background:#f8f9fa;border:1px solid #dee2e6;border-radius:4px;max-width:420px;">
    <label class="font-weight-bold small mb-1 d-block" for="aw-new-ruleset-name">New rule set name</label>
    <div class="d-flex align-items-center" style="gap:0.5rem;">
      <input type="text" id="aw-new-ruleset-name" class="form-control form-control-sm" placeholder="e.g. Group A rules" maxlength="255" style="flex:1;">
      <button type="button" id="aw-new-ruleset-save" class="btn btn-primary">Save</button>
      <button type="button" id="aw-new-ruleset-cancel" class="btn btn-secondary">Cancel</button>
    </div>
    <div id="aw-new-ruleset-error" class="text-danger small mt-1" style="display:none;"></div>
  </div>
</div>

<script>
(function() {
    var ajaxUrl = ' . json_encode($ajax_url) . ';
    var sesskey = ' . json_encode(sesskey()) . ';
    var courseid = ' . (int)$courseid_for_sets . ';

    function byId(id) { return document.getElementById(id); }

    document.addEventListener("DOMContentLoaded", function() {
        var visSelect = byId("aw-rulesetid-vis");
        var hidField  = byId("id_rulesetid");
        var btn       = byId("aw-new-ruleset-btn");
        var form      = byId("aw-new-ruleset-form");
        var nameInp   = byId("aw-new-ruleset-name");
        var saveBtn   = byId("aw-new-ruleset-save");
        var cancelBtn = byId("aw-new-ruleset-cancel");
        var errDiv    = byId("aw-new-ruleset-error");
        if (!btn || !visSelect) return;

        // Keep hidden field in sync so Moodle form processor gets the value.
        function syncHidden() {
            if (hidField) hidField.value = visSelect.value;
        }
        visSelect.addEventListener("change", syncHidden);
        syncHidden(); // sync on load

        btn.addEventListener("click", function() {
            form.style.display = "";
            nameInp.value = "";
            errDiv.style.display = "none";
            nameInp.focus();
        });

        cancelBtn.addEventListener("click", function() {
            form.style.display = "none";
        });

        nameInp.addEventListener("keydown", function(e) {
            if (e.key === "Enter")  { e.preventDefault(); saveBtn.click(); }
            if (e.key === "Escape") { cancelBtn.click(); }
        });

        saveBtn.addEventListener("click", function() {
            var name = nameInp.value.trim();
            if (!name) {
                errDiv.textContent = "Please enter a name.";
                errDiv.style.display = "";
                nameInp.focus();
                return;
            }

            saveBtn.disabled    = true;
            saveBtn.textContent = "Saving\u2026";
            errDiv.style.display = "none";

            fetch(ajaxUrl, {
                method: "POST",
                body: new URLSearchParams({ courseid: courseid, name: name, sesskey: sesskey })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.error) {
                    errDiv.textContent   = data.error;
                    errDiv.style.display = "";
                    saveBtn.disabled     = false;
                    saveBtn.textContent  = "Save";
                    return;
                }
                // Add option and select it in both visible + hidden.
                var opt = document.createElement("option");
                opt.value       = data.id;
                opt.textContent = data.name;
                visSelect.appendChild(opt);
                visSelect.value = data.id;
                syncHidden();

                // Tidy up.
                form.style.display  = "none";
                saveBtn.disabled    = false;
                saveBtn.textContent = "Save";

                // Brief ✓ confirmation.
                var tick = document.createElement("span");
                tick.textContent = "\u2713 Created";
                tick.className   = "text-success small ml-2";
                tick.style.transition = "opacity 1s";
                btn.parentNode.appendChild(tick);
                setTimeout(function() { tick.style.opacity = "0"; }, 1500);
                setTimeout(function() { tick.remove(); },            2600);
            })
            .catch(function() {
                errDiv.textContent   = "An error occurred. Please try again.";
                errDiv.style.display = "";
                saveBtn.disabled     = false;
                saveBtn.textContent  = "Save";
            });
        });
    });
})();
</script>';

        $mform->addElement('static', 'rulesetid_widget',
            get_string('ruleset_assign', 'local_asyncwatch'),
            $ruleset_html
        );

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
