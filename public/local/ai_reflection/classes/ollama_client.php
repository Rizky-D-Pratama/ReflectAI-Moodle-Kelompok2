<?php
namespace local_ai_reflection;

defined('MOODLE_INTERNAL') || die();

class ollama_client {

    private string $baseurl;
    private string $model;
    private int $batchsize;
    private int $timeoutseconds;

    public function __construct(
        string $baseurl = 'http://localhost:11434',
        string $model = 'gemma3',
        int $batchsize = 2,
        int $timeoutseconds = 240
    ) {
        $this->baseurl = rtrim($baseurl, '/');
        $this->model = $model;
        $this->batchsize = max(1, $batchsize);
        $this->timeoutseconds = max(60, $timeoutseconds);
    }

    public function analyse(array $payload): array {
        $prompt = trim((string)($payload['combined_prompt'] ?? ''));
        $images = $this->collect_images($payload);
        $analysismode = (string)($payload['analysis_mode'] ?? 'none');
        $hasstudenttext = $this->has_student_text_content($payload);

        if ($prompt === '') {
            return [
                'ok' => false,
                'skipped' => true,
                'reason' => 'Prompt kosong.',
                'vision_status' => 'skipped',
                'needs_separate_handling' => false,
                'images_count' => 0,
                'analysis_mode' => $analysismode,
            ];
        }

        if (empty($images)) {
            $textresult = $this->run_text_only_request($prompt, $analysismode);

            if ($analysismode === 'visual_limitations_only') {
                $textresult['can_analyze'] = false;
                if (trim((string)($textresult['reason'] ?? '')) === '') {
                    $textresult['reason'] = 'Dokumen terdeteksi berisi gambar/scan yang belum bisa dianalisis visual.';
                }
                if (trim((string)($textresult['reflection'] ?? '')) === '') {
                    $textresult['reflection'] = 'Terdapat gambar/scan pada PDF, tetapi analisis visual belum tersedia.';
                }
            }

            return $textresult;
        }

        return $this->analyse_with_vision_batches($prompt, $images, $hasstudenttext, $analysismode);
    }

