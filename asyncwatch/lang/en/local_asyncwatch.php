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

// Parts UI
$string['parts']                = 'Parts';
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

// Notifications tab
$string['tab_notifications']        = 'Notifications';
$string['notifications']            = 'Async Progress Monitor: Notifications';
$string['tpl_saved']                = 'Notification templates saved.';
$string['tpl_learner_heading']      = 'Learner email';
$string['tpl_learner_desc']         = 'Sent to the learner when a rule they are behind on is triggered.';
$string['tpl_staff_heading']        = 'Staff email';
$string['tpl_staff_desc']           = 'Sent to selected staff members when a rule is triggered for any learner.';
$string['email_subject']            = 'Subject';
$string['email_body']               = 'Body';
$string['staff_recipients']         = 'Staff recipients';
$string['staff_recipients_desc']    = 'Choose who receives the staff email.';
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

// Default templates
$string['tpl_learner_subject_default'] = 'Progress reminder: {{coursename}}';
$string['tpl_learner_body_default']    = 'Dear {{firstname}},

This is a reminder about your progress in {{coursename}}.

You have completed {{parts_done}} of {{parts_required}} required sections by the deadline of {{deadline}}.

Please log in and continue your studies as soon as possible.

If you have any questions, please contact your course administrator.';

$string['tpl_staff_subject_default']   = 'Async Progress Monitor alert: {{fullname}} — {{coursename}}';
$string['tpl_staff_body_default']      = 'This is an automated alert from Async Progress Monitor.

Learner: {{fullname}} ({{email}})
Course: {{coursename}}
Rule: {{rulename}}
Progress: {{parts_done}} of {{parts_required}} sections complete
Deadline: {{deadline}}

Please review this learner\'s progress.';

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

$string['tpl_staff_warning_subject_default']   = 'Async Progress Monitor at-risk alert: {{fullname}} — {{coursename}}';
$string['tpl_staff_warning_body_default']      = 'This is an automated at-risk alert from Async Progress Monitor.

Learner: {{fullname}} ({{email}})
Course: {{coursename}}
Rule: {{rulename}}
Progress: {{parts_done}} of {{parts_required}} sections complete
Deadline: {{deadline}}

This learner is approaching their deadline and may need support.';

// Bulk delete
$string['bulkdelete']                 = 'Delete selected';
$string['bulkdeleteconfirm']          = 'Are you sure you want to delete the selected items? This cannot be undone.';
$string['bulkdeleted']                = 'Successfully deleted {$a} item(s).';
$string['partdeleted']                = 'Part deleted successfully.';
$string['partdeleted_rules_warning']  = 'Note: existing rules may need updating as the total number of parts has changed.';

$string['tpl_reset'] = 'Reset to default';

// Rule Sets
$string['tab_rulesets']                = 'Rule Sets';
$string['rulesets_intro']              = 'Rule Sets let you apply different rules to different groups of learners within this course. Assign rules to a set, then assign course groups to that set — learners in those groups will only be evaluated against the rules in their set. Rules not assigned to any set are <strong>global</strong> and always apply to all learners regardless of whether Rule Sets exist. Rule Sets are optional — if none are defined, all rules behave as global.';
$string['rulesets_nogroups_warning']   = 'This course has no groups. You need to create course groups before you can assign them to Rule Sets. Visit Course Settings → Groups to create them.';
$string['addruleset']                  = 'Add Rule Set';
$string['editruleset']                 = 'Edit Rule Set';
$string['rulesetname']                 = 'Rule Set name';
$string['ruleset_rules']               = 'Rules in this set';
$string['ruleset_rules_desc']          = 'Select which rules belong to this set. A rule can only belong to one set at a time.';
$string['ruleset_groups']              = 'Course groups';
$string['ruleset_groups_desc']         = 'Select which course groups this rule set applies to. Global rules (not assigned to any set) still apply to all learners regardless of these assignments.';
$string['norulesets']                  = 'No Rule Sets have been defined yet. Click "Add Rule Set" to get started.';
$string['rulesetsaved']                = 'Rule Set saved successfully.';
$string['rulesetdeleted']              = 'Rule Set deleted successfully.';
$string['rulesetdeleteconfirm']        = 'Are you sure you want to delete this Rule Set? The rules and groups will be unassigned but not deleted.';
$string['ruleset_name_duplicate']      = 'A Rule Set with this name already exists in this course.';
$string['rule_global']                 = 'Global';
$string['ruleset_assignment_header']   = 'Rule Set';
$string['rule_params_header']          = 'Rule Parameters';
$string['ruleset_global_note']         = 'If this rule is not assigned to a Rule Set it will be <strong>global</strong> — applying to all learners at all times, regardless of whether other Rule Sets exist in this course.';
$string['ruleset_none']                = '— No set (global) —';
$string['ruleset_assign']              = 'Assign to Rule Set';
$string['ruleset_assign_help']         = 'Optionally assign this rule to a Rule Set. Rules in a set only apply to learners in that set\'s course groups. Rules not in any set are global.';
$string['filter_ruleset']              = 'Rule Set';
$string['filter_ruleset_all']          = 'All Rule Sets';

// Group overrides
$string['overrides_title']          = 'Group Deadline Overrides';
$string['overrides']                = 'Group Overrides';
$string['overrides_count']          = '{$a} group override(s)';
$string['addoverride']              = 'Add group override';
$string['editoverride']             = 'Edit group override';
$string['nooverrides']              = 'No group overrides defined. The default rule deadline applies to all learners.';
$string['overridesaved']            = 'Override saved successfully.';
$string['overridedeleted']          = 'Override deleted.';
$string['overridedeleteconfirm']    = 'Delete this group override? The group will revert to the default deadline.';
$string['override_default_deadline']= 'Default deadline';
$string['override_default_desc']    = 'Groups without an override use this deadline and warning.';
$string['override_deadline']        = 'Override Deadline';
$string['override_active']          = 'This learner has a group deadline override';
$string['override_none']            = 'Default';
$string['filter_override']          = 'Override';
$string['filter_override_all']      = 'All';
$string['filter_override_active']   = 'Override active';
$string['filter_override_default']  = 'Default deadline';
$string['backtoruleslist']          = 'Back to Rules';
$string['deletedgroup']             = '(Deleted group)';

$string['filter_group']     = 'Group';
$string['filter_group_all'] = 'All groups';

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
