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

/**
 * Utility class providing common functionality for rate limit form elements.
 *
 * The abstract method add_settings is removed as per-service settings are now handled
 * via the hook listener calling the add_form_elements method directly on service classes.
 *
 * @package     aiprovider_datacurso
 * @category    admin
 * @copyright   2025 Wilber Narvaez <https://datacurso.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace aiprovider_datacurso\local\ratelimit;

/**
 * Utility class to manage common rate limit configurations.
 */
class ratelimit_settings {
    /**
     * Return the list of allowed user ids for a given service/action pair.
     *
     * Implementing classes may provide a service-specific version of this
     * method. The base implementation returns an empty array meaning that
     * there is no per-user restriction for the given service/action pair.
     *
     * @param string $serviceid Frankenstyle service/component id (e.g. 'local_coursegen').
     * @param string|null $actionpath Optional HTTP path used to route to the correct list.
     * @return int[] List of allowed user ids for this service/action only.
     */
    public static function get_allowed_service_user_ids(string $serviceid, ?string $actionpath): array {
        return [];
    }

    /**
     * Resolve a configuration key for a given action path using a
     * prefix-to-config mapping.
     *
     * @param string|null $actionpath HTTP path for the remote call.
     * @param array $mapping Array in the form prefix => configname.
     * @return string|null Config key name or null when no prefix matches.
     */
    protected static function resolve_config_key_for_action(?string $actionpath, array $mapping): ?string {
        if (empty($actionpath)) {
            return null;
        }

        foreach ($mapping as $prefix => $configname) {
            if (str_starts_with($actionpath, $prefix)) {
                return $configname;
            }
        }

        return null;
    }

    /**
     * Extract user ids from a comma-separated configuration value.
     *
     * @param string $value Raw configuration value.
     * @return int[]
     */
    protected static function extract_user_ids(string $value): array {
        if ($value === '') {
            return [];
        }

        $parts = explode(',', $value);
        $ids = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $ids[] = (int)$part;
        }

        return $ids;
    }

    /**
     * Resolve and delegate to the concrete ratelimit settings class for a service.
     *
     * This uses the convention that the service id matches the class name in this
     * namespace (for example serviceid "local_coursegen" -> class
     * aiprovider_datacurso\local\ratelimit\local_coursegen).
     *
     * @param string $serviceid Frankenstyle service/component id.
     * @param string|null $actionpath Optional HTTP path used to route to the correct list.
     * @return int[] List of allowed user ids for this service/action only.
     */
    public static function get_allowed_users_for_service(string $serviceid, ?string $actionpath): array {
        $classname = "aiprovider_datacurso\\local\\ratelimit\\" . $serviceid;

        if (!class_exists($classname)) {
            return [];
        }

        // Only call the method when the target class exposes the expected
        // static API. This keeps service classes decoupled from inheritance
        // while still allowing them to provide their own allowlist logic.
        if (!method_exists($classname, 'get_allowed_service_user_ids')) {
            return [];
        }

        return $classname::get_allowed_service_user_ids($serviceid, $actionpath);
    }


    /**
     * Retrieve the list of selectable users for the autocomplete control.
     *
     * @param array $capabilities Capability names users must have to be selectable.
     * @return array<string,string>
     */
    public static function get_user_choices(array $capabilities): array {
        global $DB, $CFG;

        [$insql, $params] = $DB->get_in_or_equal($capabilities, SQL_PARAMS_NAMED);

        $params['deleted'] = 0;
        $params['suspended'] = 0;
        $params['permission'] = CAP_ALLOW;
        $params['capabilitiescount'] = count($capabilities);

        $records = $DB->get_records_sql(
            "SELECT
                u.id,
                u.firstname,
                u.lastname,
                u.firstnamephonetic,
                u.lastnamephonetic,
                u.middlename,
                u.alternatename
            FROM
                {user} u
                JOIN {role_assignments} ra ON ra.userid = u.id
                JOIN {role_capabilities} rc ON rc.roleid = ra.roleid
            WHERE
                rc.permission = :permission
                AND u.deleted = :deleted
                AND u.suspended = :suspended
                AND rc.capability {$insql}
            GROUP BY
                u.id, u.firstname, u.lastname, u.alternatename, u.middlename, u.firstnamephonetic, u.lastnamephonetic
            HAVING
                COUNT(DISTINCT rc.capability) = :capabilitiescount
            ORDER BY u.lastname, u.firstname, u.id",
            $params
        );

        $choices = [];
        foreach ($records as $user) {
            $choices[(string)$user->id] = fullname($user);
        }

        if (empty($choices)) {
            return ['' => get_string('noselection', 'form')];
        }

        return $choices;
    }

    /**
     * Get the autocomplete attributes for the user selection.
     *
     * @return array
     */
    public static function get_autocomplete_attributes(): array {
        return [
            'multiple' => true,
            'showsuggestions' => true,
            'placeholder' => get_string('search'),
            'noselectionstring' => get_string('noselection', 'form'),
        ];
    }
}
