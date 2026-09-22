<?php
/**
 * Site-wide admin settings for local_asyncwatch.
 *
 * Staff "report" emails (one per rule per run, CSV attached) use a single
 * site-wide template for each type (Behind / At-risk) rather than per-course
 * custom text — configured here rather than in the course Notifications tab.
 * Learner emails for per-course rules remain per-course, edited in that tab.
 *
 * Cross-course rules (Site administration → AsyncWatch → Cross-course Rules)
 * have no course of their own, so BOTH their learner and staff wording live
 * here too — see the second section below.
 *
 * @package    local_asyncwatch
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $settingspage = new admin_settingpage('local_asyncwatch', get_string('pluginname', 'local_asyncwatch'));

    // ── Course-level profile field write allowlist ───────────────────────────
    // local/asyncwatch:manage is a PER-COURSE capability, but custom profile
    // fields are a SITE-WIDE resource — without this, any course's editing
    // teacher could point a rule at any unlocked text/menu profile field on
    // the site. This lets a site admin explicitly opt fields in; empty
    // (the default) means no course-level rule can write to any field until
    // one is added here. Cross-course rules are unaffected — they already
    // require local/asyncwatch:manageglobal, a genuinely site-wide
    // capability with no default role.
    //
    // Fields are grouped by their profile field category (Site admin >
    // Users > User profile fields), and locked fields are shown greyed out
    // and un-tickable rather than left off the list entirely — so a field
    // that's currently locked still confirms it was found, without letting
    // it actually be selected until it's unlocked at the higher level
    // that's meant to control it.
    $settingspage->add(new admin_setting_heading(
        'local_asyncwatch/profilefieldheading',
        get_string('profilefieldheading', 'local_asyncwatch'),
        get_string('profilefieldheading_desc', 'local_asyncwatch')
    ));
    $settingspage->add(new \local_asyncwatch\admin_setting_profilefields(
        'local_asyncwatch/course_profile_field_allowlist',
        get_string('profilefield_allowlist', 'local_asyncwatch'),
        get_string('profilefield_allowlist_desc', 'local_asyncwatch')
    ));

    // ── Per-course rule staff report emails ─────────────────────────────────
    $settingspage->add(new admin_setting_heading(
        'local_asyncwatch/staffreportheading',
        get_string('staffreportheading', 'local_asyncwatch'),
        get_string('staffreportheading_desc', 'local_asyncwatch')
    ));

    // Behind (breach) report.
    $settingspage->add(new admin_setting_configtext(
        'local_asyncwatch/staff_breach_subject',
        get_string('staff_breach_subject', 'local_asyncwatch'),
        get_string('staff_breach_subject_desc', 'local_asyncwatch'),
        get_string('tpl_staff_subject_default', 'local_asyncwatch'),
        PARAM_TEXT
    ));
    $settingspage->add(new admin_setting_confightmleditor(
        'local_asyncwatch/staff_breach_body',
        get_string('staff_breach_body', 'local_asyncwatch'),
        get_string('staff_breach_body_desc', 'local_asyncwatch'),
        get_string('tpl_staff_body_default', 'local_asyncwatch')
    ));

    // At-risk (warning) report.
    $settingspage->add(new admin_setting_configtext(
        'local_asyncwatch/staff_warning_subject',
        get_string('staff_warning_subject', 'local_asyncwatch'),
        get_string('staff_warning_subject_desc', 'local_asyncwatch'),
        get_string('tpl_staff_warning_subject_default', 'local_asyncwatch'),
        PARAM_TEXT
    ));
    $settingspage->add(new admin_setting_confightmleditor(
        'local_asyncwatch/staff_warning_body',
        get_string('staff_warning_body', 'local_asyncwatch'),
        get_string('staff_warning_body_desc', 'local_asyncwatch'),
        get_string('tpl_staff_warning_body_default', 'local_asyncwatch')
    ));

    // ── Cross-course rule emails (learner + staff, both live here) ──────────
    $settingspage->add(new admin_setting_heading(
        'local_asyncwatch/globalruleemailheading',
        get_string('globalruleemailheading', 'local_asyncwatch'),
        get_string('globalruleemailheading_desc', 'local_asyncwatch')
    ));

    // Behind — learner.
    $settingspage->add(new admin_setting_configtext(
        'local_asyncwatch/global_learner_breach_subject',
        get_string('global_learner_breach_subject', 'local_asyncwatch'),
        get_string('global_learner_breach_subject_desc', 'local_asyncwatch'),
        get_string('tpl_global_learner_subject_default', 'local_asyncwatch'),
        PARAM_TEXT
    ));
    $settingspage->add(new admin_setting_confightmleditor(
        'local_asyncwatch/global_learner_breach_body',
        get_string('global_learner_breach_body', 'local_asyncwatch'),
        get_string('global_learner_breach_body_desc', 'local_asyncwatch'),
        get_string('tpl_global_learner_body_default', 'local_asyncwatch')
    ));

    // Behind — staff report.
    $settingspage->add(new admin_setting_configtext(
        'local_asyncwatch/global_staff_breach_subject',
        get_string('global_staff_breach_subject', 'local_asyncwatch'),
        get_string('global_staff_breach_subject_desc', 'local_asyncwatch'),
        get_string('tpl_global_staff_subject_default', 'local_asyncwatch'),
        PARAM_TEXT
    ));
    $settingspage->add(new admin_setting_confightmleditor(
        'local_asyncwatch/global_staff_breach_body',
        get_string('global_staff_breach_body', 'local_asyncwatch'),
        get_string('global_staff_breach_body_desc', 'local_asyncwatch'),
        get_string('tpl_global_staff_body_default', 'local_asyncwatch')
    ));

    // At-risk — learner.
    $settingspage->add(new admin_setting_configtext(
        'local_asyncwatch/global_learner_warning_subject',
        get_string('global_learner_warning_subject', 'local_asyncwatch'),
        get_string('global_learner_warning_subject_desc', 'local_asyncwatch'),
        get_string('tpl_global_learner_warning_subject_default', 'local_asyncwatch'),
        PARAM_TEXT
    ));
    $settingspage->add(new admin_setting_confightmleditor(
        'local_asyncwatch/global_learner_warning_body',
        get_string('global_learner_warning_body', 'local_asyncwatch'),
        get_string('global_learner_warning_body_desc', 'local_asyncwatch'),
        get_string('tpl_global_learner_warning_body_default', 'local_asyncwatch')
    ));

    // At-risk — staff report.
    $settingspage->add(new admin_setting_configtext(
        'local_asyncwatch/global_staff_warning_subject',
        get_string('global_staff_warning_subject', 'local_asyncwatch'),
        get_string('global_staff_warning_subject_desc', 'local_asyncwatch'),
        get_string('tpl_global_staff_warning_subject_default', 'local_asyncwatch'),
        PARAM_TEXT
    ));
    $settingspage->add(new admin_setting_confightmleditor(
        'local_asyncwatch/global_staff_warning_body',
        get_string('global_staff_warning_body', 'local_asyncwatch'),
        get_string('global_staff_warning_body_desc', 'local_asyncwatch'),
        get_string('tpl_global_staff_warning_body_default', 'local_asyncwatch')
    ));

    $ADMIN->add('localplugins', $settingspage);

    // ── Cross-course Rules management page ──────────────────────────────────
    // Separate admin external page rather than a $settingspage setting —
    // this needs full form/table UI, not a config value. Gated by
    // local/asyncwatch:manageglobal, which has no default role, so this
    // link only shows up for true site admins.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_asyncwatch_globalrules',
        get_string('globalrules', 'local_asyncwatch'),
        new moodle_url('/local/asyncwatch/globalrules.php'),
        'local/asyncwatch:manageglobal'
    ));

    $ADMIN->add('localplugins', new admin_externalpage(
        'local_asyncwatch_globalreport',
        get_string('globalreport', 'local_asyncwatch'),
        new moodle_url('/local/asyncwatch/globalreport.php'),
        'local/asyncwatch:manageglobal'
    ));
}