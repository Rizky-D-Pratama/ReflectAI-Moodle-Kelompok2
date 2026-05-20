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

/**
 * Class ai_course_api
 *
 * HTTP client for the Datacurso AI service for course generation.
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_course_api extends datacurso_api_base {
    /** Default base URL for the standard DataCurso course AI service. */
    private const DEFAULT_BASE_URL = 'https://course-ai.datacurso.com';

    /** Default base URL for the EU-hosted DataCurso course AI service. */
    private const DEFAULT_BASE_URL_EU = 'https://eu.course-ai.datacurso.com';

    /**
     * Constructor.
     *
     * @param string|null $licensekey The license key obtained from Datacurso SHOP.
     * @param string|null $baseurl Optional standard-region base URL to override the default endpoint.
     * @param string|null $baseurleu Optional EU-region base URL to override the default endpoint.
     */
    public function __construct(?string $licensekey = null, ?string $baseurl = null, ?string $baseurleu = null) {
        if ($this->is_for_ue()) {
            $finalbaseurl = $baseurleu ?? self::DEFAULT_BASE_URL_EU;
        } else {
            $finalbaseurl = $baseurl ?? self::DEFAULT_BASE_URL;
        }

        parent::__construct($finalbaseurl, $licensekey);
    }

    /**
     * Build the streaming URL for a given session ID, adjusting base URL for localhost dev environments.
     *
     * @param string $sessionid
     * @return string streaming URL
     */
    public function get_streaming_url_for_session(string $sessionid): string {
        $baseurl = rtrim($this->baseurl, '/');
        return $baseurl . '/course/stream?session_id=' . urlencode($sessionid);
    }

    /**
     * Build the streaming URL for a module creation job (create-mod), adjusting base URL for localhost dev environments.
     *
     * @param string $jobid
     * @return string streaming URL
     */
    public function get_mod_streaming_url_for_job(string $jobid): string {
        $baseurl = rtrim($this->baseurl, '/');
        return $baseurl . '/resources/create-mod/stream?job_id=' . urlencode($jobid);
    }
}
