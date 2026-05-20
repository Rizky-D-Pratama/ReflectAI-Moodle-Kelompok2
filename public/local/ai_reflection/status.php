<?php
require_once(__DIR__ . '/../../config.php');

global $DB, $USER;

$cmid = required_param('cmid', PARAM_INT);

$cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
require_login($cm->course, false, $cm);

$assignmentid = (int)$cm->instance;
$result = \local_ai_reflection\result_repository::get_result_by_user_and_assignment($USER->id, $assignmentid);

header('Content-Type: application/json');

if (!$result) {
    echo json_encode(['status' => 'no_submission']);
} else {
    echo json_encode(['status' => $result->status]);
}
exit;