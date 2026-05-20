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
 * Class local_assign_ai
 *
 * @package     aiprovider_datacurso
 * @copyright   2025 Wilber Narvaez <https://datacurso.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_assign_ai {
    /** @var string Plugin component name. */
    private const PLUGIN = 'aiprovider_datacurso';

    /**
     * Add elements specifics the form settings.
     *
     * @param \MoodleQuickForm $mform Object form Moodle.
     * @param string $serviceid ServiceID ('local_assign_ai').
     */
    public function add_form_elements(\MoodleQuickForm $mform, string $serviceid): void {

        $configprefix = "ratelimit_{$serviceid}";
        $allowedusersenableid = "{$configprefix}_allowedusers_enable";

        $mform->addElement(
            'checkbox',
            $allowedusersenableid,
            new lang_string('ratelimit_local_assign_ai_allowedusers_enable', self::PLUGIN),
            new lang_string('ratelimit_local_assign_ai_allowedusers_enable_desc', self::PLUGIN)
        );
        $mform->setType($allowedusersenableid, PARAM_BOOL);
        $mform->setDefault($allowedusersenableid, 0);

        $choices = ratelimit_settings::get_user_choices([
            'local/assign_ai:review',
            'local/assign_ai:changestatus',
            'local/assign_ai:viewdetails',
            'mod/assign:submit',
        ]);
        $attributes = ratelimit_settings::get_autocomplete_attributes();
        $allowedusersid = "{$configprefix}_allowedusers";
        $mform->addElement(
            'autocomplete',
            $allowedusersid,
            new lang_string('ratelimit_local_assign_ai_allowedusers', self::PLUGIN),
            $choices,
            $attributes,
        );
        $mform->addHelpButton(
            $allowedusersid,
            'ratelimit_local_assign_ai_allowedusers_desc',
            self::PLUGIN
        );
        $mform->setType($allowedusersid, PARAM_RAW);

        $mform->hideIf($allowedusersid, $allowedusersenableid, 'notchecked');
    }

    /**
     * Get the allowed user ids for local_assign_ai.
     *
     * Currently all actions for this service use the same
     * "allowedusers" field.
     *
     * @param string $serviceid Service id, expected "local_assign_ai".
     * @param string|null $actionpath HTTP path for the remote call.
     * @return int[]
     */
    public static function get_allowed_service_user_ids(string $serviceid, ?string $actionpath): array {
        if ($serviceid !== 'local_assign_ai') {
            return [];
        }

        if (empty($actionpath)) {
            return [];
        }

        $mapping = [
            '/assign/' => 'ratelimit_local_assign_ai_allowedusers',
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
