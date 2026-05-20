<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace aiprovider_datacurso\httpclient;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

/**
 * Class datacurso_api_base
 * Base class for interacting with Datacurso APIs.
 * @package    aiprovider_datacurso
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class datacurso_api_base {
    /** @var string $baseurl */
    /** Services that depend on the local Moodle webservice. */
    private const SERVICES_REQUIRING_WEBSERVICE = [
        'local_assign_ai',
        'local_forum_ai',
        'local_dttutor',
    ];

    /** @var string $baseurl The base URL for Datacurso API requests */
    protected $baseurl;

    /** @var string|null $licensekey */
    protected $licensekey;

    /** @var object|null $instanceprovider */
    protected $instanceprovider;

    /**
     * Constructor.
     *
     * @param string $baseurl
     * @param string|null $licensekey
     */
    public function __construct(string $baseurl, ?string $licensekey = null) {
        global $DB;

        $manager = new \core_ai\manager($DB);
        $instances = $manager->get_provider_instances();
        $licensekey = '';

        foreach ($instances as $instance) {
            if ($instance->get_name() === 'aiprovider_datacurso' && $instance->enabled === true) {
                $config = $instance->config;
                if (!empty($config['licensekey'])) {
                    $this->instanceprovider = $instance;
                    $licensekey = $config['licensekey'];
                    break;
                }
            }
        }
        if ($this->instanceprovider == null) {
            throw new \moodle_exception('instance_disabled', 'aiprovider_datacurso');
        }
        $this->baseurl = rtrim($baseurl, '/');
        $this->licensekey = $licensekey;
    }

    /**
     * Returns the base URL for Datacurso API requests.
     */
    public function get_base_url(): string {
        return $this->baseurl . '/';
    }

    /**
     * Download a file from Datacurso API.
     *
     * @param string $endpoint The API endpoint for the file download.
     * @param string $filename The desired name for the downloaded file.
     * @param array $filerecord Additional file record information.
     */
    public function download_file($endpoint, $filename, $filerecord = []): ?\stored_file {
        global $USER;

        $baseurl = $this->get_base_url();
        $packageurl = $baseurl . ltrim($endpoint, '/');

        $userid = $USER->id;
        $draftid = file_get_unused_draft_itemid();

        $fs = get_file_storage();
        $context = \context_user::instance($userid);

        $fileinfo = [
            'contextid' => $context->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftid,
            'filepath' => '/',
            'filename' => $filename,
        ];

        $fileinfo = array_merge($fileinfo, $filerecord);

        $options['headers'] = [
            'License-Key: ' . $this->licensekey,
        ];

        return $fs->create_file_from_url($fileinfo, $packageurl, $options, true);
    }

    /**
     * Generic handler for HTTP calls to Datacurso API.
     *
     * @param string $method The HTTP method (GET, POST, etc.).
     * @param string $path The API path/endpoint.
     * @param array $payload The request body or GET parameters.
     * @param array $headers Additional HTTP headers.
     * @return array|null The decoded JSON response array, or null on failure.
     */
    protected function send_request(string $method, string $path, $payload = [], array $headers = []): ?array {
        global $USER, $CFG;

        if (empty($this->licensekey)) {
            debugging('Cannot make this request: invalid license key', DEBUG_DEVELOPER);
            throw new \moodle_exception('invalidlicensekey', 'aiprovider_datacurso');
        }

        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        // Rate limit.
        // Enforce per-user, per-service rate limit using cached DB pre-check.
        // Service could be null if the path is not mapped.
        $serviceid = \aiprovider_datacurso\local\ratelimiter::resolve_service_for_path($path);
        $this->enforce_webservice_requirements($serviceid);
        $userid = (string)($payload['userid'] ?? $USER->id);
        $ratelimiter = new \aiprovider_datacurso\local\ratelimiter($this->instanceprovider);

        // Validate if user is allowed to make this request.
        if (!empty($serviceid) && !$ratelimiter->is_user_allowed($serviceid, $userid, $path)) {
            throw new \moodle_exception('notallowed', 'aiprovider_datacurso');
        }

         // Enforce user global quota (across services) first.
        if (!$ratelimiter->precheck_user_quota($userid)) {
            $snapshot = $ratelimiter->get_user_quota_snapshot($userid);
            $details = '';
            if (is_array($snapshot) && ($snapshot['limit'] ?? 0) > 0) {
                $details = $snapshot['used'] . '/' . $snapshot['limit'];
            }
            throw new \moodle_exception('error_usertokenlimit_exceeded', 'aiprovider_datacurso', '', $details);
        }

        if (!empty($serviceid) && !$ratelimiter->precheck($serviceid, $userid)) {
            $remaining = $ratelimiter->get_time_until_next_window((string)$serviceid, (int)$userid);
            $retrytimestamp = time() + max(0, (int)$remaining);
            $retryat = userdate($retrytimestamp, get_string('strftimedatetime', 'langconfig'));
            throw new \moodle_exception('error_ratelimit_exceeded', 'aiprovider_datacurso', '', $retryat);
        }

        $curl = new \curl();
        $baseheaders = [
            'License-Key: ' . $this->licensekey,
        ];

        $headers = array_merge($baseheaders, $headers);

        $options = [
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_HTTPHEADER' => $headers,
        ];

        $url = $this->baseurl . $path;

        $defaultpayload = [
            'site_id' => md5($CFG->wwwroot),
            'userid' => $userid,
            'timezone' => \core_date::get_user_timezone(),
            'lang' => $payload['lang'] ?? current_language(),
        ];

        switch (strtoupper($method)) {
            case 'GET':
                $response = $curl->get($url, $payload, $options);
                break;

            case 'POST':
                $payload = array_merge($payload, $defaultpayload);
                $response = $curl->post($url, json_encode($payload), $options);
                // Store response in log file in moodledata/temp/datacurso_api.log.
                file_put_contents($CFG->dataroot . '/temp/datacurso_api.log', $response, FILE_APPEND);
                break;

            case 'PUT':
                $payload = array_merge($payload, $defaultpayload);
                $response = $curl->put($url, $payload, $options);
                break;

            case 'DELETE':
                $response = $curl->delete($url, $payload, $options);
                break;

            case 'UPLOAD':
                $payload = array_merge($payload, $defaultpayload);
                $response = $curl->post($url, $payload, $options);
                break;

            default:
                throw new \coding_exception('Invalid HTTP method: ' . $method);
        }

        if (!$response) {
            debugging('Empty response from Datacurso API', DEBUG_DEVELOPER);
            throw new \moodle_exception('emptyresponse', 'aiprovider_datacurso');
        }

        if ($curl->error) {
            debugging('cURL error (' . $curl->error . ')', DEBUG_DEVELOPER);
            throw new \moodle_exception('curlerror', 'aiprovider_datacurso', '', $curl->error);
        }

        $httpcode = $curl->get_info()['http_code'] ?? 0;

        // Handle API 403 errors.
        if ($httpcode == 403) {
            $decodedresponse = json_decode($response, true);

            if (($decodedresponse['detail'] ?? '') === 'tokens_not_sufficient') {
                throw new \moodle_exception('notenoughtokens', 'aiprovider_datacurso');
            }

            if (($decodedresponse['detail'] ?? '') === 'license_not_allowed') {
                throw new \moodle_exception('license_not_allowed', 'aiprovider_datacurso');
            }

            throw new \moodle_exception('forbidden', 'aiprovider_datacurso');
        }

        if ($httpcode >= 400) {
            debugging("HTTP error {$httpcode} from Datacurso API: {$response}", DEBUG_DEVELOPER);
            debugging("PAYLOAD: {$payload}", DEBUG_DEVELOPER);
            throw new \moodle_exception('httperror', 'aiprovider_datacurso', '', $httpcode);
        }

        $decodedresponse = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            debugging('JSON decode error: ' . json_last_error_msg(), DEBUG_DEVELOPER);
            throw new \moodle_exception('jsondecodeerror', 'aiprovider_datacurso', '', json_last_error_msg());
        }

        // Post-success syncs: only after a valid, non-error response.
        if (!empty($serviceid)) {
            $ratelimiter->sync_after_success($serviceid, $userid, $path);
        }
        $ratelimiter->sync_user_quota_after_success($userid, $path);

        $ratelimiter->sync_user_quota_after_success($userid, $path);
        return $decodedresponse;
    }

    /**
     * Standard JSON API call.
     *
     * @param string $method The HTTP method (GET, POST, etc.).
     * @param string $path The API path/endpoint.
     * @param array $body The request body or GET parameters.
     * @return array|null The decoded JSON response array, or null on failure.
     */
    public function request(string $method, string $path, array $body = []): ?array {
        $headers = ['Content-Type: application/json'];
        return $this->send_request($method, $path, $body, $headers);
    }

    /**
     * Upload a file using multipart/form-data.
     *
     * @param string $path The API path/endpoint for the upload.
     * @param string $filepath The local path to the file to be uploaded.
     * @param string|null $mimetype The MIME type of the file.
     * @param string|null $filename The desired filename for the upload.
     * @param array $extraparams Additional parameters to send in the form data.
     * @return array|null The decoded JSON response array, or null on failure.
     */
    public function upload_file(
        string $path,
        string $filepath,
        ?string $mimetype = null,
        ?string $filename = null,
        array $extraparams = []
    ): ?array {

        if (!file_exists($filepath)) {
            $filename = basename($filepath);
            throw new \coding_exception("File not found: {$filename}");
        }

        $postdata = array_merge($extraparams, [
            'file' => new \CURLFile($filepath, $mimetype, $filename),
        ]);

        return $this->send_request('UPLOAD', $path, $postdata);
    }

    /**
     * Ensure the Datacurso webservice is fully configured when required by the service.
     *
     * @param string|null $serviceid
     * @return void
     */
    private function enforce_webservice_requirements(?string $serviceid): void {
        if (empty($serviceid) || !in_array($serviceid, self::SERVICES_REQUIRING_WEBSERVICE, true)) {
            return;
        }

        if (!\aiprovider_datacurso\webservice_config::is_configured()) {
            $setupurl = \aiprovider_datacurso\webservice_config::get_url();
            $messageparams = (object)['url' => $setupurl->out(false)];
            throw new \moodle_exception('error_webservice_not_configured', 'aiprovider_datacurso', '', $messageparams);
        }
    }

    /**
     * Check if the license is for European Union.
     *
     * @return bool
     */
    public function is_for_ue(): bool {
        $datacursoapi = new datacurso_api();
        $response = $datacursoapi->get('tokens/saldo');
        return $response['is_for_eu'] == true;
    }
}
