<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\mod_assign\event\assessable_submitted',
        'callback'  => '\local_ai_reflection\observer::assignment_submitted',
    ],
    [
        'eventname' => '\mod_assign\event\submission_removed',
        'callback'  => '\local_ai_reflection\observer::submission_removed',
    ],
];