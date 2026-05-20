<?php
namespace local_ai_reflection;

defined('MOODLE_INTERNAL') || die();

class payload_extractor {

    public static function from_submission(\assign $assign, \stdClass $submission, int $userid): array {
        global $DB;

        $user       = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $assignment = $assign->get_instance();

        $payload = [
            'assignment' => [
                'id'               => (int)$assignment->id,
                'name'             => (string)$assignment->name,
                'description'      => self::clean_text((string)($assignment->intro ?? '')),
                'grade_max'        => isset($assignment->grade) ? (float)$assignment->grade : null,
                'grading_method'   => 'unknown',
                'rubric_context'   => null,
                'additional_files' => [],
            ],
            'submission' => [
                'id'            => (int)$submission->id,
                'userid'        => (int)$userid,
                'status'        => (string)($submission->status ?? ''),
                'timecreated'   => (int)($submission->timecreated ?? 0),
                'timemodified'  => (int)($submission->timemodified ?? 0),
                'attemptnumber' => (int)($submission->attemptnumber ?? -1),
            ],
            'user' => [
                'id'       => (int)$user->id,
                'fullname' => fullname($user),
                'email'    => (string)$user->email,
            ],
            'content' => [
                'assignment_context_text'    => '',
                'online_text'                => '',
                'additional_files_text'      => [],
                'submission_attachments_text' => [],
                'visual_limitations'         => [],
            ],
            'text'        => '',
            'attachments' => [],
        ];

        // Build assignment context
        $assignmentcontext = self::build_assignment_context($assign);
        $payload['assignment']['additional_files'] = $assignmentcontext['additional_files'];
        $payload['assignment']['rubric_context']   = $assignmentcontext['rubric_context'];
        $payload['assignment']['grading_method']   = $assignmentcontext['grading_method'];
        $payload['content']['assignment_context_text'] = $assignmentcontext['assignment_context_text'];
        $payload['content']['visual_limitations']  = $assignmentcontext['visual_limitations'];

        // Extract online text
        foreach ($assign->get_submission_plugins() as $plugin) {
            if (!$plugin->is_enabled() || !$plugin->is_visible()) {
                continue;
            }

            if ($plugin->get_type() === 'onlinetext') {
                $text = self::clean_text((string)$plugin->get_editor_text('onlinetext', $submission->id));
                if ($text !== '') {
                    $payload['text'] .= ($payload['text'] !== '' ? "\n\n" : '') . $text;
                    $payload['content']['online_text'] = $payload['text'];
                }
            }
        }

        // Extract file attachments
        foreach ($assign->get_submission_plugins() as $plugin) {
            if (!$plugin->is_enabled() || !$plugin->is_visible()) {
                continue;
            }

            if ($plugin->get_type() === 'file') {
                $context = $assign->get_context();
                $files   = $plugin->get_files($submission, $context);

                foreach ($files as $file) {
                    if (!$file instanceof \stored_file || $file->is_directory()) {
                        continue;
                    }

                    $attachment = attachment_router::route($file);
                    $payload['attachments'][] = $attachment;

                    if (!empty($attachment['extracted_text'])) {
                        $payload['content']['submission_attachments_text'][] = [
                            'filename' => $attachment['filename'],
                            'mimetype' => $attachment['mimetype'],
                            'text'     => $attachment['extracted_text'],
                        ];
                    }

                    if (self::has_visual_limitation($attachment)) {
                        $payload['content']['visual_limitations'][] =
                            self::build_visual_limitation_message($attachment, 'submission attachment');
                    }
                }
            }
        }

        // Determine analysis mode
        $payload['analysis_mode']         = self::determine_analysis_mode($payload);
        $payload['should_call_ai']        = $payload['analysis_mode'] !== 'none';
        $payload['can_analyze']           = in_array($payload['analysis_mode'], [
            'text', 'text_with_images', 'images',
            'text_with_images_and_visual_limitations',
            'text_with_visual_limitations',
        ], true);
        $payload['analysis_skip_reason']  = match($payload['analysis_mode']) {
            'none'                   => 'Tidak ada teks atau keterbatasan visual yang perlu dianalisis.',
            'visual_limitations_only' => 'Dokumen terdeteksi berisi gambar/scan yang belum bisa dianalisis visual.',
            default                  => '',
        };

        $payload['combined_prompt'] = self::build_prompt($payload);

        return $payload;
    }

