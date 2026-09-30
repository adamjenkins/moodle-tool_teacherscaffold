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
 * Library callbacks for Teacher scaffold.
 *
 * @package    tool_teacherscaffold
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add a link to the step-by-step activity list on the user's Preferences page.
 *
 * @param navigation_node $navigation The user settings node.
 * @param stdClass $user The user whose preferences are shown.
 * @param context_user $usercontext User context.
 * @param stdClass $course Current course.
 * @param context_course $coursecontext Course context.
 */
function tool_teacherscaffold_extend_navigation_user_settings(
    navigation_node $navigation,
    $user,
    $usercontext,
    $course,
    $coursecontext
) {
    global $PAGE, $USER;

    if (
        (int)$user->id !== (int)$USER->id || !$PAGE->has_set_url()
            || !$PAGE->url->compare(new moodle_url('/user/preferences.php'), URL_MATCH_BASE)
    ) {
        return;
    }
    if (!\tool_teacherscaffold\local\tracker::get_record((int)$USER->id)) {
        return;
    }
    $usernode = $navigation->find('useraccount', navigation_node::TYPE_CONTAINER);
    if ($usernode) {
        $usernode->add_node(navigation_node::create(
            get_string('preferences', 'tool_teacherscaffold'),
            new moodle_url('/admin/tool/teacherscaffold/preferences.php'),
            navigation_node::TYPE_SETTING,
            null,
            'tool_teacherscaffold_preferences'
        ));
    }
}

/**
 * Declare the user preferences this plugin stores.
 *
 * @return array
 */
function tool_teacherscaffold_user_preferences() {
    return [
        \tool_teacherscaffold\local\tracker::PREF_OPTEDOUT => [
            'type' => PARAM_BOOL,
            'null' => NULL_NOT_ALLOWED,
            'default' => 0,
            'permissioncallback' => [core_user::class, 'is_current_user'],
        ],
    ];
}
