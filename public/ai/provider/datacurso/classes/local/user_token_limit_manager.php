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

namespace aiprovider_datacurso\local;

/**
 * Manager for CRUD operations over aiprovider_datacurso_userlimit.
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Wilber Narvaez <https://datacurso.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_token_limit_manager {
    /**
     * Count records matching optional search.
     *
     * @param string $search
     * @return int
     */
    public static function count(string $search = ''): int {
        global $DB;
        [$where, $params] = self::build_search_where($search);
        $sql = "SELECT COUNT(1)
                  FROM {aiprovider_datacurso_userlimit} utl
                  JOIN {user} u ON u.id = utl.userid
                 $where";
        return (int)$DB->get_field_sql($sql, $params);
    }

    /**
     * Reset usage counters for a user limit record.
     *
     * @param int $id Record ID
     * @return bool
     */
    public static function reset_usage(int $id): bool {
        global $DB, $USER;
        if (!$DB->record_exists('aiprovider_datacurso_userlimit', ['id' => $id])) {
            return false;
        }
        $now = time();
        $record = (object) [
            'id' => $id,
            'tokensused' => 0,
            'countfrom' => $now,
            'usermodified' => $USER->id,
            'timemodified' => $now,
        ];
        $DB->update_record('aiprovider_datacurso_userlimit', $record);
        return true;
    }

    /**
     * Get paginated records with user data.
     *
     * @param string $search
     * @param string $sort allowed: fullname|email|tokenlimit|tokensused
     * @param string $dir ASC|DESC
     * @param int $offset
     * @param int $limit
     * @return array of records (stdClass)
     */
    public static function get_records(string $search, string $sort, string $dir, int $offset, int $limit): array {
        global $DB;
        [$where, $params] = self::build_search_where($search);

        $orderby = self::map_sort($sort, $dir);
        $sql = "SELECT utl.id, utl.userid, utl.tokenlimit, utl.tokensused,
                       u.firstname, u.lastname, u.email
                  FROM {aiprovider_datacurso_userlimit} utl
                  JOIN {user} u ON u.id = utl.userid
                 $where
              ORDER BY $orderby";
        return $DB->get_records_sql($sql, $params, $offset, $limit);
    }

    /**
     * Get a single record by id.
     *
     * @param int $id
     * @return \stdClass|null
     */
    public static function get_by_id(int $id): ?\stdClass {
        global $DB;
        return $DB->get_record('aiprovider_datacurso_userlimit', ['id' => $id]) ?: null;
    }

    /**
     * Delete a record by id.
     *
     * @param int $id
     * @return bool
     */
    public static function delete(int $id): bool {
        global $DB;
        return $DB->delete_records('aiprovider_datacurso_userlimit', ['id' => $id]);
    }

    /**
     * Create or update a user quota record.
     *
     * @param int $userid
     * @param int $tokenlimit
     * @param int|null $id existing id to update
     * @return int record id
     */
    public static function save(int $userid, int $tokenlimit, ?int $id = null): int {
        global $DB, $USER;
        $now = time();

        if ($id) {
            $record = $DB->get_record('aiprovider_datacurso_userlimit', ['id' => $id], '*', MUST_EXIST);
            $record->tokenlimit = $tokenlimit;
            $record->usermodified = $USER->id;
            $record->timemodified = $now;
            $DB->update_record('aiprovider_datacurso_userlimit', $record);
            return $record->id;
        }

        // Check if a record already exists for this user.
        if ($DB->record_exists('aiprovider_datacurso_userlimit', ['userid' => $userid])) {
            throw new \moodle_exception('error_usertokenlimit_exists', 'aiprovider_datacurso');
        }

        $record = (object) [
            'userid' => $userid,
            'tokenlimit' => $tokenlimit,
            'tokensused' => 0,
            'countfrom' => $now,
            'lastsync' => 0,
            'usermodified' => $USER->id,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        return (int)$DB->insert_record('aiprovider_datacurso_userlimit', $record);
    }

    /**
     * Build search SQL for name or email.
     *
     * @param string $search
     * @return array [where, params]
     */
    private static function build_search_where(string $search): array {
        global $DB;
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = 'WHERE (' .
                $DB->sql_like('u.firstname', ':s1', false) . ' OR ' .
                $DB->sql_like('u.lastname', ':s2', false) . ' OR ' .
                $DB->sql_like('u.email', ':s3', false) .
            ')';
            $like = "%$search%";
            $params['s1'] = $like;
            $params['s2'] = $like;
            $params['s3'] = $like;
        }
        return [$where, $params];
    }

    /**
     * Map UI sort to SQL order by.
     *
     * @param string $sort
     * @param string $dir
     * @return string
     */
    private static function map_sort(string $sort, string $dir): string {
        $dir = (strtoupper($dir) === 'DESC') ? 'DESC' : 'ASC';
        return match ($sort) {
            'fullname' => "u.lastname $dir, u.firstname $dir",
            'email' => "u.email $dir",
            'tokenlimit' => "utl.tokenlimit $dir",
            'tokensused' => "utl.tokensused $dir",
            default => "u.email $dir",
        };
    }
}
