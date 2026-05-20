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
 * Hide fields
 *
 * @module     aiprovider_datacurso/hiden_fields
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Hide section connection settings and fields ratelimit.
 */
const hideConnectionSettings = () => {
    const connectionSettings = document.getElementById('id_connection_header');
    if (connectionSettings) {
        connectionSettings.style.display = 'none';
    }
};

/**
 * Init function.
 */
export const init = () => {
    hideConnectionSettings();
};
