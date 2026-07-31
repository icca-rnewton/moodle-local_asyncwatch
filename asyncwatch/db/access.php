<?php
/**
 * Capabilities for local_asyncwatch.
 *
 * @package    local_asyncwatch
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Manage parts and rules within a course (course editors / managers).
    'local/asyncwatch:manage' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    // View the progress report for a course.
    'local/asyncwatch:viewreport' => [
        'captype'      => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],

    // Manage cross-course rules (span multiple courses, live outside any
    // single course context). Deliberately has NO default archetypes —
    // Moodle's primary site administrators bypass capability checks
    // entirely, so leaving this unassigned means only true site admins
    // can reach it. Assign explicitly to a role if that's ever meant to
    // change.
    'local/asyncwatch:manageglobal' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],
];