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

namespace tool_teacherscaffold\local;

use context_system;
use stdClass;

/**
 * Tracked teachers: their stage, status and stage-role assignment.
 *
 * This is the only code that assigns or removes the plugin's roles. Every assignment is made at
 * system context with component 'tool_teacherscaffold', so the core "Assign roles" page cannot
 * remove it and role_unassign() must be given the same component.
 *
 * @package    tool_teacherscaffold
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tracker {

    /** @var string Being guided through the stages. */
    const STATUS_ACTIVE = 'active';

    /** @var string Chose to see every activity. */
    const STATUS_OPTEDOUT = 'optedout';

    /** @var string Reached the final stage. */
    const STATUS_GRADUATED = 'graduated';

    /** @var string Component recorded on the role assignments. */
    const COMPONENT = 'tool_teacherscaffold';

    /** @var string User preference set when the teacher opts out. */
    const PREF_OPTEDOUT = 'tool_teacherscaffold_optedout';

    /**
     * Whether the master switch is on (on until an admin turns it off).
     *
     * @return bool
     */
    public static function enabled(): bool {
        $value = get_config('tool_teacherscaffold', 'enabled');
        return $value === false || (bool)$value;
    }

    /**
     * The tracking record of a user.
     *
     * @param int $userid User id.
     * @return stdClass|null
     */
    public static function get_record(int $userid): ?stdClass {
        global $DB;
        return $DB->get_record('tool_teacherscaffold_user', ['userid' => $userid]) ?: null;
    }

    /**
     * Whether a user can be tracked at all. Site admins bypass capability checks, so the
     * stage roles would do nothing for them.
     *
     * @param int $userid User id.
     * @return bool
     */
    public static function can_track(int $userid): bool {
        global $DB;
        if (is_siteadmin($userid) || isguestuser($userid)) {
            return false;
        }
        return $DB->record_exists('user', ['id' => $userid, 'deleted' => 0]);
    }

    /**
     * Start guiding a teacher at stage 1.
     *
     * Someone who was tracked before (including anyone who opted out or unlocked everything)
     * is never started again; an admin reset is the way back.
     *
     * @param int $userid User id.
     * @return bool True if the user is newly tracked.
     */
    public static function track(int $userid): bool {
        global $DB;
        if (!self::can_track($userid) || $DB->record_exists('tool_teacherscaffold_user', ['userid' => $userid])) {
            return false;
        }
        $now = time();
        $record = (object)[
            'userid' => $userid,
            'tier' => 1,
            'status' => self::STATUS_ACTIVE,
            'tieradds' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        try {
            $record->id = $DB->insert_record('tool_teacherscaffold_user', $record);
        } catch (\dml_write_exception $e) {
            // Another request tracked the same user at the same moment.
            return false;
        }
        self::apply_role($record);
        return true;
    }

    /**
     * Save changed fields of a record.
     *
     * @param stdClass $record Record with id.
     * @param array $fields Field => value.
     * @return stdClass The updated record.
     */
    protected static function update(stdClass $record, array $fields): stdClass {
        global $DB;
        foreach ($fields as $field => $value) {
            $record->$field = $value;
        }
        $record->timemodified = time();
        $DB->update_record('tool_teacherscaffold_user', $record);
        return $record;
    }

    /**
     * Give the user exactly the role their record calls for, and remove any other stage role.
     *
     * @param stdClass $record Row of tool_teacherscaffold_user.
     */
    public static function apply_role(stdClass $record): void {
        $wanted = null;
        if (self::enabled() && $record->status === self::STATUS_ACTIVE) {
            $wanted = role_manager::role_id((int)$record->tier);
        }
        self::set_role((int)$record->userid, $wanted);
    }

    /**
     * Make the user hold only $roleid among this plugin's assignments.
     *
     * @param int $userid User id.
     * @param int|null $roleid Role to hold, or null for none.
     */
    protected static function set_role(int $userid, ?int $roleid): void {
        global $DB;
        $syscontext = context_system::instance();
        $has = false;
        $assignments = $DB->get_records('role_assignments',
            ['userid' => $userid, 'component' => self::COMPONENT, 'contextid' => $syscontext->id]);
        foreach ($assignments as $ra) {
            if (!$has && $roleid && (int)$ra->roleid === $roleid) {
                $has = true;
                continue;
            }
            role_unassign($ra->roleid, $userid, $syscontext->id, self::COMPONENT);
        }
        if ($roleid && !$has) {
            role_assign($roleid, $userid, $syscontext->id, self::COMPONENT);
        }
    }

    /**
     * Opt out: show every activity from now on.
     *
     * @param int $userid User id.
     * @return bool False if the user was not being guided.
     */
    public static function opt_out(int $userid): bool {
        $record = self::get_record($userid);
        if (!$record || $record->status !== self::STATUS_ACTIVE) {
            return false;
        }
        set_user_preference(self::PREF_OPTEDOUT, 1, $userid);
        self::apply_role(self::update($record, ['status' => self::STATUS_OPTEDOUT]));
        return true;
    }

    /**
     * Opt back in at the stage the user had reached.
     *
     * @param int $userid User id.
     * @return bool False if the user had not opted out.
     */
    public static function opt_in(int $userid): bool {
        $record = self::get_record($userid);
        if (!$record || $record->status !== self::STATUS_OPTEDOUT) {
            return false;
        }
        unset_user_preference(self::PREF_OPTEDOUT, $userid);
        self::apply_role(self::update($record, ['status' => self::STATUS_ACTIVE]));
        return true;
    }

    /**
     * Admin action: back to stage 1, with the record of activities tried cleared.
     *
     * @param int $userid User id.
     * @return bool False if the user is not tracked.
     */
    public static function reset(int $userid): bool {
        global $DB;
        $record = self::get_record($userid);
        if (!$record) {
            return false;
        }
        $DB->delete_records('tool_teacherscaffold_usage', ['userid' => $userid]);
        unset_user_preference(self::PREF_OPTEDOUT, $userid);
        self::apply_role(self::update($record, ['tier' => 1, 'tieradds' => 0, 'status' => self::STATUS_ACTIVE]));
        return true;
    }

    /**
     * Admin action: unlock everything now, without the unlock event (nothing was earned).
     *
     * @param int $userid User id.
     * @return bool False if the user is not tracked.
     */
    public static function graduate(int $userid): bool {
        $record = self::get_record($userid);
        if (!$record) {
            return false;
        }
        $config = new tier_config();
        self::apply_role(self::update($record,
            ['tier' => $config->final_tier(), 'tieradds' => 0, 'status' => self::STATUS_GRADUATED]));
        return true;
    }

    /**
     * Stop tracking a user and delete their data.
     *
     * @param int $userid User id.
     */
    public static function forget(int $userid): void {
        global $DB;
        self::set_role($userid, null);
        $DB->delete_records('tool_teacherscaffold_usage', ['userid' => $userid]);
        $DB->delete_records('tool_teacherscaffold_user', ['userid' => $userid]);
        unset_user_preference(self::PREF_OPTEDOUT, $userid);
    }

    /**
     * After stages were removed, move anyone on a removed stage to the last listed stage.
     *
     * @param int $maxtier Number of listed stages.
     */
    public static function clamp_to(int $maxtier): void {
        global $DB;
        $DB->execute("UPDATE {tool_teacherscaffold_user}
                         SET tier = :maxtier, tieradds = 0, timemodified = :now
                       WHERE tier > :maxtier2 AND status <> :graduated",
            ['maxtier' => max(1, $maxtier), 'maxtier2' => max(1, $maxtier), 'now' => time(),
             'graduated' => self::STATUS_GRADUATED]);
    }

    /**
     * Repair everyone's role assignment and opt-out state. Also a settings callback.
     *
     * The opt-out preference is the teacher's own choice, so it wins over a stale status.
     *
     * @param string $name Full name of the changed setting, when used as a callback (unused).
     */
    public static function reconcile_all(string $name = ''): void {
        global $DB;
        $records = $DB->get_recordset('tool_teacherscaffold_user');
        foreach ($records as $record) {
            $optedout = (bool)get_user_preferences(self::PREF_OPTEDOUT, false, $record->userid);
            if ($optedout && $record->status === self::STATUS_ACTIVE) {
                $record = self::update($record, ['status' => self::STATUS_OPTEDOUT]);
            } else if (!$optedout && $record->status === self::STATUS_OPTEDOUT) {
                $record = self::update($record, ['status' => self::STATUS_ACTIVE]);
            }
            self::apply_role($record);
        }
        $records->close();

        // Assignments of users who are no longer tracked.
        $syscontext = context_system::instance();
        $orphans = $DB->get_records_sql(
            "SELECT ra.id, ra.roleid, ra.userid
               FROM {role_assignments} ra
          LEFT JOIN {tool_teacherscaffold_user} t ON t.userid = ra.userid
              WHERE ra.component = :component AND t.id IS NULL",
            ['component' => self::COMPONENT]);
        foreach ($orphans as $ra) {
            role_unassign($ra->roleid, $ra->userid, $syscontext->id, self::COMPONENT);
        }
    }

    /**
     * Track every member of the configured cohorts. Also a settings callback.
     *
     * @param string $name Full name of the changed setting, when used as a callback (unused).
     * @return int Number of users newly tracked.
     */
    public static function sync_cohorts(string $name = ''): int {
        global $DB;
        $cohortids = self::cohort_ids();
        if (!$cohortids) {
            return 0;
        }
        [$insql, $params] = $DB->get_in_or_equal($cohortids, SQL_PARAMS_NAMED);
        $userids = $DB->get_fieldset_sql(
            "SELECT DISTINCT cm.userid
               FROM {cohort_members} cm
          LEFT JOIN {tool_teacherscaffold_user} t ON t.userid = cm.userid
              WHERE cm.cohortid $insql AND t.id IS NULL", $params);
        $count = 0;
        foreach ($userids as $userid) {
            $count += self::track((int)$userid) ? 1 : 0;
        }
        return $count;
    }

    /**
     * Ids of the cohorts whose members are tracked.
     *
     * @return int[]
     */
    public static function cohort_ids(): array {
        $value = (string)get_config('tool_teacherscaffold', 'cohorts');
        return array_values(array_filter(array_map('intval', explode(',', $value))));
    }
}
