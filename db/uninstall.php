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

/**
 * Uninstall steps for Teacher scaffold.
 *
 * @package    tool_teacherscaffold
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Delete the stage roles (which also removes every assignment of them) and the opt-out preference.
 *
 * @return bool
 */
function xmldb_tool_teacherscaffold_uninstall() {
    global $DB;
    \tool_teacherscaffold\local\role_manager::delete_all_roles();
    // Core removes tables and config, but not user preferences.
    $DB->delete_records('user_preferences', ['name' => 'tool_teacherscaffold_optedout']);
    return true;
}