    private function analyse_with_vision_batches(string $prompt, array $images, bool $allowtextfallback, string $analysismode): array {
        $batches = array_chunk($images, $this->batchsize);
        $batchresults = [];
        $batchsummaries = [];

        $anysuccess = false;
        $anyfailure = false;
        $timeoutseen = false;

        foreach ($batches as $batchindex => $batchimages) {
            $batchno = $batchindex + 1;
            $batchprompt = $this->build_batch_prompt($prompt, $batchno, count($batches), count($batchimages), $analysismode);

            $result = $this->run_multimodal_request($batchprompt, $batchimages, $analysismode);
            $batchresults[] = $this->compact_attempt_result("batch {$batchno}", $result, count($batchimages), 'vision');

            if (!empty($result['ok'])) {
                $anysuccess = true;
                $batchsummaries[] = $this->extract_summary($batchno, $result);
                continue;
            }

            $anyfailure = true;
            if ($this->is_timeout_error((string)($result['error'] ?? ''))) {
                $timeoutseen = true;
            }

            if ($this->is_timeout_error((string)($result['error'] ?? '')) && count($batchimages) > 1) {
                foreach ($batchimages as $singleindex => $singleimage) {
                    $singleno = $singleindex + 1;
                    $singleprompt = $this->build_single_image_prompt($prompt, $batchno, $singleno, count($batches), $analysismode);

                    $singleresult = $this->run_multimodal_request(
                        $singleprompt,
                        [$singleimage],
                        $analysismode,
                        max(90, (int)floor($this->timeoutseconds / 2))
                    );

                    $batchresults[] = $this->compact_attempt_result(
                        "batch {$batchno}.{$singleno}",
                        $singleresult,
                        1,
                        'vision_retry'
                    );

                    if (!empty($singleresult['ok'])) {
                        $anysuccess = true;
                        $batchsummaries[] = $this->extract_summary("{$batchno}.{$singleno}", $singleresult);
                    } else {
                        $anyfailure = true;
                        if ($this->is_timeout_error((string)($singleresult['error'] ?? ''))) {
                            $timeoutseen = true;
                        }
                    }
                }
            }
        }

        if (!empty($batchsummaries)) {
            $synthprompt = $this->build_synthesis_prompt($prompt, $batchsummaries, $batchresults, $anyfailure, $analysismode);
            $synth = $this->run_text_only_request($synthprompt, $analysismode);

            if (!empty($synth['ok'])) {
                $synth['can_analyze'] = !empty($synth['can_analyze']) || !empty($batchsummaries);
                $synth['vision_status'] = $anyfailure ? 'partial' : 'ok';
                $synth['needs_separate_handling'] = $anyfailure;
                $synth['batch_mode'] = true;
                $synth['batches_count'] = count($batches);
                $synth['images_count'] = count($images);
                $synth['batch_results'] = $batchresults;
                $synth['analysis_mode'] = $analysismode;

                if ($anyfailure) {
                    $synth['warning'] = $timeoutseen
                        ? 'One or more image batches timed out. Some visual content may need separate handling.'
                        : 'One or more image batches were not fully processed.';
                }

                return $synth;
            }

            return [
                'ok' => true,
                'httpcode' => 200,
                'can_analyze' => true,
                'reflection' => $this->merge_field_values($batchsummaries, 'reflection'),
                'strengths' => $this->merge_field_values($batchsummaries, 'strengths'),
                'improvements' => $this->merge_field_values($batchsummaries, 'improvements'),
                'reason' => $this->merge_field_values($batchsummaries, 'reason'),
                'error' => '',
                'error_type' => '',
                'vision_status' => $anyfailure ? 'partial' : 'ok',
                'needs_separate_handling' => $anyfailure,
                'batch_mode' => true,
                'batches_count' => count($batches),
                'images_count' => count($images),
                'batch_results' => $batchresults,
                'analysis_mode' => $analysismode,
                'warning' => $anyfailure
                    ? 'Synthesis failed, so merged batch summaries were used as fallback.'
                    : '',
            ];
        }

        if ($allowtextfallback) {
            $fallbackprompt = $this->build_text_fallback_prompt($prompt, $batchresults, $analysismode);
            $fallback = $this->run_text_only_request($fallbackprompt, $analysismode);

            if (!empty($fallback['ok'])) {
                $fallback['vision_status'] = $timeoutseen ? 'timeout' : 'partial';
                $fallback['needs_separate_handling'] = true;
                $fallback['batch_mode'] = true;
                $fallback['batches_count'] = count($batches);
                $fallback['images_count'] = count($images);
                $fallback['batch_results'] = $batchresults;
                $fallback['analysis_mode'] = $analysismode;
                $fallback['warning'] = 'Image analysis timed out or failed. Text fallback was used.';
                return $fallback;
            }
        }

        return [
            'ok' => false,
            'can_analyze' => false,
            'reflection' => '',
            'strengths' => '',
            'improvements' => '',
            'reason' => $timeoutseen
                ? 'Vision request timed out before a response was received.'
                : 'Image analysis failed.',
            'error' => $timeoutseen ? 'Vision request timeout' : 'Image analysis failed',
            'error_type' => $timeoutseen ? 'timeout' : 'error',
            'httpcode' => 0,
            'vision_status' => $timeoutseen ? 'timeout' : 'error',
            'needs_separate_handling' => true,
            'batch_mode' => true,
            'batches_count' => count($batches),
            'images_count' => count($images),
            'batch_results' => $batchresults,
            'analysis_mode' => $analysismode,
        ];
    }

    private function run_text_only_request(string $prompt, string $analysismode, ?int $timeoutseconds = null): array {
        $result = $this->request_ollama($prompt, [], $analysismode, $timeoutseconds ?? $this->timeoutseconds);

        $result['vision_status'] = 'text_only';
        $result['needs_separate_handling'] = false;
        $result['batch_mode'] = false;
        $result['batches_count'] = 0;
        $result['images_count'] = 0;
        $result['analysis_mode'] = $analysismode;

        return $result;
    }

    private function run_multimodal_request(string $prompt, array $images, string $analysismode, ?int $timeoutseconds = null): array {
        $result = $this->request_ollama($prompt, $images, $analysismode, $timeoutseconds ?? $this->timeoutseconds);

        $result['vision_status'] = !empty($images) ? 'vision' : 'text_only';
        $result['needs_separate_handling'] = false;
        $result['batch_mode'] = true;
        $result['images_count'] = count($images);
        $result['analysis_mode'] = $analysismode;

        return $result;
    }

