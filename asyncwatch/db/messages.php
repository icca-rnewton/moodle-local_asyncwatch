<?php
/**
 * Message providers for local_asyncwatch.
 *
 * Required for helper::send_message() to work — message_send() silently
 * does nothing if the component hasn't declared the message name it's
 * trying to send. Only the learner notifications go through the Message
 * API (see helper::send_message()'s docblock for why the staff digest
 * doesn't); this declares that one message type.
 *
 * @package    local_asyncwatch
 * @copyright 2026 Inns of Court College of Advocacy (Part of COIC)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    'progressnotification' => [
        'defaults' => [
            // MESSAGE_DEFAULT_LOGGEDIN/MESSAGE_DEFAULT_LOGGEDOFF were
            // deprecated in Moodle 4.0 (MDL-73284) and removed entirely in
            // 4.5 — MESSAGE_DEFAULT_ENABLED is the single replacement
            // constant covering both online/offline states.
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
];