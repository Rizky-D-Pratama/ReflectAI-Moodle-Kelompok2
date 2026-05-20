<?php
namespace local_ai_reflection\task;

defined('MOODLE_INTERNAL') || die();

class process_submission_task extends \core\task\adhoc_task {

    public function execute(): void {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $data     = (array)$this->get_custom_data();
        $context  = \context::instance_by_id((int)$data['contextid']);
        $cm       = get_coursemodule_from_id('assign', $context->instanceid, 0, false, MUST_EXIST);
        $course   = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $assign   = new \assign($context, $cm, $course);
        $userid   = (int)$data['userid'];

        $submission = $assign->get_user_submission($userid, false, -1);
        if (!$submission) {
            mtrace('No submission found.');
            return;
        }

        mtrace('Extracting payload...');
        $payload      = \local_ai_reflection\payload_extractor::from_submission($assign, $submission, $userid);
        $storepayload = self::compact_payload_for_storage($payload);

        $record              = new \stdClass();
        $record->userid      = $userid;
        $record->assignmentid = (int)$assign->get_instance()->id;
        $record->submissionid = (int)$submission->id;
        $record->timecreated = time();
        $record->payloadjson = json_encode($storepayload, JSON_UNESCAPED_UNICODE);

        $recordid             = $DB->insert_record('local_ai_reflection_payload', $record);
        $payload['payloadid'] = (int)$recordid;

        if (!$payload['should_call_ai']) {
            mtrace('SKIPPED: ' . $payload['analysis_skip_reason']);
            return;
        }

        mtrace('Sending to Ollama...');
        $client = new \local_ai_reflection\ollama_client('http://localhost:11434', 'gemma3');
        $result = $client->analyse($payload);

        mtrace('Ollama response: ' . ($result['ok'] ? 'ok' : 'failed - ' . ($result['error'] ?? '')));

        $storepayload['ollama'] = self::compact_ollama_result($result);
        $record->id          = $recordid;
        $record->payloadjson = json_encode($storepayload, JSON_UNESCAPED_UNICODE);
        $DB->update_record('local_ai_reflection_payload', $record);

        \local_ai_reflection\result_repository::save_from_task($payload, $result, (int)$recordid);

        mtrace('Task complete.');
    }

    private static function compact_payload_for_storage(array $payload): array {
        return self::strip_keys_recursively($payload, [
            'images_base64',
            'raw_base64',
            'binary_content',
        ]);
    }

    private static function strip_keys_recursively(array $data, array $keys): array {
        foreach ($data as $key => $value) {
            if (in_array($key, $keys, true)) {
                unset($data[$key]);
                continue;
            }

            if (is_array($value)) {
                $data[$key] = self::strip_keys_recursively($value, $keys);
            }
        }

        return $data;
    }

    private static function compact_ollama_result(array $result): array {
        return [
            'ok' => (bool)($result['ok'] ?? false),
            'httpcode' => (int)($result['httpcode'] ?? 0),
            'can_analyze' => (bool)($result['can_analyze'] ?? false),
            'reflection' => (string)($result['reflection'] ?? ''),
            'strengths' => (string)($result['strengths'] ?? ''),
            'improvements' => (string)($result['improvements'] ?? ''),
            'reason' => (string)($result['reason'] ?? ''),
            'error' => (string)($result['error'] ?? ''),
            'error_type' => (string)($result['error_type'] ?? ''),
            'analysis_mode' => (string)($result['analysis_mode'] ?? ''),
            'vision_status' => (string)($result['vision_status'] ?? ''),
            'needs_separate_handling' => (bool)($result['needs_separate_handling'] ?? false),
        ];
    }
}