    private static function determine_analysis_mode(array $payload): string {
        $hasstudenttext = trim((string)($payload['text'] ?? '')) !== '';

        if (!$hasstudenttext) {
            foreach (($payload['content']['submission_attachments_text'] ?? []) as $item) {
                if (!empty($item['text'])) {
                    $hasstudenttext = true;
                    break;
                }
            }
        }

        $hassubmissionimages = false;
        foreach (($payload['attachments'] ?? []) as $att) {
            if (!empty($att['images_base64'])) {
                $hassubmissionimages = true;
                break;
            }
        }

        $hasvisuallimitations = !empty($payload['content']['visual_limitations']);

        if ($hasstudenttext && $hassubmissionimages && $hasvisuallimitations) {
            return 'text_with_images_and_visual_limitations';
        }
        if ($hasstudenttext && $hassubmissionimages) {
            return 'text_with_images';
        }
        if ($hasstudenttext && $hasvisuallimitations) {
            return 'text_with_visual_limitations';
        }
        if ($hasstudenttext) {
            return 'text';
        }
        if ($hassubmissionimages && $hasvisuallimitations) {
            return 'images_with_visual_limitations';
        }
        if ($hassubmissionimages) {
            return 'images';
        }
        if ($hasvisuallimitations) {
            return 'visual_limitations_only';
        }

        return 'none';
    }

    private static function build_assignment_context(\assign $assign): array {
        $assignment = $assign->get_instance();
        $context    = $assign->get_context();
        $description = self::clean_text((string)($assignment->intro ?? ''));

        $additionalfiles  = [];
        $visuallimitations = [];

        $fs    = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_assign', 'introattachment', 0, 'filename', false);

        foreach ($files as $file) {
            if (!$file instanceof \stored_file || $file->is_directory()) {
                continue;
            }

            $item = attachment_router::route($file);
            $additionalfiles[] = $item;

            if (self::has_visual_limitation($item)) {
                $visuallimitations[] = self::build_visual_limitation_message($item, 'assignment additional file');
            }
        }

        return [
            'assignment_context_text' => $description,
            'additional_files'        => $additionalfiles,
            'visual_limitations'      => $visuallimitations,
            'grading_method'          => 'unknown',
            'rubric_context'          => null,
        ];
    }

