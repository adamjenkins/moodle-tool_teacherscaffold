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

namespace tool_teacherscaffold;

use tool_teacherscaffold\local\tracker;

/**
 * Event observers for Teacher scaffold.
 *
 * All are registered with 'internal' => false, so they run after the triggering transaction
 * commits: a rolled-back module creation never counts as progress.
 *
 * @package    tool_teacherscaffold
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * A module was added (form, drag and drop, duplicate). Restores do not fire this event.
     *
     * @param \core\event\course_module_created $event The event.
     */
    public static function course_module_created(\core\event\course_module_created $event): void {
        tracker::record_module_created((int)$event->userid, (string)$event->other['modulename']);
    }

    /**
     * Track someone given an editing teacher role for the first time, if that is switched on.
     *
     * @param \core\event\role_assigned $event The event.
     */
    public static function role_assigned(\core\event\role_assigned $event): void {
        global $DB;
        if (!get_config('tool_teacherscaffold', 'autotrack')) {
            return;
        }
        if (($event->other['component'] ?? '') === tracker::COMPONENT) {
            return;
        }
        $archetype = $DB->get_field('role', 'archetype', ['id' => $event->objectid]);
        if ($archetype !== 'editingteacher') {
            return;
        }
        $userid = (int)$event->relateduserid;
        if (tracker::get_record($userid)) {
            return;
        }
        // Only a genuinely new teacher: no other editing-teacher-type role anywhere.
        $others = $DB->count_records_sql(
            "SELECT COUNT(1)
               FROM {role_assignments} ra
               JOIN {role} r ON r.id = ra.roleid
              WHERE ra.userid = :userid AND r.archetype = :archetype AND ra.id <> :raid",
            ['userid' => $userid, 'archetype' => 'editingteacher', 'raid' => (int)($event->other['id'] ?? 0)]);
        if ($others) {
            return;
        }
        tracker::track($userid);
    }

    /**
     * Track a new member of a configured cohort.
     *
     * @param \core\event\cohort_member_added $event The event.
     */
    public static function cohort_member_added(\core\event\cohort_member_added $event): void {
        if (in_array((int)$event->objectid, tracker::cohort_ids())) {
            tracker::track((int)$event->relateduserid);
        }
    }

    /**
     * Delete the data of a deleted user.
     *
     * @param \core\event\user_deleted $event The event.
     */
    public static function user_deleted(\core\event\user_deleted $event): void {
        tracker::forget((int)$event->objectid);
    }
}
