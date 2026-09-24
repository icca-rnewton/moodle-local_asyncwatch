<?php
/**
 * AsyncWatch cross-course notifications page.
 *
 * The cross-course equivalent of notifications.php at course level — same
 * three sections (Behind learner email, Warning learner email, Staff
 * recipients), on one page, in the same order. The one structural
 * difference: there's only ever one of these (site-wide, not per-course),
 * so the learner email wording is stored as plugin config rather than a
 * per-course DB row — but it's edited here, not in Site administration,
 * for the same reason course-level learner wording isn't a site setting:
 * it's genuinely this feature's own content, not a site-wide default.
 *
 * Staff report wording stays in Site administration → AsyncWatch, same as
 * course-level — see that page's own note about why.
 *
 * Supersedes the earlier globalrecipients.php, which only had the
 * recipients section — delete that file from the server, it's replaced
 * by this one.
 *
 * Lives outside any course context — registered as an admin external page
 * (see settings.php) and gated by the local/asyncwatch:manageglobal
 * capability, which has no default role (site admins only).
 *
 * URL: /local/asyncwatch/globalnotifications.php
 *
 * @package    local_asyncwatch
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/formslib.php');

use local_asyncwatch\helper;
use local_asyncwatch\form\global_notification_form;

// ── Auth / page setup ───────────────────────────────────────────────────────
admin_externalpage_setup('local_asyncwatch_globalnotifications');

$pageurl = new moodle_url('/local/asyncwatch/globalnotifications.php');

// ── Build the site-wide user picker ─────────────────────────────────────────
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

$form = new global_notification_form($pageurl->out(false), ['users' => $user_options]);

if ($form->is_cancelled()) {
    redirect($pageurl);
}

// ── Handle save ───────────────────────────────────────────────────────────────
if ($data = $form->get_data()) {
    // Editor content is user-authored HTML — clean it before storing, same
    // treatment as the per-course version (see notifications.php).
    set_config('global_learner_breach_subject',  $data->learner_subject, 'local_asyncwatch');
    set_config('global_learner_breach_body',      clean_param($data->learner_body_editor['text'] ?? '', PARAM_CLEANHTML), 'local_asyncwatch');
    set_config('global_learner_warning_subject',  $data->learner_warning_subject, 'local_asyncwatch');
    set_config('global_learner_warning_body',     clean_param($data->learner_warning_body_editor['text'] ?? '', PARAM_CLEANHTML), 'local_asyncwatch');

    helper::set_global_staff_recipient_ids((array)($data->staff_recipients_ids ?? []));

    redirect($pageurl, get_string('tpl_saved', 'local_asyncwatch'), null,
        \core\output\notification::NOTIFY_SUCCESS);
}

// ── Convert plain-text bodies to HTML for the Atto editor ──────────────────
// Same helper as notifications.php — a first-time default value may not
// already be wrapped in <p> tags.
function aw_global_text_to_html(string $text): string {
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

// ── Pre-populate form with saved (or default) data ──────────────────────────
$learner_subject         = get_config('local_asyncwatch', 'global_learner_breach_subject');
$learner_body            = get_config('local_asyncwatch', 'global_learner_breach_body');
$learner_warning_subject = get_config('local_asyncwatch', 'global_learner_warning_subject');
$learner_warning_body    = get_config('local_asyncwatch', 'global_learner_warning_body');

if ($learner_subject === false || $learner_subject === '') {
    $learner_subject = get_string('tpl_global_learner_subject_default', 'local_asyncwatch');
}
if ($learner_body === false || $learner_body === '') {
    $learner_body = get_string('tpl_global_learner_body_default', 'local_asyncwatch');
}
if ($learner_warning_subject === false || $learner_warning_subject === '') {
    $learner_warning_subject = get_string('tpl_global_learner_warning_subject_default', 'local_asyncwatch');
}
if ($learner_warning_body === false || $learner_warning_body === '') {
    $learner_warning_body = get_string('tpl_global_learner_warning_body_default', 'local_asyncwatch');
}

$form->set_data([
    'learner_subject'             => $learner_subject,
    'learner_body_editor'         => ['text' => aw_global_text_to_html($learner_body),         'format' => FORMAT_HTML],
    'learner_warning_subject'     => $learner_warning_subject,
    'learner_warning_body_editor' => ['text' => aw_global_text_to_html($learner_warning_body), 'format' => FORMAT_HTML],
    'staff_recipients_ids'        => helper::get_global_staff_recipient_ids(),
]);

// ── Render ────────────────────────────────────────────────────────────────────
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('globalnotifications', 'local_asyncwatch'));

$tabs = [
    new tabobject('globalrules',         new moodle_url('/local/asyncwatch/globalrules.php'),         get_string('globalrules', 'local_asyncwatch')),
    new tabobject('globalreport',        new moodle_url('/local/asyncwatch/globalreport.php'),        get_string('globalreport', 'local_asyncwatch')),
    new tabobject('globalnotifications', $pageurl,                                                    get_string('tab_globalnotifications', 'local_asyncwatch')),
];
echo $OUTPUT->tabtree($tabs, 'globalnotifications');

$form->display();

echo $OUTPUT->footer();