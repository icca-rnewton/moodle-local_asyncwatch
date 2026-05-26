<?php
/**
 * AJAX endpoint: create a new Rule Set and return its id + name as JSON.
 *
 * POST params: courseid (int), name (string), sesskey (string)
 *
 * @package    local_asyncwatch
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once('../../config.php');

use local_asyncwatch\helper;

header('Content-Type: application/json');

try {
    $courseid = required_param('courseid', PARAM_INT);
    $name     = required_param('name',     PARAM_TEXT);

    // Auth.
    $course  = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    $context = context_course::instance($courseid);
    require_login($course);
    require_capability('local/asyncwatch:manage', $context);
    require_sesskey();

    $name = trim($name);
    if ($name === '') {
        throw new \invalid_parameter_exception('Name cannot be empty');
    }
    if (core_text::strlen($name) > 255) {
        throw new \invalid_parameter_exception('Name too long');
    }

    // Check for duplicate name within this course.
    if ($DB->record_exists('asyncwatch_rule_sets', ['courseid' => $courseid, 'name' => $name])) {
        echo json_encode(['error' => get_string('ruleset_name_duplicate', 'local_asyncwatch')]);
        exit;
    }

    $data           = new \stdClass();
    $data->courseid = $courseid;
    $data->name     = $name;

    $newid = helper::save_rule_set($data);

    echo json_encode(['id' => $newid, 'name' => $name]);

} catch (\Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
exit;
