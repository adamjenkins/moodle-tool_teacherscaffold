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
 * Event observers for Teacher scaffold.
 *
 * @package    tool_teacherscaffold
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\\core\\event\\course_module_created',
        'callback' => '\\tool_teacherscaffold\\observer::course_module_created',
        'internal' => false,
    ],
    [
        'eventname' => '\\core\\event\\role_assigned',
        'callback' => '\\tool_teacherscaffold\\observer::role_assigned',
        'internal' => false,
    ],
    [
        'eventname' => '\\core\\event\\cohort_member_added',
        'callback' => '\\tool_teacherscaffold\\observer::cohort_member_added',
        'internal' => false,
    ],
    [
        'eventname' => '\\core\\event\\role_deleted',
        'callback' => '\\tool_teacherscaffold\\observer::role_deleted',
        'internal' => false,
    ],
    [
        'eventname' => '\\core\\event\\user_deleted',
        'callback' => '\\tool_teacherscaffold\\observer::user_deleted',
        'internal' => false,
    ],
];
