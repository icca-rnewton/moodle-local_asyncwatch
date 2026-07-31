<?php
/**
 * Form: site-wide staff recipient list for cross-course rule reports.
 *
 * One list shared by every cross-course rule — mirrors the site-wide email
 * wording (settings.php) rather than per-course recipients (which per-course
 * rules configure individually in the Notifications tab).
 *
 * @package    local_asyncwatch
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_asyncwatch\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class global_recipients_form extends \moodleform {

    public function definition(): void {
        $mform = $this->_form;
        $users = $this->_customdata['users']; // [userid => display string]

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