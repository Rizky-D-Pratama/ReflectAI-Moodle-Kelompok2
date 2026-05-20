<?php
namespace local_ai_reflection;

defined('MOODLE_INTERNAL') || die();

/**
 * Repository class untuk mengelola data tabel local_ai_reflection_result.
 */
class result_repository {

    /**
     * Menyimpan atau memperbarui hasil refleksi dari task AI Ollama.
     *
     * @param array $payload
     * @param array $result
     * @param int $payloadid
     * @return int ID dari record yang disimpan/diperbarui
     */
    public static function save_from_task(array $payload, array $result, int $payloadid): int {
        global $DB;

        $submissionid = (int)($payload['submission']['id'] ?? 0);
        $userid = (int)($payload['submission']['userid'] ?? ($payload['user']['id'] ?? 0));
        $assignmentid = (int)($payload['assignment']['id'] ?? 0);

        $record = $DB->get_record('local_ai_reflection_result', ['submissionid' => $submissionid]);

        $store = new \stdClass();
        $store->payloadid = $payloadid;
        $store->userid = $userid;
        $store->assignmentid = $assignmentid;
        $store->submissionid = $submissionid;
        $store->status = self::derive_status($result);
        $store->analysismode = (string)($result['analysis_mode'] ?? ($payload['analysis_mode'] ?? 'text'));
        $store->cananalyze = !empty($result['can_analyze']) ? 1 : 0;
        $store->visionstatus = (string)($result['vision_status'] ?? '');
        $store->needsseparatehandling = !empty($result['needs_separate_handling']) ? 1 : 0;
        $store->imagecount = (int)($result['images_count'] ?? 0);
        $store->reflection = self::clean_text((string)($result['reflection'] ?? ''));
        $store->strengths = self::clean_text((string)($result['strengths'] ?? ''));
        $store->improvements = self::clean_text((string)($result['improvements'] ?? ''));
        $store->reason = self::clean_text((string)($result['reason'] ?? ''));
        $store->prompt = self::clean_text((string)($payload['combined_prompt'] ?? ''));
        $store->responsejson = json_encode($result, JSON_UNESCAPED_UNICODE);
        $store->timemodified = time();

        if ($record) {
            $store->id = (int)$record->id;
            $store->timecreated = (int)$record->timecreated;
            $DB->update_record('local_ai_reflection_result', $store);
            return (int)$store->id;
        }

        $store->timecreated = time();
        return (int)$DB->insert_record('local_ai_reflection_result', $store);
    }

    /**
     * Mengambil daftar hasil refleksi terbaru (untuk kebutuhan halaman dashboard dosen/admin).
     *
     * @param int $limit Maksimal data yang diambil
     * @param int $assignmentid Filter berdasarkan ID assignment jika diisi
     * @return array Daftar record hasil refleksi beserta nama user dan tugas
     */
    public static function get_recent_results(int $limit = 50, int $assignmentid = 0): array {
        global $DB;

        $params = [];
        $where = '1=1';

        if ($assignmentid > 0) {
            $where .= ' AND r.assignmentid = :assignmentid';
            $params['assignmentid'] = $assignmentid;
        }

        $sql = "
            SELECT
                r.*,
                u.firstname,
                u.lastname,
                a.name AS assignmentname
            FROM {local_ai_reflection_result} r
            JOIN {user} u ON u.id = r.userid
            JOIN {assign} a ON a.id = r.assignmentid
            WHERE {$where}
            ORDER BY r.timemodified DESC, r.id DESC
        ";

        return $DB->get_records_sql($sql, $params, 0, $limit);
    }

    /**
     * Mengambil satu data hasil refleksi spesifik berdasarkan Primary ID tabel.
     *
     * @param int $id ID baris database
     * @return \stdClass|false Object data hasil atau false jika tidak ditemukan
     */
    public static function get_by_id(int $id) {
        global $DB;
        return $DB->get_record('local_ai_reflection_result', ['id' => $id]);
    }

    /**
     * Mengambil data hasil refleksi berdasarkan User ID dan Assignment ID (Spesifik per Siswa).
     * Fungsi ini digunakan untuk menampilkan UI refleksi di halaman tugas masing-masing siswa.
     *
     * @param int $userid ID user Moodle siswa
     * @param int $assignmentid ID tugas (instance tabel assign)
     * @return \stdClass|false Object data hasil refleksi siswa tersebut atau false jika belum ada
     */
    public static function get_result_by_user_and_assignment(int $userid, int $assignmentid) {
        global $DB;
        return $DB->get_record('local_ai_reflection_result', [
            'userid' => $userid,
            'assignmentid' => $assignmentid
        ]);
    }

    /**
     * Menentukan status hasil pemrosesan AI berdasarkan response mentah.
     *
     * @param array $result
     * @return string
     */
    private static function derive_status(array $result): string {
        if (!empty($result['error'])) {
            return 'error';
        }

        if (!empty($result['needs_separate_handling'])) {
            return 'partial';
        }

        if (!empty($result['skipped'])) {
            return 'skipped';
        }

        return 'done';
    }

    /**
     * Membersihkan teks dari sisa-sisa tag HTML dan spasi berlebih dari respon AI.
     *
     * @param string $text
     * @return string
     */
    private static function clean_text(string $text): string {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = preg_replace('/[ \t]+/u', ' ', $text);
        $text = preg_replace('/\R{3,}/u', "\n\n", $text);
        return trim((string)$text);
    }

        /**
     * Menyimpan catatan guru untuk hasil refleksi tertentu.
     *
     * @param int $resultid ID record hasil refleksi
     * @param string $note Catatan dari guru
     * @return bool
     */
    public static function save_teacher_note(int $resultid, string $note): bool {
        global $DB;

        $record = new \stdClass();
        $record->id = $resultid;
        $record->teachernote = clean_param($note, PARAM_TEXT);
        $record->timemodified = time();

        return $DB->update_record('local_ai_reflection_result', $record);
    }
}