    private function request_ollama(string $prompt, array $images, string $analysismode, int $timeoutseconds): array {
        $schema = [
            'type' => 'object',
            'properties' => [
                'can_analyze' => ['type' => 'boolean'],
                'reflection' => ['type' => 'string'],
                'strengths' => ['type' => 'string'],
                'improvements' => ['type' => 'string'],
                'reason' => ['type' => 'string'],
            ],
            'required' => ['can_analyze', 'reflection', 'strengths', 'improvements', 'reason'],
            'additionalProperties' => false,
        ];

        $system = implode("\n", [
            'You are a learning reflection assistant in an LMS.',
            'Use only the context provided. Do not fabricate content that is not in the input.',
            'Always respond in natural, polite, and specific Indonesian (Bahasa Indonesia).',
            'If the submission does not match or address the assignment description, explicitly state this in the reflection field. Do not fabricate relevance that does not exist.',
            'If there are visual limitations, state them honestly and briefly. Do not pretend images were analyzed if they were not.',
            'If mode is visual_limitations_only, set can_analyze=false and explain that the document contains images/scans that could not be visually analyzed.',
            'If mode is text_with_visual_limitations or text_with_images_and_visual_limitations, analyze the available text and briefly mention the visual limitation.',
            'If images could not be processed due to device limitations or timeout, state that images exist but could not be analyzed. Do not ignore their existence.',
            'Fill only the schema fields. Do not add any wrapper or extra explanation outside the JSON schema.',
        ]);

        $body = [
            'model' => $this->model,
            'prompt' => $prompt,
            'system' => $system,
            'format' => $schema,
            'stream' => false,
            'options' => [
                'temperature' => 0.1,
            ],
        ];

        if (!empty($images)) {
            $body['images'] = $images;
        }

        if (!function_exists('curl_init')) {
            return [
                'ok' => false,
                'error' => 'Ekstensi cURL tidak tersedia di PHP.',
                'error_type' => 'curl_missing',
                'prompt' => $prompt,
                'httpcode' => 0,
                'images_count' => count($images),
                'analysis_mode' => $analysismode,
            ];
        }

        $ch = curl_init($this->baseurl . '/api/generate');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => $timeoutseconds,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);

