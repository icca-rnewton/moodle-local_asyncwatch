<?php
/**
 * English language strings for local_asyncwatch.
 *
 * @package    local_asyncwatch
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// General
$string['pluginname']           = 'Async Progress Monitor';
$string['pluginname_desc']      = 'Monitor asynchronous learner progress by grouping activities into Parts and setting completion rules.';

// Navigation / page titles
$string['manage']               = 'Async Progress Monitor: Manage';
$string['report']               = 'Async Progress Monitor: Progress Report';
$string['tab_parts']            = 'Parts';
$string['tab_rules']            = 'Rules';
$string['tab_report']           = 'Report';
$string['card_show_all_rules']  = 'Show all rules';

// Parts UI
$string['parts']                = 'Parts';
$string['part']                 = 'Part';
$string['addpart']              = 'Add part';
$string['editpart']             = 'Edit part';
$string['deletepart']           = 'Delete part';
$string['partname']             = 'Part name';
$string['partname_help']        = 'A short, descriptive name for this group of activities (e.g. "Part 1 – Introduction").';
$string['partactivities']       = 'Activities in this part';
$string['noparts']              = 'No parts have been defined yet. Click "Add part" to get started.';
$string['partdeleteconfirm']    = 'Are you sure you want to delete this part? The activities will be unassigned but not deleted.';
$string['partsaved']            = 'Part saved successfully.';
$string['totalparts']           = 'Total parts defined: {$a}';

// Activity selector
$string['selectactivities']     = 'Select activities';
$string['filter_type']          = 'Filter by type';
$string['filter_section']       = 'Filter by section';
$string['filter_tracking']      = 'Only show activities with completion tracking enabled';
$string['tracking_off_warning'] = '⚠ Completion tracking is disabled for this activity. It will not be counted unless tracking is turned on in the activity settings.';
$string['noactivities']         = 'No activities match the current filter.';
$string['applyfilter']          = 'Apply filter';

// Rules UI
$string['rules']                = 'Rules';
$string['addrule']              = 'Add rule';
$string['editrule']             = 'Edit rule';
$string['deleterule']           = 'Delete rule';
$string['rulename']             = 'Rule name';
$string['rulename_help']        = 'A label for this rule (e.g. "Checkpoint 1").';
$string['rule_name_duplicate']  = 'A rule with this name already exists in this course. Please choose a different name.';
$string['parts_required']       = 'Parts required';
$string['parts_required_help']  = 'How many parts must be fully completed by the deadline.';
$string['deadline']             = 'Deadline';
$string['deadline_help']        = 'Date and time by which the required number of parts must be completed.';
$string['warn_hours']           = 'Warning';
$string['warn_hours_help']      = 'Send a yellow warning notification this many hours before the deadline if the learner is at risk of not meeting it. Set to 0 to disable.';
$string['norules']              = 'No rules have been defined yet. Click "Add rule" to get started.';
$string['ruledeleteconfirm']    = 'Are you sure you want to delete this rule? Sent notification records will also be removed.';
$string['rulesaved']            = 'Rule saved successfully.';
$string['rule_summary']         = '{$a->required} of {$a->total} parts by {$a->deadline}';

// Rule form — enable / warning fields
$string['rule_enabled']         = 'Enable rule';
$string['rule_enabled_help']    = 'When unchecked this rule will be saved but will not trigger any notifications or appear as active in reports.';
$string['rule_disabled_badge']  = 'Disabled';
$string['warn_header']          = 'Warning notification';
$string['warn_window']          = 'Warn learners';
$string['warn_window_help']     = 'If enabled, a warning email is sent to learners who are still below the threshold within this window before the deadline. Set the number and choose hours, days, or weeks.';
$string['warn_enabled']         = 'Enable warning';
$string['warn_value_required']  = 'Please enter a warning window value of at least 1.';
$string['warn_unit_minutes']    = 'minutes before deadline';
$string['warn_unit_hours']      = 'hours before deadline';
$string['warn_unit_days']       = 'days before deadline';
$string['warn_unit_weeks']      = 'weeks before deadline';

// Report
$string['learner']              = 'Learner';
$string['learners']             = 'Learners';
$string['parts_complete']       = 'Parts complete';
$string['status']               = 'Status';
$string['status_completed']     = 'Completed';
$string['status_ok']            = 'On track';
$string['filter_status']            = 'Status';
$string['filter_status_all']        = 'All statuses';
$string['filter_status_completed']  = 'Completed';
$string['filter_status_ok']         = 'On track';
$string['filter_status_warning']    = 'At risk';
$string['filter_status_breach']     = 'Behind';
$string['status_warning']       = 'At risk';
$string['status_breach']        = 'Behind';
$string['last_activity']        = 'Last activity';
$string['noenrolments']         = 'No learners are enrolled in this course.';
$string['exportcsv']            = 'Export CSV';
$string['filter_rule']          = 'Rule';
$string['filter_user']          = 'Learner';
$string['all_rules']            = 'All rules';
$string['all_users']            = 'All learners';
$string['clearfilter']          = 'Clear filter';
$string['noresults']            = 'No results match the current filter.';

// Email notifications
$string['email_breach_subject'] = 'Action required: Async Progress Monitor progress alert – {$a->coursename}';
$string['email_breach_body']    = 'Dear {$a->firstname},

Our records show that you have completed {$a->done} of {$a->required} required parts in {$a->coursename} and the deadline of {$a->deadline} has passed.

Please log in and continue your studies as soon as possible.

If you believe this is an error, please contact your course administrator.

This message was sent automatically by Async Progress Monitor.';

$string['email_warning_subject']= 'Reminder: Async Progress Monitor progress check – {$a->coursename}';
$string['email_warning_body']   = 'Dear {$a->firstname},

This is a friendly reminder that you currently have {$a->done} of {$a->required} parts complete in {$a->coursename}.

The deadline is {$a->deadline} – that\'s in less than {$a->hours} hours.

Please log in and continue working through the course materials.

This message was sent automatically by Async Progress Monitor.';

// Errors / capabilities
$string['error_nocourse']       = 'Invalid course ID.';
$string['error_nopermission']   = 'You do not have permission to manage Async Progress Monitor for this course.';
$string['asyncwatch:manage']    = 'Manage Async Progress Monitor parts and rules';
$string['asyncwatch:viewreport']= 'View Async Progress Monitor progress report';
$string['asyncwatch:manageglobal'] = 'Manage cross-course Async Progress Monitor rules';

// Notifications tab
$string['tab_notifications']        = 'Notifications';
$string['notifications']            = 'Async Progress Monitor: Notifications';
$string['tpl_saved']                = 'Notification templates saved.';
$string['tpl_learner_heading']      = 'Learner email';
$string['tpl_learner_desc']         = 'Sent to the learner when a rule they are behind on is triggered.';
$string['tpl_staff_heading']        = 'Staff report';
$string['tpl_staff_desc']           = 'Sent once to selected staff members when a rule is triggered, with a CSV of every affected learner attached.';
$string['email_subject']            = 'Subject';
$string['email_body']               = 'Body';
$string['staff_recipients']         = 'Staff recipients';
$string['staff_recipients_desc']    = 'Choose who receives the staff report email.';
$string['staff_digest_note']        = 'This is sent once per rule as a single report, not per student. The affected students are listed in a CSV attachment, not in the email body — use the placeholders below for a short summary only.';
$string['recipient_by_role']        = 'By role';
$string['recipient_by_cohort']      = 'By cohort';
$string['recipient_by_user']        = 'Individual users';
$string['no_staff_found']           = 'No staff with report access found in this course.';
$string['search_users']             = 'Type a name or email to search...';

// Placeholders
$string['ph_available']             = 'Available placeholders:';
$string['ph_firstname']             = "Learner's first name";
$string['ph_lastname']              = "Learner's last name";
$string['ph_fullname']              = "Learner's full name";
$string['ph_email']                 = "Learner's email address";
$string['ph_coursename']            = 'Course name';
$string['ph_parts_done']            = 'Number of parts completed by this learner';
$string['ph_parts_required']        = 'Number of parts required by this rule';
$string['ph_deadline']              = 'Rule deadline (formatted date)';
$string['ph_rulename']              = 'Name of the rule that triggered';
$string['ph_sitename']              = 'Site name';
$string['ph_affected_count']        = 'Number of students included in this report (staff report only)';

// Admin settings — staff report wording (site-wide, not per-course).
$string['staffreportheading']       = 'Staff report emails';
$string['staffreportheading_desc']  = 'AsyncWatch sends staff one report email per rule per run (with a CSV of affected students attached), not per-course custom text. Configure the two report templates below — placeholders: {{coursename}}, {{rulename}}, {{deadline}}, {{sitename}}, {{affected_count}}.';
$string['staff_breach_subject']     = 'Behind report — subject';
$string['staff_breach_subject_desc'] = 'Subject line for the staff report sent when learners have passed a rule\'s deadline.';
$string['staff_breach_body']        = 'Behind report — body';
$string['staff_breach_body_desc']   = 'Body text for the staff report sent when learners have passed a rule\'s deadline. The affected students are listed in the attached CSV, not in this text.';
$string['staff_warning_subject']    = 'At-risk report — subject';
$string['staff_warning_subject_desc'] = 'Subject line for the staff report sent when learners enter a rule\'s warning window.';
$string['staff_warning_body']       = 'At-risk report — body';
$string['staff_warning_body_desc']  = 'Body text for the staff report sent when learners enter a rule\'s warning window. The affected students are listed in the attached CSV, not in this text.';
$string['staff_report_settings_link'] = 'The wording for staff report emails (Behind / At-risk) is configured site-wide in Site administration → Plugins → Local plugins → AsyncWatch, not per course.';

// Admin settings — cross-course rule wording (site-wide; these rules have
// no single course of their own, so both learner and staff wording live
// here rather than in any course's Notifications tab).
$string['ph_courses']                    = 'Comma-separated list of the rule\'s courses';
$string['globalruleemailheading']        = 'Cross-course rule emails';
$string['globalruleemailheading_desc']   = 'Wording for rules that span multiple courses (Site administration → AsyncWatch → Cross-course Rules). These rules don\'t belong to one course, so this is the only place to set their wording — placeholders: {{courses}}, {{rulename}}, {{deadline}}, {{sitename}}, {{affected_count}}, plus {{firstname}}, {{lastname}}, {{fullname}}, {{email}}, {{parts_done}}, {{parts_required}} for the learner templates.';
$string['global_learner_breach_subject']       = 'Learner behind email — subject';
$string['global_learner_breach_subject_desc']  = 'Subject line for the personal email sent to a learner who has passed a cross-course rule\'s deadline.';
$string['global_learner_breach_body']          = 'Learner behind email — body';
$string['global_learner_breach_body_desc']     = 'Body text for the personal email sent to a learner who has passed a cross-course rule\'s deadline.';
$string['global_learner_warning_subject']      = 'Learner at-risk email — subject';
$string['global_learner_warning_subject_desc'] = 'Subject line for the personal email sent to a learner entering a cross-course rule\'s warning window.';
$string['global_learner_warning_body']         = 'Learner at-risk email — body';
$string['global_learner_warning_body_desc']    = 'Body text for the personal email sent to a learner entering a cross-course rule\'s warning window.';
$string['global_staff_breach_subject']         = 'Staff behind report — subject';
$string['global_staff_breach_subject_desc']    = 'Subject line for the staff report sent when learners have passed a cross-course rule\'s deadline.';
$string['global_staff_breach_body']            = 'Staff behind report — body';
$string['global_staff_breach_body_desc']       = 'Body text for the staff report sent when learners have passed a cross-course rule\'s deadline. The affected students are listed in the attached CSV, not in this text.';
$string['global_staff_warning_subject']        = 'Staff at-risk report — subject';
$string['global_staff_warning_subject_desc']   = 'Subject line for the staff report sent when learners enter a cross-course rule\'s warning window.';
$string['global_staff_warning_body']           = 'Staff at-risk report — body';
$string['global_staff_warning_body_desc']      = 'Body text for the staff report sent when learners enter a cross-course rule\'s warning window. The affected students are listed in the attached CSV, not in this text.';

// Default templates — cross-course rules.
$string['tpl_global_learner_subject_default']         = 'Progress reminder: {{courses}}';
$string['tpl_global_learner_body_default']            = 'Dear {{firstname}},

This is a reminder about your progress across {{courses}}.

You have completed {{parts_done}} of {{parts_required}} required parts by the deadline of {{deadline}}.

Please log in and continue your studies as soon as possible.

If you have any questions, please contact your course administrator.';
$string['tpl_global_learner_warning_subject_default'] = 'Reminder: Please keep up with {{courses}}';
$string['tpl_global_learner_warning_body_default']    = 'Dear {{firstname}},

This is a friendly reminder about your progress across {{courses}}.

You have completed {{parts_done}} of {{parts_required}} required parts, and the deadline of {{deadline}} is approaching.

Please log in and continue working through the course materials as soon as possible.

If you have any questions, please contact your course administrator.';
$string['tpl_global_staff_subject_default']           = 'AsyncWatch Alert: {{rulename}} - {{courses}}';
$string['tpl_global_staff_body_default']              = 'This is an automated alert from AsyncWatch.

The attached CSV contains all users who are behind for {{courses}} regarding "{{rulename}}".';
$string['tpl_global_staff_warning_subject_default']   = 'AsyncWatch Alert: {{rulename}} - {{courses}}';
$string['tpl_global_staff_warning_body_default']      = 'This is an automated alert from AsyncWatch.

The attached CSV contains all users who are at risk of falling behind for {{courses}} regarding "{{rulename}}".';

// Cross-course Rules — admin page + form (Site administration → AsyncWatch).
$string['globalrules']                    = 'Cross-course Rules';
$string['globalreport']                   = 'Cross-course Report';
$string['globalrules_intro']              = 'Rules here span multiple courses at once — e.g. "10 parts total across Course A and Course B", regardless of how the completed parts are split between them. Parts are still defined per-course as usual; this page only adds a rule layer on top.';
$string['addglobalrule']                  = 'Add cross-course rule';
$string['editglobalrule']                 = 'Edit cross-course rule';
$string['deleteglobalrule']               = 'Delete cross-course rule';
$string['globalrulesaved']                = 'Cross-course rule saved.';
$string['globalruledeleted']              = 'Cross-course rule deleted.';
$string['globalruledeleteconfirm']        = 'Delete this cross-course rule? This cannot be undone.';
$string['noglobalrules']                  = 'No cross-course rules yet.';
$string['globalrule_courses']             = 'Courses';
$string['globalrule_courses_desc']        = 'Only courses with Parts defined are listed — a course needs at least one Part before it can contribute to a cross-course rule.';
$string['globalrule_courses_required']    = 'Select at least one course.';
$string['globalrule_nocourses']           = 'No courses have Parts defined yet. Set up Parts in a course first, then it will appear here.';
$string['globalrule_totalparts_prefix']   = 'Total parts in selected courses:';
$string['globalrule_partsrequired_desc']  = 'The absolute number of parts a learner must complete, added up across all selected courses combined — it doesn\'t matter which course they come from.';
$string['globalrule_partsrequired_min']   = 'Must be at least 1.';
$string['globalrule_partsrequired_max']   = 'Cannot exceed the combined total of Parts in the selected courses ({$a}).';
$string['globalrule_wording_note']        = 'Email wording for cross-course rules is configured site-wide in Site administration → Plugins → Local plugins → AsyncWatch.';
$string['globalrule_cohorts']             = 'Target cohorts (optional)';
$string['globalrule_cohorts_desc']        = 'Leave empty to include anyone enrolled in the selected courses. Choose one or more cohorts to restrict the rule to just those members, who must also be enrolled in at least one of the selected courses.';
$string['globalrule_col_courses']         = 'Courses';
$string['globalrule_col_cohorts']         = 'Cohorts';
$string['globalrule_overrides_link']      = 'Cohort overrides';
$string['globalrule_recipients']          = 'Staff recipients';
$string['globalrule_recipients_desc']     = 'These staff receive the report email (with CSV attached) for every cross-course rule, warning and behind alike. Shared by all cross-course rules — set per-rule recipients aren\'t available for these.';
$string['globalrule_recipients_saved']    = 'Staff recipients saved.';

// Default templates
$string['tpl_learner_subject_default'] = 'Progress reminder: {{coursename}}';
$string['tpl_learner_body_default']    = 'Dear {{firstname}},

This is a reminder about your progress in {{coursename}}.

You have completed {{parts_done}} of {{parts_required}} required sections by the deadline of {{deadline}}.

Please log in and continue your studies as soon as possible.

If you have any questions, please contact your course administrator.';

$string['tpl_staff_subject_default']   = 'AsyncWatch Alert: {{rulename}} - {{coursename}}';
$string['tpl_staff_body_default']      = 'This is an automated alert from AsyncWatch.

The attached CSV contains all users who are behind for {{coursename}} regarding "{{rulename}}".';

// Rule form — notify_enabled
$string['notify_enabled']           = 'Enable notifications';
$string['notify_enabled_help']      = 'When checked, learner and staff emails will be sent when this rule is triggered. The email templates are configured in the Notifications tab.';

// Notification columns and rule form
$string['notify_header']              = 'Notifications';
$string['notify_breach_heading']      = 'Behind notifications';
$string['notify_breach_desc']         = 'Sent when a learner has passed the deadline without meeting the required parts.';
$string['notify_warning_heading']     = 'Warning notifications';
$string['notify_warning_desc']        = 'Sent when a learner is within the warning window and still below the threshold. Requires "Enable warning" to be set on the rule.';
$string['notify_learner']             = 'Notify learner';
$string['notify_staff']               = 'Notify staff';
$string['notify_learner_short']       = 'Learner';
$string['notify_staff_short']         = 'Staff';
$string['notify_breach_col']          = 'Behind notifications';
$string['notify_warning_col']         = 'Warning notifications';

// Notification template descriptions
$string['tpl_learner_breach_desc']    = 'Sent to the learner when they have passed the deadline without meeting the required parts.';
$string['tpl_staff_breach_desc']      = 'Sent to staff when a learner has passed the deadline without meeting the required parts.';
$string['tpl_learner_warning_desc']   = 'Sent to the learner when they are within the warning window and still below the threshold.';
$string['tpl_staff_warning_desc']     = 'Sent to staff when a learner is within the warning window and still below the threshold.';

// Default warning templates
$string['tpl_learner_warning_subject_default'] = 'Reminder: Please keep up with {{coursename}}';
$string['tpl_learner_warning_body_default']    = 'Dear {{firstname}},

This is a friendly reminder about your progress in {{coursename}}.

You have completed {{parts_done}} of {{parts_required}} required sections, and the deadline of {{deadline}} is approaching.

Please log in and continue working through the course materials as soon as possible.

If you have any questions, please contact your course administrator.';

$string['tpl_staff_warning_subject_default']   = 'AsyncWatch Alert: {{rulename}} - {{coursename}}';
$string['tpl_staff_warning_body_default']      = 'This is an automated alert from AsyncWatch.

The attached CSV contains all users who are at risk of falling behind for {{coursename}} regarding "{{rulename}}".';

// Bulk delete
$string['bulkdelete']                 = 'Delete selected';
$string['bulkdeleteconfirm']          = 'Are you sure you want to delete the selected items? This cannot be undone.';
$string['bulkdeleted']                = 'Successfully deleted {$a} item(s).';
$string['partdeleted']                = 'Part deleted successfully.';
$string['partdeleted_rules_warning']  = 'Note: existing rules may need updating as the total number of parts has changed.';

$string['tpl_reset'] = 'Reset to default';

$string['rule_global']                 = 'Global';
$string['rule_params_header']          = 'Rule Parameters';

// Restrictions (group/cohort audience targeting, inline on the rule itself)
$string['restrict_header']             = 'Restrictions';
$string['restrict_desc']               = 'Leave both empty to apply this rule to everyone enrolled. Select specific groups and/or cohorts to restrict it to only those learners — a learner in either a selected group or a selected cohort is in scope.';
$string['restrict_groups']             = 'Course groups';
$string['restrict_cohorts']            = 'Cohorts';
$string['restrict_all']                = 'All learners';
$string['restrict_summary']            = '{$a->groups} group(s), {$a->cohorts} cohort(s)';
$string['profilefield_header']         = 'Profile Field Sync';
$string['profilefield_desc']           = 'Optionally write this rule\'s status (On track / At risk / Behind / Completed) into a user profile custom field whenever it changes. If more than one rule targets the same field for a learner, whichever rule the scheduled task processes last in a given run decides the value.';
$string['profilefield']                = 'Update profile field';
$string['profilefield_help']           = 'Only text and dropdown-type custom profile fields are listed. The field must already exist — create it under Site administration → Users → User profile fields. The value is updated during the scheduled task run, and only written when the status has actually changed.';
$string['profilefield_none']           = '— Don\'t update a profile field —';

// Group overrides
$string['overrides_title']          = 'Group Deadline Overrides';
$string['overrides']                = 'Group Overrides';
$string['overrides_count']          = '{$a} group override(s)';
$string['overrides_count_cohort']   = '{$a} cohort override(s)';
$string['addoverride']              = 'Add group override';
$string['addoverride_cohort']       = 'Add cohort override';
$string['editoverride']             = 'Edit group override';
$string['nooverrides']              = 'No group overrides defined. The default rule deadline applies to all learners.';
$string['nooverrides_cohort']       = 'No cohort overrides defined. The default rule deadline applies to all learners.';
$string['overridesaved']            = 'Override saved successfully.';
$string['overridedeleted']          = 'Override deleted.';
$string['overridedeleteconfirm']    = 'Delete this group override? The group will revert to the default deadline.';
$string['overridedeleteconfirm_cohort'] = 'Delete this cohort override? The cohort will revert to the default deadline.';
$string['override_default_deadline']= 'Default deadline';
$string['override_default_desc']    = 'Groups without an override use this deadline and warning.';
$string['override_deadline']        = 'Override Deadline';
$string['override_active']          = 'This learner has a group deadline override';
$string['override_active_cohort']   = 'This learner has a cohort deadline override';
$string['override_none']            = 'Default';
$string['filter_override']          = 'Override';
$string['filter_override_all']      = 'All';
$string['filter_override_active']   = 'Override active';
$string['filter_override_default']  = 'Default deadline';
$string['backtoruleslist']          = 'Back to Rules';
$string['deletedgroup']             = '(Deleted group)';
$string['deletedcohort']            = '(Deleted cohort)';
$string['overrides_title_cohort']        = 'Cohort Deadline Overrides';
$string['override_default_desc_cohort']  = 'Cohorts without an override use this deadline and warning.';
$string['globalrule_nocohorts_for_override'] = 'There are no cohorts on this site yet — create one first (Site administration → Users → Cohorts) before adding a cohort override.';
$string['globalrule_override_anycohort_note'] = 'This rule isn\'t restricted to specific cohorts, so an override here will only affect the users who happen to be in the chosen cohort and also enrolled in one of the rule\'s courses.';

$string['filter_group']     = 'Group';
$string['filter_group_all'] = 'All groups';
$string['filter_cohort']     = 'Cohort';
$string['filter_cohort_all'] = 'All cohorts';

$string['part_progress'] = 'Part progress';

// Report column names
$string['default_deadline'] = 'Default Deadline';
$string['default_warn']     = 'Default Warning';
$string['override_warn']    = 'Override Warning';

// Date format: Friday, 15 May 2026, 15:47 (24hr, no leading zeros on day)
$string['aw_datetimefmt']   = '%A, %d %B %Y, %H:%M';

$string['sort_reset'] = 'Reset sort';

// Auto Parts
$string['autoparts_title']                  = 'Auto Parts Generator';
$string['autoparts_btn']                    = 'Auto Parts';
$string['autoparts_badge']                  = 'Auto';
$string['backtomanage']                     = 'Back to Parts';

// Mode
$string['autoparts_mode']                   = 'How should parts be created?';
$string['autoparts_mode_section']           = 'Sections only';
$string['autoparts_mode_section_desc']      = 'All tracked activities within a section (including those inside subsections) form one part per section.';
$string['autoparts_mode_subsection']        = 'Subsections only';
$string['autoparts_mode_subsection_desc']   = 'Each subsection forms its own part. Activities outside subsections are ignored.';
$string['autoparts_mode_both']              = 'Sections and subsections';
$string['autoparts_mode_both_desc']         = 'Each subsection forms its own part. Activities directly in the section (outside any subsection) form a separate "General" part.';
$string['autoparts_no_subsections']         = 'This course has no subsections';

// Selectors
$string['autoparts_sections']              = 'Which sections to include?';
$string['autoparts_sections_desc']         = 'Select the sections you want to generate parts for. All sections are selected by default.';
$string['autoparts_modtypes']              = 'Which activity types to include?';
$string['autoparts_modtypes_desc']         = 'Only activities with completion tracking enabled will be included. Select which types to count towards each part.';

// Preview
$string['autoparts_preview']              = 'Preview';
$string['autoparts_preview_summary']      = 'Preview — review before confirming';
$string['autoparts_preview_will_create']  = 'This will create {$a->parts} part(s) containing {$a->activities} activity/activities.';
$string['autoparts_preview_skipped_tracking'] = '{$a} activity/activities were excluded because completion tracking is not enabled on them.';
$string['autoparts_preview_empty_parts']  = '{$a} section(s)/subsection(s) produced no parts (no matching tracked activities found) and will be skipped.';
$string['autoparts_preview_empty']        = 'No parts could be generated with the selected options. No matching activities with completion tracking enabled were found. Try adjusting the mode, sections, or activity types.';

// Duplicate handling
$string['autoparts_dupes_found']          = 'Name clashes detected';
$string['autoparts_dupes_desc']           = 'One or more generated part names already exist. Choose how to handle them:';
$string['autoparts_dupe_skip']            = 'Skip';
$string['autoparts_dupe_skip_desc']       = 'Leave existing parts unchanged and do not create a duplicate.';
$string['autoparts_dupe_overwrite']       = 'Overwrite';
$string['autoparts_dupe_overwrite_desc']  = 'Replace the existing part\'s activities with the newly generated ones.';
$string['autoparts_dupe_rename']          = 'Rename';
$string['autoparts_dupe_rename_desc']     = 'Keep the existing part and create the new one with a number appended e.g. (2).';

// Preview table columns
$string['autoparts_col_name']             = 'Part name';
$string['autoparts_col_type']             = 'Type';
$string['autoparts_col_activities']       = 'Activities';
$string['autoparts_col_skipped']          = 'Excluded (tracking off)';
$string['autoparts_col_status']           = 'Action';

// Types
$string['autoparts_type_section']         = 'Section';
$string['autoparts_type_subsection']      = 'Subsection';
$string['autoparts_type_remainder']       = 'General';

// Status
$string['autoparts_status_create']        = 'Will create';
$string['autoparts_status_dupe']          = 'Name clash';
$string['autoparts_status_empty']         = 'Skipped — empty';

// Confirm
$string['autoparts_confirm']              = 'Confirm and create parts';
$string['autoparts_saved']                = 'Auto Parts complete: {$a->created} created, {$a->overwritten} overwritten, {$a->renamed} renamed, {$a->skipped} skipped.';

$string['autoparts_mode_help']             = 'Choose how the course structure should be used to group activities into parts.';
$string['autoparts_no_subsections_note']   = 'Subsection options are unavailable because this course does not contain any subsections.';

// Auto parts — dynamic section/subsection lists
$string['autoparts_sections_only_desc']    = 'Select which top-level sections to include. One part will be created per section.';
$string['autoparts_subsections_only_desc'] = 'Select which subsections to include. One part will be created per subsection.';
$string['autoparts_mode_reset_note']       = 'Changing mode resets your section selection to all selected.';

$string['autoparts_include_subsection_acts']      = 'Include activities inside subsections';
$string['autoparts_include_subsection_acts_desc'] = 'When checked, activities inside subsections are rolled up into the section part. When unchecked, only activities directly outside subsections are included.';

// Auto parts preview accordion
$string['autoparts_show_activities'] = 'Show activities';
$string['autoparts_hide_activities'] = 'Hide activities';
$string['autoparts_included']        = 'Included — uncheck to remove from this part:';
$string['autoparts_excluded']        = 'Excluded (tracking off — shown for reference only):';
$string['autoparts_tracking_off']    = 'tracking off';

$string['autoparts_col_detail'] = 'Activities';

// Privacy API metadata
$string['privacy:metadata:asyncwatch_notifications']          = 'A log of warning and breach notification emails sent by AsyncWatch for course-level rules.';
$string['privacy:metadata:asyncwatch_notifications:ruleid']   = 'The rule that triggered this notification.';
$string['privacy:metadata:asyncwatch_notifications:userid']   = 'The learner this notification was sent about.';
$string['privacy:metadata:asyncwatch_notifications:type']     = 'The type of notification (e.g. learner warning, staff breach digest).';
$string['privacy:metadata:asyncwatch_notifications:timesent'] = 'The time the notification was sent.';
$string['privacy:metadata:asyncwatch_global_notifications']          = 'A log of warning and breach notification emails sent by AsyncWatch for cross-course rules.';
$string['privacy:metadata:asyncwatch_global_notifications:ruleid']   = 'The cross-course rule that triggered this notification.';
$string['privacy:metadata:asyncwatch_global_notifications:userid']   = 'The learner this notification was sent about.';
$string['privacy:metadata:asyncwatch_global_notifications:type']     = 'The type of notification (e.g. learner warning, staff breach digest).';
$string['privacy:metadata:asyncwatch_global_notifications:timesent'] = 'The time the notification was sent.';
$string['privacy:metadata:user_info_data']         = 'AsyncWatch can write a learner\'s computed status (On track / At risk / Behind / Completed) into a Moodle user profile custom field you choose. This is a core Moodle table with its own privacy provider — AsyncWatch only ever writes to it.';
$string['privacy:metadata:user_info_data:userid']  = 'The learner whose profile field is being updated.';
$string['privacy:metadata:user_info_data:data']    = 'The status label written into the chosen profile field.';