    private static function build_prompt(array $payload): string {
        $out = [];

        $out[] = 'You are a learning reflection assistant in an LMS.';
        $out[] = 'Use only the context actually provided below.';
        $out[] = 'Do not fabricate content that is not in the input.';
        $out[] = 'Always respond in natural, polite, and specific Indonesian (Bahasa Indonesia).';
        $out[] = '';

        $out[] = '=== ANALYSIS MODE ===';
        $out[] = 'Mode: ' . ($payload['analysis_mode'] ?? 'none');
        $out[] = '';

        $out[] = '=== ASSIGNMENT CONTEXT ===';
        $out[] = 'Assignment name: ' . ($payload['assignment']['name'] ?? '');
        $out[] = 'Assignment description: ' . ($payload['assignment']['description'] ?? '');
        $out[] = 'Maximum grade: ' . (($payload['assignment']['grade_max'] ?? null) === null ? 'unknown' : (string)$payload['assignment']['grade_max']);
        $out[] = 'Grading method: ' . ($payload['assignment']['grading_method'] ?? 'unknown');
        $out[] = '';

        if (!empty($payload['content']['assignment_context_text'])) {
            $out[] = '=== ASSIGNMENT DESCRIPTION ===';
            $out[] = $payload['content']['assignment_context_text'];
            $out[] = '';
        }

        if (!empty($payload['assignment']['additional_files'])) {
            $out[] = '=== ASSIGNMENT ADDITIONAL FILES ===';
            foreach ($payload['assignment']['additional_files'] as $file) {
                $out[] = 'File: ' . ($file['filename'] ?? '') . ' (' . ($file['mimetype'] ?? '') . ')';
                if (!empty($file['extracted_text'])) {
                    $out[] = self::trim_preview((string)$file['extracted_text'], 3000);
                } elseif (!empty($file['images_base64'])) {
                    $out[] = '[Image attached in assignment additional files]';
                } elseif (!empty($file['note'])) {
                    $out[] = '[' . $file['note'] . ']';
                }
                $out[] = '';
            }
        }

        $out[] = '=== STUDENT SUBMISSION ===';
        $out[] = 'Student name: ' . (string)($payload['user']['fullname'] ?? '');
        $out[] = 'Online text: ' . (trim((string)($payload['text'] ?? '')) !== '' ? (string)$payload['text'] : '[none]');
        $out[] = '';

        if (!empty($payload['content']['submission_attachments_text'])) {
            $out[] = '=== SUBMISSION ATTACHMENTS TEXT ===';
            foreach ($payload['content']['submission_attachments_text'] as $item) {
                $out[] = 'File: ' . ($item['filename'] ?? '') . ' (' . ($item['mimetype'] ?? '') . ')';
                $out[] = self::trim_preview((string)($item['text'] ?? ''), 5000);
                $out[] = '';
            }
        }

        if (!empty($payload['content']['visual_limitations'])) {
            $out[] = '=== VISUAL LIMITATIONS ===';
            foreach ($payload['content']['visual_limitations'] as $line) {
                $out[] = '- ' . $line;
            }
            $out[] = '';
        }

        $mode = (string)($payload['analysis_mode'] ?? 'none');
        $out[] = '=== INSTRUCTION ===';
        $out[] = match($mode) {
            'visual_limitations_only'                    => 'The submission appears to contain image/scan-based content that could not be analyzed visually. State the limitation clearly and keep the response faithful to the available context.',
            'text_with_visual_limitations'               => 'Analyze the available text normally, and mention briefly that some visual PDF content was detected but not visually analyzed.',
            'text_with_images_and_visual_limitations'    => 'Analyze the available text and attached images, and mention briefly that some visual PDF content was detected but not visually analyzed.',
            'text_with_images'                           => 'Analyze the text and attached images together.',
            'images'                                     => 'Analyze the attached images together with the available context.',
            'images_with_visual_limitations'             => 'Analyze the attached images together with the available context, and mention briefly that some visual PDF content was detected but not visually analyzed.',
            default                                      => 'Analyze the available context normally.',
        };
        $out[] = '';

        $out[] = '=== EVALUATION FOCUS ===';
        $out[] = '- First, verify whether the submission actually addresses this specific assignment. If the submission is irrelevant or does not match the assignment description, state this explicitly in the reflection field. Do not fabricate relevance.';
        $out[] = '- Relevance to the assignment task';
        $out[] = '- Clarity and coherence of the submission';
        $out[] = '- Completeness of the content';
        $out[] = '- Specific strengths of the submission';
        $out[] = '- Specific improvements the student can make';
        $out[] = '';
        $out[] = 'IMPORTANT: Be specific and faithful to the actual submission content. Do not generalize or invent details not present in the input.';
        $out[] = '';
        $out[] = 'CRITICAL CONSISTENCY RULE:';
        $out[] = '- If the reflection states that the submission is irrelevant or does not match the assignment, then:';
        $out[] = '  * strengths field: mention only what is good about the submission AS A STANDALONE WORK, but explicitly note it does not fulfill the assignment.';
        $out[] = '  * improvements field: focus on what the student needs to do to actually fulfill THIS specific assignment.';
        $out[] = '- Do NOT contradict the reflection in the strengths or improvements fields.';
        $out[] = '- All three fields (reflection, strengths, improvements) must tell a consistent story.';

        return trim(implode("\n", $out));
    }

    private static function has_visual_limitation(array $attachment): bool {
        $kind = (string)($attachment['kind'] ?? '');
        if (in_array($kind, ['pdf_hybrid', 'pdf_image_only', 'pdf_unknown'], true)) {
            return true;
        }
        $note = strtolower((string)($attachment['note'] ?? ''));
        return str_contains($note, 'gambar') || str_contains($note, 'scan');
    }

    private static function build_visual_limitation_message(array $attachment, string $scope): string {
        $kind     = (string)($attachment['kind'] ?? '');
        $filename = (string)($attachment['filename'] ?? 'file');

        return match($kind) {
            'pdf_image_only' => "File {$scope} '{$filename}' terdeteksi sebagai PDF berisi gambar/scan tanpa teks bermakna. Konten visual belum bisa dianalisis.",
            'pdf_hybrid'     => "File {$scope} '{$filename}' berisi teks dan gambar. Teks berhasil diekstrak, tetapi bagian visual belum dianalisis.",
            'pdf_unknown'    => "File {$scope} '{$filename}' berupa PDF tetapi teksnya tidak berhasil diekstrak. Konten visual belum bisa dianalisis.",
            default          => (($note = (string)($attachment['note'] ?? '')) !== '')
                                ? "File {$scope} '{$filename}': {$note}"
                                : "File {$scope} '{$filename}' memiliki keterbatasan visual yang belum dianalisis.",
        };
    }

    private static function trim_preview(string $text, int $limit): string {
        $text = self::clean_text($text);
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        return mb_substr($text, 0, $limit) . '…';
    }

    private static function clean_text(string $text): string {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = preg_replace('/[ \t]+/u', ' ', $text);
        $text = preg_replace('/\R{3,}/u', "\n\n", $text);
        return trim((string)$text);
    }
}