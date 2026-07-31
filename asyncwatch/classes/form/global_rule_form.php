<?php
/**
 * Form: create / edit a cross-course rule.
 *
 * Lives outside any course context — courses are picked explicitly rather
 * than assumed from the page. Deadline/warn-window handling mirrors
 * rule_form.php exactly (same static warn_to_hours()/hours_to_warn_fields()
 * helpers are reused from there rather than duplicated).
 *
 * @package    local_asyncwatch
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_asyncwatch\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class global_rule_form extends \moodleform {

    public function definition(): void {
        $mform   = $this->_form;
        $ruleid  = $this->_customdata['ruleid'] ?? 0;
        $courses = $this->_customdata['courses_with_parts']; // [courseid => stdClass{id,coursename,partcount}]
        $cohorts = $this->_customdata['cohorts']; // [cohortid => name]

        $mform->addElement('hidden', 'ruleid', $ruleid);
        $mform->setType('ruleid', PARAM_INT);

        // ── Rule name ────────────────────────────────────────────────────
        $mform->addElement('text', 'name', get_string('rulename', 'local_asyncwatch'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // ── Enable rule ──────────────────────────────────────────────────
        $mform->addElement('advcheckbox', 'enabled', get_string('rule_enabled', 'local_asyncwatch'), '');
        $mform->setDefault('enabled', 1);

        // ── Courses ──────────────────────────────────────────────────────
        $mform->addElement('header', 'courses_header', get_string('globalrule_courses', 'local_asyncwatch'));
        $mform->addElement('static', 'courses_desc', '',
            \html_writer::tag('p', get_string('globalrule_courses_desc', 'local_asyncwatch'), ['class' => 'text-muted small mb-2'])
        );

        $course_options = [];
        $partcounts_js  = [];
        foreach ($courses as $c) {
            $course_options[(int)$c->id] = format_string($c->coursename)
                . ' (' . $c->partcount . ' ' . ($c->partcount == 1 ? get_string('part', 'local_asyncwatch') : get_string('parts', 'local_asyncwatch')) . ')';
            $partcounts_js[(int)$c->id] = (int)$c->partcount;
        }
        if (empty($course_options)) {
            $mform->addElement('static', 'courses_empty', '',
                \html_writer::tag('div', get_string('globalrule_nocourses', 'local_asyncwatch'), ['class' => 'alert alert-warning'])
            );
        }
        $el = $mform->addElement('autocomplete', 'courseids', get_string('globalrule_courses', 'local_asyncwatch'), $course_options);
        $el->setMultiple(true);
        $mform->setType('courseids', PARAM_INT);
        $mform->addRule('courseids', null, 'required', null, 'client');

        // Live "total parts in selected courses" hint + a soft max on the
        // parts-required field below. Purely a UX aid — the real limit is
        // enforced server-side in validation().
        $mform->addElement('static', 'courses_total_hint', '',
            '<div class="text-muted small mt-1">'
            . s(get_string('globalrule_totalparts_prefix', 'local_asyncwatch'))
            . ' <span id="aw-courses-total">0</span></div>'
        );

        // ── Rule parameters ─────────────────────────────────────────────
        $mform->addElement('header', 'params_header', get_string('rule_params_header', 'local_asyncwatch'));

        $mform->addElement('text', 'parts_required', get_string('parts_required', 'local_asyncwatch'), ['size' => 6]);
        $mform->setType('parts_required', PARAM_INT);
        $mform->addRule('parts_required', null, 'required', null, 'client');
        $mform->addElement('static', 'parts_required_help', '',
            \html_writer::tag('p', get_string('globalrule_partsrequired_desc', 'local_asyncwatch'), ['class' => 'text-muted small'])
        );

        $mform->addElement('date_time_selector', 'deadline', get_string('deadline', 'local_asyncwatch'));

        // Early-warning window — identical UI/logic to rule_form.php.
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

        // Live total-parts hint, driven by the course autocomplete's
        // underlying <select multiple>, which Moodle keeps in sync and
        // fires 'change' on as items are added/removed.
        $mform->addElement('static', 'courses_total_js', '', '
<script>
(function() {
    var partcounts = ' . json_encode($partcounts_js) . ';
    function update() {
        var sel  = document.getElementById("id_courseids");
        var span = document.getElementById("aw-courses-total");
        if (!sel || !span) return;
        var total = 0;
        for (var i = 0; i < sel.options.length; i++) {
            if (sel.options[i].selected) {
                total += partcounts[sel.options[i].value] || 0;
            }
        }
        span.textContent = total;
    }
    function init() {
        var sel = document.getElementById("id_courseids");
        if (sel) { sel.addEventListener("change", update); update(); }
    }
    window.addEventListener("load", function() { init(); setTimeout(init, 700); });
})();
</script>
        ');

        // ── Notifications ────────────────────────────────────────────────
        $mform->addElement('header', 'notify_header', get_string('notify_header', 'local_asyncwatch'));

        $mform->addElement('static', 'notify_breach_label', '',
            '<strong>' . get_string('notify_breach_heading', 'local_asyncwatch') . '</strong> '
            . '<span class="text-muted small">' . get_string('notify_breach_desc', 'local_asyncwatch') . '</span>');
        $mform->addElement('advcheckbox', 'notify_learner_breach', get_string('notify_learner', 'local_asyncwatch'), '');
        $mform->setDefault('notify_learner_breach', 0);
        $mform->addElement('advcheckbox', 'notify_staff_breach', get_string('notify_staff', 'local_asyncwatch'), '');
        $mform->setDefault('notify_staff_breach', 0);

        $mform->addElement('static', 'notify_warn_label', '',
            '<strong>' . get_string('notify_warning_heading', 'local_asyncwatch') . '</strong> '
            . '<span class="text-muted small">' . get_string('notify_warning_desc', 'local_asyncwatch') . '</span>');
        $mform->addElement('advcheckbox', 'notify_learner_warning', get_string('notify_learner', 'local_asyncwatch'), '');
        $mform->setDefault('notify_learner_warning', 0);
        $mform->addElement('advcheckbox', 'notify_staff_warning', get_string('notify_staff', 'local_asyncwatch'), '');
        $mform->setDefault('notify_staff_warning', 0);

        $mform->addElement('static', 'notify_wording_note', '',
            \html_writer::tag('p', get_string('globalrule_wording_note', 'local_asyncwatch'), ['class' => 'text-muted small mt-2'])
        );

        // ── Cohort targeting ─────────────────────────────────────────────
        $mform->addElement('header', 'cohorts_header', get_string('globalrule_cohorts', 'local_asyncwatch'));
        $mform->addElement('static', 'cohorts_desc', '',
            \html_writer::tag('p', get_string('globalrule_cohorts_desc', 'local_asyncwatch'), ['class' => 'text-muted small mb-2'])
        );
        $el = $mform->addElement('autocomplete', 'cohortids', get_string('globalrule_cohorts', 'local_asyncwatch'), $cohorts);
        $el->setMultiple(true);
        $mform->setType('cohortids', PARAM_INT);

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if (empty(trim($data['name'] ?? ''))) {
            $errors['name'] = get_string('required');
        }

        $courseids = array_map('intval', (array)($data['courseids'] ?? []));
        if (empty($courseids)) {
            $errors['courseids'] = get_string('globalrule_courses_required', 'local_asyncwatch');
        }

        $parts_required = (int)($data['parts_required'] ?? 0);
        if ($parts_required < 1) {
            $errors['parts_required'] = get_string('globalrule_partsrequired_min', 'local_asyncwatch');
        } elseif (!empty($courseids)) {
            $max = \local_asyncwatch\helper::total_parts_for_courses($courseids);
            if ($max > 0 && $parts_required > $max) {
                $errors['parts_required'] = get_string('globalrule_partsrequired_max', 'local_asyncwatch', $max);
            }
        }

        if (!empty($data['warn_enabled'])) {
            if ((int)($data['warn_value'] ?? 0) < 1) {
                $errors['warn_value'] = get_string('warn_value_required', 'local_asyncwatch');
            }
        }

        return $errors;
    }
}