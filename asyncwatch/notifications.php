<?php
/**
 * AsyncWatch notification template editor.
 *
 * @package    local_asyncwatch
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/formslib.php');

use local_asyncwatch\helper;
use local_asyncwatch\form\notification_form;

// ── Params & auth ─────────────────────────────────────────────────────────────
$courseid = required_param('courseid', PARAM_INT);
$course   = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context  = context_course::instance($courseid);

require_login($course);
require_capability('local/asyncwatch:manage', $context);

// ── Page setup ────────────────────────────────────────────────────────────────
$pageurl = new moodle_url('/local/asyncwatch/notifications.php', ['courseid' => $courseid]);
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('notifications', 'local_asyncwatch'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

// ── Load / initialise template ────────────────────────────────────────────────
$tpl = $DB->get_record('asyncwatch_ntpl', ['courseid' => $courseid]);
if (!$tpl) {
    $tpl = (object)[
        'courseid'                => $courseid,
        'learner_subject'         => get_string('tpl_learner_subject_default',        'local_asyncwatch'),
        'learner_body'            => get_string('tpl_learner_body_default',            'local_asyncwatch'),
        'learner_warning_subject' => get_string('tpl_learner_warning_subject_default', 'local_asyncwatch'),
        'learner_warning_body'    => get_string('tpl_learner_warning_body_default',    'local_asyncwatch'),
        'staff_recipients'        => json_encode(['userids' => []]),
    ];
}

// Fall back to defaults for any empty template fields.
$all_defaults = [
    'learner_subject'         => get_string('tpl_learner_subject_default',        'local_asyncwatch'),
    'learner_body'            => get_string('tpl_learner_body_default',            'local_asyncwatch'),
    'learner_warning_subject' => get_string('tpl_learner_warning_subject_default', 'local_asyncwatch'),
    'learner_warning_body'    => get_string('tpl_learner_warning_body_default',    'local_asyncwatch'),
];
foreach ($all_defaults as $f => $default) {
    if (empty($tpl->$f)) $tpl->$f = $default;
}

$recipients       = json_decode($tpl->staff_recipients ?? '{}', true) ?: ['userids' => []];
$selected_userids = array_map('intval', $recipients['userids'] ?? []);

// ── Build user list for autocomplete ─────────────────────────────────────────
$all_users = $DB->get_records_sql(
    "SELECT id, firstname, lastname, email,
            firstnamephonetic, lastnamephonetic, middlename, alternatename
       FROM {user}
      WHERE deleted = 0 AND suspended = 0 AND id != :guestid
      ORDER BY lastname ASC, firstname ASC",
    ['guestid' => $CFG->siteguest ?? 1]
);
$user_options = [];
foreach ($all_users as $u) {
    $user_options[$u->id] = fullname($u) . ' (' . $u->email . ')';
}

// ── Build form ────────────────────────────────────────────────────────────────
$form = new notification_form($pageurl->out(false), [
    'courseid' => $courseid,
    'users'    => $user_options,
]);

// ── Handle cancel ─────────────────────────────────────────────────────────────
if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/asyncwatch/manage.php', ['courseid' => $courseid]));
}

// ── Handle save ───────────────────────────────────────────────────────────────
if ($data = $form->get_data()) {
    // Extract editor text from the editor element arrays.
    $tpl->learner_subject         = $data->learner_subject;
    $tpl->learner_body            = $data->learner_body_editor['text'] ?? '';
    $tpl->learner_warning_subject = $data->learner_warning_subject;
    $tpl->learner_warning_body    = $data->learner_warning_body_editor['text'] ?? '';

    $tpl->staff_recipients = json_encode([
        'userids' => array_values(array_unique(array_filter(
            array_map('intval', (array)($data->staff_recipients_ids ?? []))
        ))),
    ]);

    $now = time();
    if (!empty($tpl->id)) {
        $tpl->timemodified = $now;
        $DB->update_record('asyncwatch_ntpl', $tpl);
    } else {
        $tpl->timecreated  = $now;
        $tpl->timemodified = $now;
        $tpl->id = $DB->insert_record('asyncwatch_ntpl', $tpl);
    }
    redirect($pageurl, get_string('tpl_saved', 'local_asyncwatch'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// ── Convert plain-text bodies to HTML for the Atto editor ──────────────────
// Plain-text bodies need newlines converted to <p> tags for the Atto editor.
function aw_text_to_html(string $text): string {
    if (strpos($text, '<p>') !== false || strpos($text, '<br') !== false) {
        return $text; // Already HTML.
    }
    $paras = preg_split('/\n{2,}/', trim($text));
    $html  = '';
    foreach ($paras as $para) {
        $para  = str_replace("\n", '<br>', htmlspecialchars($para, ENT_QUOTES));
        $html .= '<p>' . $para . '</p>';
    }
    return $html;
}

// ── Pre-populate form with saved data ─────────────────────────────────────────
$form->set_data([
    'courseid'                      => $courseid,
    'learner_subject'               => $tpl->learner_subject,
    'learner_body_editor'           => ['text' => aw_text_to_html($tpl->learner_body),            'format' => FORMAT_HTML],
    'learner_warning_subject'       => $tpl->learner_warning_subject,
    'learner_warning_body_editor'   => ['text' => aw_text_to_html($tpl->learner_warning_body),    'format' => FORMAT_HTML],
    'staff_recipients_ids'          => $selected_userids,
]);

// ── Render ────────────────────────────────────────────────────────────────────
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('notifications', 'local_asyncwatch'));

$manage_url = new moodle_url('/local/asyncwatch/manage.php', ['courseid' => $courseid]);
$tabs = [
    new tabobject('parts',         new moodle_url($manage_url, ['tab' => 'parts']),  get_string('tab_parts',         'local_asyncwatch')),
    new tabobject('rules',         new moodle_url($manage_url, ['tab' => 'rules']),  get_string('tab_rules',         'local_asyncwatch')),
    new tabobject('rulesets',      new moodle_url('/local/asyncwatch/rulesets.php',      ['courseid' => $courseid]), get_string('tab_rulesets',      'local_asyncwatch')),
    new tabobject('report',        new moodle_url('/local/asyncwatch/report.php',        ['courseid' => $courseid]), get_string('tab_report',        'local_asyncwatch')),
    new tabobject('notifications', $pageurl, get_string('tab_notifications', 'local_asyncwatch')),
];
echo $OUTPUT->tabtree($tabs, 'notifications');

$form->display();

echo $OUTPUT->footer();