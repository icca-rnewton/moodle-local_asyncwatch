<?php
/**
 * Library functions for local_asyncwatch.
 *
 * @package    local_asyncwatch
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Adds the AsyncWatch link to the course administration menu.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context $context
 */
function local_asyncwatch_extend_navigation_course($navigation, $course, $context) {
    if (has_capability('local/asyncwatch:manage', $context)) {
        $url = new moodle_url('/local/asyncwatch/manage.php', ['courseid' => $course->id]);
        $node = navigation_node::create(
            get_string('pluginname', 'local_asyncwatch'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'local_asyncwatch',
            new pix_icon('i/report', '')
        );
        $navigation->add_node($node);
    }
}
