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
 * Class local_coursegen
 *
 * This class provides the specific rate limiting settings elements for the Course Generation AI service.
 *
 * @package     aiprovider_datacurso
 * @copyright   2025 Wilber Narvaez <https://datacurso.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_coursegen {
    /** @var string Plugin component name. */
    private const PLUGIN = 'aiprovider_datacurso';

    /**
     * Adds the rate limit form elements specific to course generation.
     *
     * This replaces the old add_settings() method.
     *
     * @param \MoodleQuickForm $mform The Moodle form object (ai_provider_form).
     * @param string $serviceid The service identifier, e.g., 'local_coursegen'.
     */
    public function add_form_elements(\MoodleQuickForm $mform, string $serviceid): void {
        $configprefix = "ratelimit_{$serviceid}";
        $allowedusersenableid = "{$configprefix}_allowedusers_enable";

        // 1. Checkbox to enable limiting by allowed users list.
        $mform->addElement(
            'checkbox',
            $allowedusersenableid,
            new lang_string('ratelimit_local_coursegen_allowedusers_enable', self::PLUGIN),
            new lang_string('ratelimit_local_coursegen_allowedusers_enable_desc', self::PLUGIN)
        );
        $mform->setType($allowedusersenableid, PARAM_BOOL);
        $mform->setDefault($allowedusersenableid, 0);

        // Define the choices (users/capabilities) once, calling the static utility.
        $choices = ratelimit_settings::get_user_choices([
            'moodle/course:create',
            'local/coursegen:createcoursewithai',
        ]);

        // Get attributes needed for the Moodle Form Autocomplete element, calling the static utility.
        $attributes = ratelimit_settings::get_autocomplete_attributes();
        $attributes['multiple'] = true;

        // 2. Autocomplete for allowed Course Creators.
        $coursecreatorsid = "{$configprefix}_coursecreators";

        $mform->addElement(
            'autocomplete',
            $coursecreatorsid,
            new lang_string('ratelimit_local_coursegen_coursecreators', self::PLUGIN),
            $choices,
            $attributes
        );
        $mform->setType($coursecreatorsid, PARAM_RAW);
        // Hide if the master checkbox is not checked (eq, 0).
        $mform->hideIf($coursecreatorsid, $allowedusersenableid, 'notchecked');

        // 3. Autocomplete for allowed Activity Creators.
        $choices = ratelimit_settings::get_user_choices([
            'moodle/course:manageactivities',
            'local/coursegen:createactivitywithai',
        ]);

        $activitycreatorsid = "{$configprefix}_activitycreators";
        $mform->addElement(
            'autocomplete',
            $activitycreatorsid,
            new lang_string('ratelimit_local_coursegen_activitycreators', self::PLUGIN),
            $choices,
            $attributes
        );
        $mform->setType($activitycreatorsid, PARAM_RAW);
        // Hide if the master checkbox is not checked (eq, 0).
        $mform->hideIf($activitycreatorsid, $allowedusersenableid, 'notchecked');
    }

    /**
     * Get the allowed user ids for local_coursegen, separated by action path.
     *
     * - Course creation actions ("/course/") use the "coursecreators" field.
     * - Activity creation actions ("/resources/create-mod") use
     *   the "activitycreators" field.
     * - Other actions for this service have no specific restriction.
     *
     * @param string $serviceid Service id, expected "local_coursegen".
     * @param string|null $actionpath HTTP path for the remote call.
     * @return int[]
     */
    public static function get_allowed_service_user_ids(string $serviceid, ?string $actionpath): array {
        if ($serviceid !== 'local_coursegen') {
            return [];
        }

        if (empty($actionpath)) {
            return [];
        }

        $mapping = [
            '/course/v2/start' => 'ratelimit_local_coursegen_coursecreators',
            '/course/start' => 'ratelimit_local_coursegen_coursecreators',
            '/resources/create-mod' => 'ratelimit_local_coursegen_activitycreators',
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