            return [
                'ok' => false,
                'error' => $error ?: 'Gagal menghubungi Ollama.',
                'error_type' => $this->classify_error((string)($error ?: '')),
                'prompt' => $prompt,
                'httpcode' => 0,
                'images_count' => count($images),
                'analysis_mode' => $analysismode,
            ];
        }

        $httpcode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $outer = json_decode($response, true);
        if (!is_array($outer)) {
            return [
                'ok' => false,
                'error' => 'Respons Ollama bukan JSON valid.',
                'error_type' => 'parse_error',
                'httpcode' => $httpcode,
                'raw_response' => $response,
                'prompt' => $prompt,
                'images_count' => count($images),
                'analysis_mode' => $analysismode,
            ];
        }

        $content = (string)($outer['response'] ?? '');
        $structured = json_decode($content, true);

        if (!is_array($structured)) {
            return [
                'ok' => false,
                'error' => 'Output model bukan JSON valid.',
                'error_type' => 'output_parse_error',
                'httpcode' => $httpcode,
                'raw_response' => $outer,
                'model_output' => $content,
                'prompt' => $prompt,
                'images_count' => count($images),
                'analysis_mode' => $analysismode,
            ];
        }

        return [
            'ok' => true,
            'httpcode' => $httpcode,
            'can_analyze' => !empty($structured['can_analyze']),
            'reflection' => (string)($structured['reflection'] ?? ''),
            'strengths' => (string)($structured['strengths'] ?? ''),
            'improvements' => (string)($structured['improvements'] ?? ''),
            'reason' => (string)($structured['reason'] ?? ''),
            'error' => '',
            'error_type' => '',
            'raw_response' => $outer,
            'structured_response' => $structured,
            'prompt' => $prompt,
            'images_count' => count($images),
            'analysis_mode' => $analysismode,
        ];
    }

    private function build_batch_prompt(string $prompt, int $batchno, int $batchcount, int $imagecount, string $analysismode): string {
        return $prompt . "\n\n"
            . "=== IMAGE BATCH {$batchno}/{$batchcount} ===\n"
            . "Ada {$imagecount} gambar pada batch ini.\n"
            . "Mode analisis: {$analysismode}\n"
            . "Pertimbangkan gambar-gambar ini sebagai bagian dari konteks yang diberikan.\n"
            . "Jika detail visual tidak terlihat jelas, sebutkan batasannya.\n";
    }

    private function build_single_image_prompt(string $prompt, int $batchno, int $imageno, int $batchcount, string $analysismode): string {
        return $prompt . "\n\n"
            . "=== IMAGE BATCH {$batchno}/{$batchcount} - SINGLE IMAGE {$imageno} ===\n"
            . "Mode analisis: {$analysismode}\n"
            . "Analisis hanya gambar ini sebagai bagian dari konteks yang diberikan.\n";
    }

    private function build_synthesis_prompt(string $prompt, array $summaries, array $batchresults, bool $hadfailure, string $analysismode): string {
        $out = [];
        $out[] = '=== ORIGINAL TASK ===';
        $out[] = $prompt;
        $out[] = '';
        $out[] = '=== ANALYSIS MODE ===';
        $out[] = $analysismode;
        $out[] = '';
        $out[] = '=== BATCH SUMMARIES ===';

        foreach ($summaries as $summary) {
            $out[] = 'Batch: ' . ($summary['batch'] ?? '?');
            $out[] = 'reflection: ' . ($summary['reflection'] ?? '');
            $out[] = 'strengths: ' . ($summary['strengths'] ?? '');
            $out[] = 'improvements: ' . ($summary['improvements'] ?? '');
            $out[] = 'reason: ' . ($summary['reason'] ?? '');
            $out[] = '';
        }

        if ($hadfailure) {
            $out[] = 'Some image batches could not be fully processed and may need separate handling.';
            $out[] = '';
        }

        $out[] = 'Combine the batch results into one final reflection that stays specific and faithful to the submission.';
        $out[] = 'If there are visual limitations, mention them briefly and accurately.';
        return trim(implode("\n", $out));
    }

    private function build_text_fallback_prompt(string $prompt, array $batchresults, string $analysismode): string {
        $out = [];
        $out[] = '=== ORIGINAL TASK ===';
        $out[] = $prompt;
        $out[] = '';
        $out[] = '=== ANALYSIS MODE ===';
        $out[] = $analysismode;
        $out[] = '';
        $out[] = '=== IMAGE PROCESSING NOTE ===';
        $out[] = 'Image analysis timed out or failed. Do not invent details from unavailable images.';
        $out[] = 'Use only the textual content and assignment context.';
        $out[] = '';

        if (!empty($batchresults)) {
            $out[] = '=== FAILED IMAGE ATTEMPTS ===';
            foreach ($batchresults as $attempt) {
                $out[] = 'Attempt: ' . ($attempt['attempt'] ?? '?');
                $out[] = 'status: ' . (!empty($attempt['ok']) ? 'ok' : 'failed');
                $out[] = 'error: ' . ($attempt['error'] ?? '');
                $out[] = '';
            }
        }

        $out[] = 'Provide a useful reflection based on the available text only.';
        return trim(implode("\n", $out));
    }

    private function extract_summary(int|string $batchlabel, array $result): array {
        return [
            'batch' => (string)$batchlabel,
            'reflection' => trim((string)($result['reflection'] ?? '')),
            'strengths' => trim((string)($result['strengths'] ?? '')),
            'improvements' => trim((string)($result['improvements'] ?? '')),
            'reason' => trim((string)($result['reason'] ?? '')),
        ];
    }

    private function compact_attempt_result(string $attempt, array $result, int $imagecount, string $mode): array {
        return [
            'attempt' => $attempt,
            'mode' => $mode,
            'ok' => (bool)($result['ok'] ?? false),
            'httpcode' => (int)($result['httpcode'] ?? 0),
            'error' => (string)($result['error'] ?? ''),
            'error_type' => (string)($result['error_type'] ?? ''),
            'can_analyze' => (bool)($result['can_analyze'] ?? false),
            'images_count' => $imagecount,
            'vision_status' => (string)($result['vision_status'] ?? ''),
        ];
    }

    private function merge_field_values(array $items, string $field): string {
        $parts = [];

        foreach ($items as $item) {
            $value = trim((string)($item[$field] ?? ''));
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        $parts = array_values(array_unique($parts));
        return trim(implode("\n\n", $parts));
    }

    private function collect_images(array $payload): array {
        $images = [];
        $sources = [];

        if (!empty($payload['assignment']['additional_files']) && is_array($payload['assignment']['additional_files'])) {
            $sources[] = $payload['assignment']['additional_files'];
        }

        if (!empty($payload['attachments']) && is_array($payload['attachments'])) {
            $sources[] = $payload['attachments'];
        }

        foreach ($sources as $group) {
            foreach ($group as $item) {
                if (empty($item['images_base64']) || !is_array($item['images_base64'])) {
                    continue;
                }

                foreach ($item['images_base64'] as $img) {
                    if (is_string($img) && $img !== '') {
                        $images[] = $img;
                    }
                }
            }
        }

        return $images;
    }

    private function has_student_text_content(array $payload): bool {
        if (!empty(trim((string)($payload['text'] ?? '')))) {
            return true;
        }

        if (!empty($payload['content']['submission_attachments_text']) && is_array($payload['content']['submission_attachments_text'])) {
            foreach ($payload['content']['submission_attachments_text'] as $item) {
                if (!empty($item['text'])) {
                    return true;
                }
            }
        }

        return false;
    }

    private function is_timeout_error(string $error): bool {
        $error = strtolower($error);
        return str_contains($error, 'timed out') || str_contains($error, 'timeout');
    }

    private function classify_error(string $error): string {
        return $this->is_timeout_error($error) ? 'timeout' : 'error';
    }
}