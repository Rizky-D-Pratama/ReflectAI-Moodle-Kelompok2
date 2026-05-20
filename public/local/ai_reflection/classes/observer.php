<?php
namespace local_ai_reflection;

defined('MOODLE_INTERNAL') || die();

class observer {

    public static function assignment_submitted(\core\event\base $event): void {
        global $DB;

        $userid       = (int)$event->userid;
        $context      = $event->get_context();
        $cm           = get_coursemodule_from_id('assign', $context->instanceid, 0, false, MUST_EXIST);
        $assignmentid = (int)$cm->instance;

        $existing = result_repository::get_result_by_user_and_assignment($userid, $assignmentid);

        if ($existing) {
            $DB->set_field('local_ai_reflection_result', 'status', 'processing', ['id' => $existing->id]);
        } else {
            $record                      = new \stdClass();
            $record->payloadid           = 0;
            $record->userid              = $userid;
            $record->assignmentid        = $assignmentid;
            $record->submissionid        = (int)$event->objectid;
            $record->status              = 'processing';
            $record->analysismode        = '';
            $record->cananalyze          = 0;
            $record->visionstatus        = '';
            $record->needsseparatehandling = 0;
            $record->imagecount          = 0;
            $record->timecreated         = time();
            $record->timemodified        = time();
            $DB->insert_record('local_ai_reflection_result', $record);
        }

        $task = new \local_ai_reflection\task\process_submission_task();
        $task->set_custom_data([
            'contextid' => $context->id,
            'userid'    => $userid,
            'objectid'  => $event->objectid,
        ]);

        \core\task\manager::queue_adhoc_task($task);
    }

    public static function submission_removed(\core\event\base $event): void {
        global $DB;

        $userid       = (int)$event->userid;
        $context      = $event->get_context();
        $cm           = get_coursemodule_from_id('assign', $context->instanceid, 0, false, MUST_EXIST);
        $assignmentid = (int)$cm->instance;

        $DB->delete_records('local_ai_reflection_result', [
            'userid'       => $userid,
            'assignmentid' => $assignmentid,
        ]);
    }
}