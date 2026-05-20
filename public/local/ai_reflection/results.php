<?php
require_once(__DIR__ . '/../../config.php');

global $DB, $PAGE, $OUTPUT, $USER;

$cmid   = optional_param('cmid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$rid    = optional_param('rid', 0, PARAM_INT);
$note   = optional_param('note', '', PARAM_TEXT);

if ($cmid <= 0) {
    redirect(new moodle_url('/'));
}

$cm     = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('local/ai_reflection:viewresults', $context);

$assignment   = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);
$assignmentid = (int)$assignment->id;

// Handle save catatan guru
if ($action === 'savenote' && $rid > 0 && confirm_sesskey()) {
    require_capability('local/ai_reflection:addteachernote', $context);
    \local_ai_reflection\result_repository::save_teacher_note($rid, $note);
    redirect(new moodle_url('/local/ai_reflection/results.php', ['cmid' => $cmid]),
        'Catatan berhasil disimpan.', null, \core\output\notification::NOTIFY_SUCCESS);
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/ai_reflection/results.php', ['cmid' => $cmid]));
$PAGE->set_title('AI Reflection — ' . $assignment->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagetype('local-ai-reflection-results');

$results = \local_ai_reflection\result_repository::get_recent_results(100, $assignmentid);

echo $OUTPUT->header();

// Breadcrumb info assignment
echo html_writer::div(
    html_writer::tag('strong', 'Assignment: ') . s($assignment->name) .
    html_writer::span(' &nbsp;|&nbsp; ', '') .
    html_writer::link(
        new moodle_url('/mod/assign/view.php', ['id' => $cmid]),
        '← Kembali ke Assignment',
        ['class' => 'text-primary']
    ),
    'alert alert-info d-flex justify-content-between align-items-center'
);

echo html_writer::tag('h4', 'Hasil AI Reflection Siswa', ['class' => 'mb-4']);

if (empty($results)) {
    echo $OUTPUT->notification('Belum ada hasil AI Reflection untuk assignment ini.', 'info');
    echo $OUTPUT->footer();
    exit;
}

foreach ($results as $row) {
    $statusbadge = match((string)$row->status) {
        'done'    => '<span class="badge badge-success">Selesai</span>',
        'error'   => '<span class="badge badge-danger">Error</span>',
        'partial' => '<span class="badge badge-warning">Sebagian</span>',
        'skipped' => '<span class="badge badge-secondary">Dilewati</span>',
        default   => '<span class="badge badge-info">' . s($row->status) . '</span>',
    };

    $canaddnote = has_capability('local/ai_reflection:addteachernote', $context);

    $noteform = '';
    if ($canaddnote) {
        $saveurl = new moodle_url('/local/ai_reflection/results.php', ['cmid' => $cmid]);
        $noteform .= html_writer::start_tag('div', ['class' => 'mt-3']);
        $noteform .= html_writer::tag('label', 'Catatan Guru:', ['class' => 'font-weight-bold text-secondary d-block']);
        $noteform .= html_writer::start_tag('form', ['method' => 'post', 'action' => $saveurl->out(false)]);
        $noteform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'savenote']);
        $noteform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'rid', 'value' => $row->id]);
        $noteform .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $noteform .= html_writer::tag('textarea', s((string)($row->teachernote ?? '')), [
            'name'        => 'note',
            'class'       => 'form-control mb-2',
            'rows'        => 3,
            'placeholder' => 'Tulis catatan untuk siswa ini...',
        ]);
        $noteform .= html_writer::tag('button', 'Simpan Catatan', [
            'type'  => 'submit',
            'class' => 'btn btn-sm btn-primary',
        ]);
        $noteform .= html_writer::end_tag('form');
        $noteform .= html_writer::end_tag('div');
    }

    $cardcontent =
        html_writer::div(
            html_writer::div(
                html_writer::tag('h5',
                    fullname((object)['firstname' => $row->firstname, 'lastname' => $row->lastname]) .
                    ' &nbsp;' . $statusbadge,
                    ['class' => 'mb-0']
                ),
                'card-header bg-light d-flex align-items-center justify-content-between'
            ) .
            html_writer::div(
                // Refleksi
                html_writer::tag('p', html_writer::tag('strong', '💬 Refleksi:'), ['class' => 'mb-1']) .
                html_writer::div(nl2br(s((string)$row->reflection)), 'p-3 bg-white rounded border mb-3') .

                // Kelebihan
                (!empty($row->strengths)
                    ? html_writer::tag('p', html_writer::tag('strong', '👍 Kelebihan:'), ['class' => 'mb-1']) .
                      html_writer::div(nl2br(s((string)$row->strengths)), 'p-3 bg-white rounded border mb-3')
                    : '') .

                // Perlu ditingkatkan
                (!empty($row->improvements)
                    ? html_writer::tag('p', html_writer::tag('strong', '⬆️ Perlu Ditingkatkan:'), ['class' => 'mb-1']) .
                      html_writer::div(nl2br(s((string)$row->improvements)), 'p-3 bg-white rounded border mb-3')
                    : '') .

                // Catatan guru
                $noteform .

                // Meta info
                html_writer::div(
                    html_writer::tag('small',
                        'Dianalisis: ' . userdate((int)$row->timemodified) .
                        ' &nbsp;|&nbsp; Mode: ' . s((string)$row->analysismode),
                        ['class' => 'text-muted']
                    ),
                    'mt-3 pt-2 border-top'
                ),
                'card-body'
            ),
            'card mb-4 shadow-sm'
        );

    echo $cardcontent;
}

echo $OUTPUT->footer();