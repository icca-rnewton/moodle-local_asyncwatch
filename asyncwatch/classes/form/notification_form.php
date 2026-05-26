<?php
/**
 * Moodle form for AsyncWatch notification templates.
 *
 * Uses native Moodle editor (Atto) for template bodies and native
 * autocomplete for staff recipient selection.
 *
 * @package    local_asyncwatch
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_asyncwatch\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class notification_form extends \moodleform {

    public function definition(): void {
        $mform    = $this->_form;
        $courseid = $this->_customdata['courseid'];
        $users    = $this->_customdata['users']; // [userid => display string]

        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        $editor_opts = [
            'maxfiles' => 0,
            'maxbytes' => 0,
            'context'  => \context_course::instance($courseid),
        ];

        // Placeholder reference block — shown after each body editor.
        $ph_html = '<div class="alert alert-info py-2 px-3 mt-1 mb-2" style="font-size:0.875em;">'
                 . '<strong>' . get_string('ph_available', 'local_asyncwatch') . '</strong> &nbsp;'
                 . implode(' &nbsp; ', array_map(function($k, $v) {
                       return '<code>' . htmlspecialchars($k, ENT_QUOTES) . '</code> <span class="text-muted">— ' . htmlspecialchars($v, ENT_QUOTES) . '</span>';
                   }, [
                       '{{firstname}}', '{{lastname}}', '{{fullname}}', '{{email}}',
                       '{{coursename}}', '{{parts_done}}', '{{parts_required}}',
                       '{{deadline}}', '{{rulename}}', '{{sitename}}',
                   ], [
                       get_string('ph_firstname',     'local_asyncwatch'),
                       get_string('ph_lastname',      'local_asyncwatch'),
                       get_string('ph_fullname',      'local_asyncwatch'),
                       get_string('ph_email',         'local_asyncwatch'),
                       get_string('ph_coursename',    'local_asyncwatch'),
                       get_string('ph_parts_done',    'local_asyncwatch'),
                       get_string('ph_parts_required','local_asyncwatch'),
                       get_string('ph_deadline',      'local_asyncwatch'),
                       get_string('ph_rulename',      'local_asyncwatch'),
                       get_string('ph_sitename',      'local_asyncwatch'),
                   ]))
                 . '</div>';

        // Helper closure to add a subject + editor pair.
        $add_template = function(string $subj_name, string $body_name, string $header_label) use ($mform, $editor_opts, $ph_html) {
            $mform->addElement('header', $body_name . '_header', $header_label);

            $mform->addElement('text', $subj_name,
                get_string('email_subject', 'local_asyncwatch'), ['size' => 80]);
            $mform->setType($subj_name, PARAM_TEXT);

            $mform->addElement('editor', $body_name . '_editor',
                get_string('email_body', 'local_asyncwatch'), null, $editor_opts);
            $mform->setType($body_name . '_editor', PARAM_RAW);

            $mform->addElement('static', $body_name . '_ph', '', $ph_html);
        };

        // ── 1. Behind — Learner ───────────────────────────────────────────────
        $add_template(
            'learner_subject',
            'learner_body',
            get_string('notify_breach_heading', 'local_asyncwatch') . ' — ' . get_string('tpl_learner_heading', 'local_asyncwatch')
        );

        // ── 2. Behind — Staff ─────────────────────────────────────────────────
        $add_template(
            'staff_subject',
            'staff_body',
            get_string('notify_breach_heading', 'local_asyncwatch') . ' — ' . get_string('tpl_staff_heading', 'local_asyncwatch')
        );

        // ── 3. At Risk — Learner ──────────────────────────────────────────────
        $add_template(
            'learner_warning_subject',
            'learner_warning_body',
            get_string('notify_warning_heading', 'local_asyncwatch') . ' — ' . get_string('tpl_learner_heading', 'local_asyncwatch')
        );

        // ── 4. At Risk — Staff ────────────────────────────────────────────────
        $add_template(
            'staff_warning_subject',
            'staff_warning_body',
            get_string('notify_warning_heading', 'local_asyncwatch') . ' — ' . get_string('tpl_staff_heading', 'local_asyncwatch')
        );

        // ── Staff recipients ──────────────────────────────────────────────────
        $mform->addElement('header', 'recipients_header',
            get_string('staff_recipients', 'local_asyncwatch'));

        $mform->addElement('static', 'recipients_desc', '',
            \html_writer::tag('p',
                get_string('staff_recipients_desc', 'local_asyncwatch'),
                ['class' => 'text-muted mb-2']
            )
        );

        $el = $mform->addElement('autocomplete', 'staff_recipients_ids',
            get_string('staff_recipients', 'local_asyncwatch'), $users);
        $el->setMultiple(true);
        $mform->setType('staff_recipients_ids', PARAM_INT);

        $this->add_action_buttons(true, get_string('savechanges'));
    }

    public function validation($data, $files): array {
        return parent::validation($data, $files);
    }
}
