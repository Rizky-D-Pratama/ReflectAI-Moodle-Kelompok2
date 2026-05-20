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
 * Get users for filtering.
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace aiprovider_datacurso\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;

/**
 * Get users available in the system.
 */
class get_users extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'search' => new external_value(PARAM_TEXT, 'Search query', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Get users.
     *
     * @param string $search Search query.
     * @return array
     */
    public static function execute(string $search = ''): array {
        global $DB;

        // Validate context.
        $context = \context_system::instance();
        self::validate_context($context);

        // Check capability.
        require_capability('aiprovider/datacurso:viewreports', $context);

        try {
            $params = self::validate_parameters(self::execute_parameters(), [
                'search' => $search,
            ]);

            // Get all active users (not deleted, not suspended).
            $where = "u.deleted = 0 AND u.suspended = 0 AND u.id > 1";
            $sqlparams = [];

            if (!empty($params['search'])) {
                $searchsql = $DB->sql_like('u.firstname', ':search1', false, false) . ' OR ' .
                             $DB->sql_like('u.lastname', ':search2', false, false) . ' OR ' .
                             $DB->sql_like($DB->sql_fullname('u.firstname', 'u.lastname'), ':search3', false, false);
                $where .= " AND ($searchsql)";
                $sqlparams['search1'] = '%' . $params['search'] . '%';
                $sqlparams['search2'] = '%' . $params['search'] . '%';
                $sqlparams['search3'] = '%' . $params['search'] . '%';
            }

            $sql = "SELECT DISTINCT u.id, u.firstname, u.lastname
                    FROM {user} u
                    WHERE $where
                    ORDER BY u.firstname ASC, u.lastname ASC";

            $users = $DB->get_records_sql($sql, $sqlparams, 0, 20); // Limit to 20 for autocomplete.

            $result = [];
            foreach ($users as $user) {
                $result[] = [
                    'id' => $user->id,
                    'fullname' => fullname($user),
                ];
            }

            return [
                'status' => 'success',
                'users' => $result,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'users' => [],
            ];
        }
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'Operation status'),
            'message' => new external_value(PARAM_TEXT, 'Message', VALUE_OPTIONAL),
            'users' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'User ID'),
                    'fullname' => new external_value(PARAM_TEXT, 'User full name'),
                ])
            ),
        ]);
    }
}
