<?php
/**
 * Moodle form for cross-course AsyncWatch notifications.
 *
 * The cross-course equivalent of notification_form.php — same three
 * sections in the same order (Behind learner email, Warning learner
 * email, Staff recipients), so the two pages feel like the same feature
 * at a glance. The one real difference: there's only ever one of these
 * (site-wide, not per-course), and staff report wording lives in Site
 * administration → AsyncWatch rather than here, same as course-level.
 *
 * @package    local_asyncwatch
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_asyncwatch\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class global_notification_form extends \moodleform {

    public function definition(): void {
        $mform = $this->_form;
        $users = $this->_customdata['users']; // [userid => display string]

        $editor_opts = [
            'maxfiles' => 0,
            'maxbytes' => 0,
            'context'  => \context_system::instance(),
        ];

        // Placeholder reference block — same placeholder set as the
        // per-course learner emails, since cross-course learner content
        // is built the same way (see build_global_vars() in the cron task).
        $labels = [
            '{{firstname}}'      => get_string('ph_firstname',      'local_asyncwatch'),
            '{{lastname}}'       => get_string('ph_lastname',       'local_asyncwatch'),
            '{{fullname}}'       => get_string('ph_fullname',       'local_asyncwatch'),
            '{{email}}'          => get_string('ph_email',          'local_asyncwatch'),
            '{{courses}}'        => get_string('ph_courses',        'local_asyncwatch'),
            '{{parts_done}}'     => get_string('ph_parts_done',     'local_asyncwatch'),
            '{{parts_required}}' => get_string('ph_parts_required', 'local_asyncwatch'),
            '{{deadline}}'       => get_string('ph_deadline',       'local_asyncwatch'),
            '{{rulename}}'       => get_string('ph_rulename',       'local_asyncwatch'),
            '{{sitename}}'       => get_string('ph_sitename',       'local_asyncwatch'),
        ];
        $ph_parts = [];
        foreach ($labels as $k => $v) {
            $ph_parts[] = '<code>' . htmlspecialchars($k, ENT_QUOTES) . '</code> <span class="text-muted">— '
                        . htmlspecialchars($v, ENT_QUOTES) . '</span>';
        }
        $ph_html_learner = '<div class="alert alert-info py-2 px-3 mt-1 mb-2" style="font-size:0.875em;">'
                          . '<strong>' . get_string('ph_available', 'local_asyncwatch') . '</strong> &nbsp;'
                          . implode(' &nbsp; ', $ph_parts) . '</div>';

        // Helper closure to add a subject + editor pair — identical shape
        // to notification_form.php's own.
        $add_template = function(string $subj_name, string $body_name, string $header_label, string $ph_html) use ($mform, $editor_opts) {
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
            get_string('notify_breach_heading', 'local_asyncwatch') . ' — ' . get_string('tpl_learner_heading', 'local_asyncwatch'),
            $ph_html_learner
        );

        // ── 2. Behind — Staff report wording lives in site admin settings ──────
        $mform->addElement('static', 'staff_report_note', get_string('tpl_staff_heading', 'local_asyncwatch'),
            '<div class="alert alert-secondary py-2 px-3" style="font-size:0.875em;">'
            . htmlspecialchars(get_string('staff_report_settings_link', 'local_asyncwatch'), ENT_QUOTES)
            . '</div>'
        );

        // ── 3. At Risk — Learner ──────────────────────────────────────────────
        $add_template(
            'learner_warning_subject',
            'learner_warning_body',
            get_string('notify_warning_heading', 'local_asyncwatch') . ' — ' . get_string('tpl_learner_heading', 'local_asyncwatch'),
            $ph_html_learner
        );

        // ── 4. At Risk — Staff report note (single note above covers both) ─────

        // ── Staff recipients ──────────────────────────────────────────────────
        $mform->addElement('header', 'recipients_header',
            get_string('staff_recipients', 'local_asyncwatch'));

        $mform->addElement('static', 'recipients_desc', '',
            \html_writer::tag('p',
                get_string('globalrule_recipients_desc', 'local_asyncwatch'),
                ['class' => 'text-muted mb-2']
            )
        );

        $el = $mform->addElement('autocomplete', 'staff_recipients_ids',
            get_string('staff_recipients', 'local_asyncwatch'), $users);
        $el->setMultiple(true);
        $mform->setType('staff_recipients_ids', PARAM_INT);

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}