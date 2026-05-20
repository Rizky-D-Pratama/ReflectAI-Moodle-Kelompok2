<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace aiprovider_datacurso\hook;

use core\hook\navigation\primary_extend as primary_extend_hook;
use moodle_url;
use navigation_node;
use context_system;

/**
 * Hook handlers for aiprovider_datacurso navigation.
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class navigation {
    /**
     * Extend the primary navigation to include a link for Datacurso AI Provider.
     *
     * @param primary_extend_hook $hook
     * @return void
     */
    public static function primary_extend(primary_extend_hook $hook): void {
        $sysctx = context_system::instance();

        // Optional: Only admins should see this navigation entry.
        if (!has_capability('moodle/site:config', $sysctx)) {
            return;
        }

        $primary = $hook->get_primaryview();
        $url = new moodle_url('/ai/provider/datacurso/admin/report_sections.php');
        $key = 'aiprovider_datacurso';

        // Avoid duplicate insertion if called more than once.
        if ($primary->find($key, null)) {
            return;
        }

        $primary->add(
            get_string('pluginname', 'aiprovider_datacurso'),
            $url,
            navigation_node::TYPE_ROOTNODE,
            null,
            $key
        );
    }
}
