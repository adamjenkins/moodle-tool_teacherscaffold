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

namespace tool_teacherscaffold\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use tool_teacherscaffold\local\tracker;

/**
 * Privacy provider for Teacher scaffold. All data lives at system context.
 *
 * @package    tool_teacherscaffold
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\core_userlist_provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\user_preference_provider {

    /**
     * Describe the stored data.
     *
     * @param collection $collection The collection to add to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('tool_teacherscaffold_user', [
            'userid' => 'privacy:metadata:tool_teacherscaffold_user:userid',
            'tier' => 'privacy:metadata:tool_teacherscaffold_user:tier',
            'status' => 'privacy:metadata:tool_teacherscaffold_user:status',
            'tieradds' => 'privacy:metadata:tool_teacherscaffold_user:tieradds',
            'timecreated' => 'privacy:metadata:tool_teacherscaffold_user:timecreated',
            'timemodified' => 'privacy:metadata:tool_teacherscaffold_user:timemodified',
        ], 'privacy:metadata:tool_teacherscaffold_user');
        $collection->add_database_table('tool_teacherscaffold_usage', [
            'userid' => 'privacy:metadata:tool_teacherscaffold_usage:userid',
            'modname' => 'privacy:metadata:tool_teacherscaffold_usage:modname',
            'timefirstused' => 'privacy:metadata:tool_teacherscaffold_usage:timefirstused',
        ], 'privacy:metadata:tool_teacherscaffold_usage');
        $collection->add_user_preference(tracker::PREF_OPTEDOUT, 'privacy:metadata:preference:optedout');
        $collection->add_subsystem_link('core_role', [], 'privacy:metadata:core_role');
        return $collection;
    }

    /**
     * Whether the plugin holds any data about the user.
     *
     * @param int $userid User id.
     * @return bool
     */
    protected static function has_data(int $userid): bool {
        global $DB;
        return $DB->record_exists('tool_teacherscaffold_user', ['userid' => $userid])
            || $DB->record_exists('tool_teacherscaffold_usage', ['userid' => $userid]);
    }

    /**
     * Contexts holding the user's data: the system context, if any.
     *
     * @param int $userid User id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        if (self::has_data($userid)) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Users with data in a context.
     *
     * @param userlist $userlist The userlist to fill.
     */
    public static function get_users_in_context(userlist $userlist) {
        if ($userlist->get_context()->contextlevel != CONTEXT_SYSTEM) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT userid FROM {tool_teacherscaffold_user}', []);
        $userlist->add_from_sql('userid', 'SELECT userid FROM {tool_teacherscaffold_usage}', []);
    }

    /**
     * Export the user's stage, status and first uses.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_SYSTEM) {
                continue;
            }
            $data = (object)[];
            if ($record = $DB->get_record('tool_teacherscaffold_user', ['userid' => $userid])) {
                $data->tier = (int)$record->tier;
                $data->status = $record->status;
                $data->tieradds = (int)$record->tieradds;
                $data->timecreated = transform::datetime($record->timecreated);
                $data->timemodified = transform::datetime($record->timemodified);
            }
            $data->usage = [];
            foreach ($DB->get_records('tool_teacherscaffold_usage', ['userid' => $userid], 'timefirstused, id') as $use) {
                $data->usage[] = (object)[
                    'modname' => $use->modname,
                    'timefirstused' => transform::datetime($use->timefirstused),
                ];
            }
            writer::with_context($context)->export_data([get_string('pluginname', 'tool_teacherscaffold')], $data);
        }
    }

    /**
     * Export the opt-out preference.
     *
     * @param int $userid User id.
     */
    public static function export_user_preferences(int $userid) {
        $value = get_user_preferences(tracker::PREF_OPTEDOUT, null, $userid);
        if ($value !== null) {
            writer::export_user_preference('tool_teacherscaffold', tracker::PREF_OPTEDOUT,
                transform::yesno($value), get_string('privacy:metadata:preference:optedout', 'tool_teacherscaffold'));
        }
    }

    /**
     * Delete everyone's data in a context.
     *
     * @param \context $context The context.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }
        $userids = array_unique(array_merge(
            $DB->get_fieldset_select('tool_teacherscaffold_user', 'userid', '1 = 1'),
            $DB->get_fieldset_select('tool_teacherscaffold_usage', 'userid', '1 = 1')
        ));
        foreach ($userids as $userid) {
            tracker::forget((int)$userid);
        }
    }

    /**
     * Delete one user's data in the approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel == CONTEXT_SYSTEM) {
                tracker::forget((int)$contextlist->get_user()->id);
            }
        }
    }

    /**
     * Delete several users' data in a context.
     *
     * @param approved_userlist $userlist Approved users.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        if ($userlist->get_context()->contextlevel != CONTEXT_SYSTEM) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            tracker::forget((int)$userid);
        }
    }
}
