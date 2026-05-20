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
 * Class report_lifestory
 *
 * This class provides the specific rate limiting settings elements for the
 * Life Story report AI feedback generation service.
 *
 * @package     aiprovider_datacurso
 * @copyright   2025 Wilber Narvaez <https://datacurso.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_lifestory {
    /** @var string Plugin component name. */
    private const PLUGIN = 'aiprovider_datacurso';

    /**
     * Adds the rate limit form elements specific to Life Story report AI feedback generation.
     *
     * This replaces the old add_settings() method.
     *
     * @param \MoodleQuickForm $mform The Moodle form object (ai_provider_form).
     * @param string $serviceid The service identifier, e.g., 'report_lifestory'.
     */
    public function add_form_elements(\MoodleQuickForm $mform, string $serviceid): void {

        $configprefix = "ratelimit_{$serviceid}";
        $allowedusersenableid = "{$configprefix}_allowedusers_enable";

        // 1. Checkbox to enable limiting by allowed users list.
        $mform->addElement(
            'checkbox',
            $allowedusersenableid,
            new lang_string('ratelimit_report_lifestory_allowedusers_enable', self::PLUGIN),
            new lang_string('ratelimit_report_lifestory_allowedusers_enable_desc', self::PLUGIN)
        );
        $mform->setType($allowedusersenableid, PARAM_BOOL);
        $mform->setDefault($allowedusersenableid, 0);

        // 2. Autocomplete for allowed users.
        $attributes = ratelimit_settings::get_autocomplete_attributes();
        $attributes['multiple'] = true;

        $choices = ratelimit_settings::get_user_choices([
            'report/lifestory:generateaifeedback',
        ]);

        $allowedusersid = "{$configprefix}_allowedusers";

        $mform->addElement(
            'autocomplete',
            $allowedusersid,
            new lang_string('ratelimit_report_lifestory_allowedusers', self::PLUGIN),
            $choices,
            $attributes
        );
        $mform->setType($allowedusersid, PARAM_RAW);
        // Hide if the master checkbox is not checked.
        $mform->hideIf($allowedusersid, $allowedusersenableid, 'notchecked');
    }

    /**
     * Get the allowed user ids for report_lifestory.
     *
     * Only the "/story/analysis" action has a specific restriction.
     *
     * @param string $serviceid Service id, expected "report_lifestory".
     * @param string|null $actionpath HTTP path for the remote call.
     * @return int[]
     */
    public static function get_allowed_service_user_ids(string $serviceid, ?string $actionpath): array {
        if ($serviceid !== 'report_lifestory') {
            return [];
        }

        if (empty($actionpath)) {
            return [];
        }

        $mapping = [
            '/story/analysis' => 'ratelimit_report_lifestory_allowedusers',
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
