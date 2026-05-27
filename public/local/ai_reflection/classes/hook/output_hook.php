<?php
namespace local_ai_reflection\hook;

defined('MOODLE_INTERNAL') || die();

use core\hook\output\before_footer_html_generation;
use context_module;

class output_hook {
    public static function before_footer_html_generation(before_footer_html_generation $hook): void {
        global $PAGE, $USER, $OUTPUT;

        // CEK hanya jalankan jika berada di modul assignment (view.php)
        if ($PAGE->cm == null || $PAGE->cm->modname !== 'assign') {
            return;
        }

        // Hanya tampilkan di halaman utama assignment (tab Assignment)
        $action = optional_param('action', '', PARAM_ALPHA);
        $allowedactions = ['', 'view'];
        if (!in_array($action, $allowedactions)) {
            return;
        }

        // Pastikan user punya hak melihat halaman assignment ini
        $context = context_module::instance($PAGE->cm->id);
        if (!has_capability('mod/assign:view', $context)) {
            return; 
        }

        // Sembunyikan dari guru/admin - mereka punya halaman results.php
        if (has_capability('mod/assign:grade', $context)) {
            return;
        }

        //Ambil data refleksi dari database untuk user yang sedang aktif
        $assignmentid = (int)$PAGE->cm->instance;
        $result = \local_ai_reflection\result_repository::get_result_by_user_and_assignment($USER->id, $assignmentid);

        if (!$result) {
            $data = [
                'state_no_submission' => true,
                'state_processing'    => false,
                'state_error'         => false,
                'state_done'          => false,
            ];
        } else if ($result->status === 'processing') {
            $data = [
                'state_no_submission' => false,
                'state_processing'    => true,
                'state_error'         => false,
                'state_done'          => false,
            ];
        } else if ($result->status === 'error') {
            $data = [
                'state_no_submission' => false,
                'state_processing'    => false,
                'state_error'         => true,
                'state_done'          => false,
            ];
        } else if ($result->status === 'done') {
            $data = [
                'state_no_submission' => false,
                'state_processing'    => false,
                'state_error'         => false,
                'state_done'          => true,
                'reflection'          => nl2br(s($result->reflection)),
                'strengths'           => !empty($result->strengths) ? nl2br(s($result->strengths)) : null,
                'improvements'        => !empty($result->improvements) ? nl2br(s($result->improvements)) : null,
                'teachernote'         => !empty($result->teachernote) ? nl2br(s($result->teachernote)) : null,
            ];
        } else {
            // partial, skipped, atau status lain — anggap masih processing
            $data = [
                'state_no_submission' => false,
                'state_processing'    => false,
                'state_error'         => true,
                'state_done'          => false,
            ];
        }

        //Render HTML menggunakan Mustache
        $html = $OUTPUT->render_from_template('local_ai_reflection/reflection_panel', $data);

        //Suntikkan HTML ke halaman Moodle
        $hook->add_html($html);

        //Panggil AMD JS untuk memindahkan posisi panel
        $PAGE->requires->js_call_amd('local_ai_reflection/move_panel', 'init');
    }

    public static function before_standard_head_html_generation(\core\hook\output\before_standard_head_html_generation $hook): void {
        global $PAGE;

        // Panggil observer jika di halaman assignment ATAU di halaman results.php plugin ini
        $isAssignPage = ($PAGE->cm !== null && $PAGE->cm->modname === 'assign');
        $isResultsPage = ($PAGE->pagetype === 'local-ai-reflection-results');

        if (!$isAssignPage && !$isResultsPage) {
            return;
        }

        $PAGE->requires->js_call_amd('local_ai_reflection/observer', 'init');
    }
}