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
 * Data generator for Teacher scaffold.
 *
 * @package    tool_teacherscaffold
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use tool_teacherscaffold\local\tracker;

/**
 * Data generator for Teacher scaffold.
 *
 * @package    tool_teacherscaffold
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tool_teacherscaffold_generator extends component_generator_base {
    /**
     * Start guiding a teacher through the plugin's own API, optionally at a later stage.
     *
     * @param array $record With 'userid', and optionally 'tier' (advanced through tracker::advance())
     *     and 'used' (comma-separated module names already tried).
     * @return stdClass The tracking record.
     */
    public function create_tracked_user(array $record): stdClass {
        global $DB;
        $userid = (int)$record['userid'];
        foreach (array_filter(array_map('trim', explode(',', (string)($record['used'] ?? '')))) as $modname) {
            $DB->insert_record(
                'tool_teacherscaffold_usage',
                (object)['userid' => $userid, 'modname' => $modname, 'timefirstused' => time()]
            );
        }
        if (!tracker::track($userid)) {
            throw new coding_exception('User ' . $userid . ' could not be tracked.');
        }
        $tier = (int)($record['tier'] ?? 1);
        for ($i = 1; $i < $tier; $i++) {
            tracker::advance($userid);
        }
        return tracker::get_record($userid);
    }
}
