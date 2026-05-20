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

namespace aiprovider_datacurso\local\ratelimit;

use lang_string;
use aiprovider_datacurso\local\ratelimit\ratelimit_settings;

/**
 * Class local_datacurso_ratings
 *
 * This class provides the specific rate limiting settings elements for the
 * Ratings Analysis AI service.
 *
 * @package     aiprovider_datacurso
 * @copyright   2025 Wilber Narvaez <https://datacurso.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_datacurso_ratings {
    /** @var string Plugin component name. */
    private const PLUGIN = 'aiprovider_datacurso';

    /**
     * Adds the rate limit form elements specific to ratings analysis with AI.
     *
     * @param \MoodleQuickForm $mform The Moodle form object (ai_provider_form).
     * @param string $serviceid The service identifier, e.g., 'local_datacurso_ratings'.
     */
    public function add_form_elements(\MoodleQuickForm $mform, string $serviceid): void {

        $configprefix = "ratelimit_{$serviceid}";
        $allowedusersenableid = "{$configprefix}_allowedusers_enable";

        // 1. Checkbox to enable limiting by allowed users list.
        $mform->addElement(
            'checkbox',
            $allowedusersenableid,
            new lang_string('ratelimit_local_datacurso_ratings_allowedusers_enable', self::PLUGIN),
            new lang_string('ratelimit_local_datacurso_ratings_allowedusers_enable_desc', self::PLUGIN)
        );
        $mform->setType($allowedusersenableid, PARAM_BOOL);
        $mform->setDefault($allowedusersenableid, 0);

        // Define attributes for the Autocomplete element.
        $attributes = ratelimit_settings::get_autocomplete_attributes();
        $attributes['multiple'] = true; // Ensure multiple selection is allowed.

        // 2. Course analysis generators (Autocomplete field).
        $coursechoices = ratelimit_settings::get_user_choices([
            'local/datacurso_ratings:generateanalysiscourse',
            'local/datacurso_ratings:generateanalysisactivity',
        ]);
        $courseanalystsid = "{$configprefix}_courseanalysts";

        $mform->addElement(
            'autocomplete',
            $courseanalystsid,
            new lang_string('ratelimit_local_datacurso_ratings_courseanalysts', self::PLUGIN),
            $coursechoices,
            $attributes
        );
        $mform->setType($courseanalystsid, PARAM_RAW);

        // Hide if the master checkbox is not checked.
        $mform->hideIf($courseanalystsid, $allowedusersenableid, 'notchecked');

        // 3. General analysis generators (Autocomplete field).
        $generalchoices = ratelimit_settings::get_user_choices([
            'local/datacurso_ratings:generateanalysisgeneral',
        ]);
        $generalanalystsid = "{$configprefix}_generalanalysts";

        $mform->addElement(
            'autocomplete',
            $generalanalystsid,
            new lang_string('ratelimit_local_datacurso_ratings_generalanalysts', self::PLUGIN),
            $generalchoices,
            $attributes
        );
        $mform->setType($generalanalystsid, PARAM_RAW);

        // Hide if the master checkbox is not checked.
        $mform->hideIf($generalanalystsid, $allowedusersenableid, 'notchecked');
    }

    /**
     * Get the allowed user ids for local_datacurso_ratings, by action.
     *
     * - "/rating/course" and "/rating/query" use the "courseanalysts" field.
     * - "/rating/general" uses the "generalanalysts" field.
     *
     * @param string $serviceid Service id, expected "local_datacurso_ratings".
     * @param string|null $actionpath HTTP path for the remote call.
     * @return int[]
     */
    public static function get_allowed_service_user_ids(string $serviceid, ?string $actionpath): array {
        if ($serviceid !== 'local_datacurso_ratings') {
            return [];
        }

        if (empty($actionpath)) {
            return [];
        }

        $mapping = [
            '/rating/course' => 'ratelimit_local_datacurso_ratings_courseanalysts',
            '/rating/query' => 'ratelimit_local_datacurso_ratings_courseanalysts',
            '/rating/general' => 'ratelimit_local_datacurso_ratings_generalanalysts',
        ];

        $configkey = self::resolve_config_key_for_action($actionpath, $mapping);
        if ($configkey === null) {
            return [];
        }

        $config = get_config(self::PLUGIN);
        $raw = $config->{$configkey} ?? '';

        return self::extract_user_ids($raw);
    }
}
