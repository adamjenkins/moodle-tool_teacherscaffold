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

/**
 * Creates and maintains the plugin's own stage roles. Never touches any other role.
 *
 * Each listed stage has one role. Its definition is exactly: CAP_PROHIBIT at system context on
 * mod/NAME:addinstance for every module still locked at that stage, plus (when "Block import and
 * restore" is on) the capabilities that could bring locked modules in by other routes.
 * A prohibit in a role held at system context overrides every allow further down
 * (has_capability_in_accessdata() in lib/accesslib.php).
 *
 * @package    tool_teacherscaffold
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class role_manager {
    /** @var string Shortname prefix of the stage roles; the stage number follows. */
    const SHORTNAME_PREFIX = 'teacherscaffoldtier';

    /**
     * @var string[] Capabilities that let a teacher bring activities into a course without
     * the addinstance check: restore, import, single-activity duplicate and the recycle bin.
     */
    const IMPORT_CAPABILITIES = [
        'moodle/backup:backuptargetimport',
        'moodle/restore:restoreactivity',
        'moodle/restore:restorecourse',
        'moodle/restore:restoresection',
        'moodle/restore:restoretargetimport',
        'tool/recyclebin:restoreitems',
    ];

    /**
     * Stage number => role id, as last synced.
     *
     * @return int[]
     */
    public static function role_ids(): array {
        $ids = json_decode((string)get_config('tool_teacherscaffold', 'roleids'), true);
        if (!is_array($ids)) {
            return [];
        }
        $result = [];
        foreach ($ids as $tier => $roleid) {
            $result[(int)$tier] = (int)$roleid;
        }
        return $result;
    }

    /**
     * The role id for a stage, or null if the stage has no role (final stage, or not synced).
     *
     * @param int $tier Stage number.
     * @return int|null
     */
    public static function role_id(int $tier): ?int {
        return self::role_ids()[$tier] ?? null;
    }

    /**
     * Whether "Block import and restore" is on (on until an admin turns it off).
     *
     * @return bool
     */
    public static function block_import(): bool {
        $value = get_config('tool_teacherscaffold', 'blockimport');
        return $value === false || (bool)$value;
    }

    /**
     * The capabilities a stage's role should prohibit.
     *
     * @param tier_config $config Stage configuration.
     * @param int $tier Stage number.
     * @return string[]
     */
    public static function wanted_capabilities(tier_config $config, int $tier): array {
        $locked = $config->locked_at($tier);
        $caps = array_map(fn($modname) => 'mod/' . $modname . ':addinstance', $locked);
        if (self::block_import()) {
            $caps = array_merge($caps, self::IMPORT_CAPABILITIES);
            if (in_array('glossary', $locked)) {
                // Glossary import can create a new glossary without the addinstance check.
                $caps[] = 'mod/glossary:import';
            }
        }
        return array_values(array_filter(array_unique($caps), fn($cap) => (bool)get_capability_info($cap)));
    }

    /**
     * Whether the roles may be out of date because plugins changed since the last sync.
     *
     * Core has no hook for "a plugin was installed or removed", but it rewrites
     * $CFG->allversionshash after every upgrade run and clears it on uninstall.
     *
     * @return bool
     */
    public static function needs_sync(): bool {
        global $CFG;
        if (during_initial_install() || !empty($CFG->upgraderunning) || empty($CFG->allversionshash)) {
            return false;
        }
        if (!get_config('tool_teacherscaffold', 'version')) {
            return false;
        }
        return get_config('tool_teacherscaffold', 'syncedhash') !== $CFG->allversionshash;
    }

    /**
     * Sync now if plugins changed since the last sync.
     */
    public static function sync_if_needed(): void {
        if (self::needs_sync()) {
            // Never make a page wait: if another request is already syncing, it will finish the job.
            self::sync(null, 0, true);
        }
    }

    /**
     * Ask for a sync on the next page view (used when a sync could not run now).
     */
    public static function mark_sync_pending(): void {
        set_config('syncedhash', '', 'tool_teacherscaffold');
    }

    /**
     * The stage number encoded in one of this plugin's role shortnames, or null for any other role.
     *
     * Only an exact "teacherscaffoldtier<N>" counts, so a role an admin named
     * "teacherscaffoldtier1custom" is never touched.
     *
     * @param string $shortname Role shortname.
     * @return int|null
     */
    public static function tier_from_shortname(string $shortname): ?int {
        return preg_match('/^' . self::SHORTNAME_PREFIX . '([1-9][0-9]*)$/', $shortname, $m) ? (int)$m[1] : null;
    }

    /**
     * This plugin's roles found by shortname: role id => stage number.
     *
     * @return int[]
     */
    protected static function own_roles(): array {
        global $DB;
        $like = $DB->sql_like('shortname', ':prefix');
        $result = [];
        $roles = $DB->get_records_select_menu('role', $like, ['prefix' => self::SHORTNAME_PREFIX . '%'], '', 'id, shortname');
        foreach ($roles as $roleid => $shortname) {
            $tier = self::tier_from_shortname($shortname);
            if ($tier !== null) {
                $result[(int)$roleid] = $tier;
            }
        }
        return $result;
    }

    /**
     * Settings callback: the stages or the import setting changed.
     *
     * @param string $name Full name of the changed setting (unused).
     */
    public static function sync_after_settings(string $name = ''): void {
        self::sync();
    }

    /**
     * Make the stage roles match the configuration, then fix everyone's role assignments.
     *
     * @param tier_config|null $config Stage configuration, or null for the saved one.
     * @param int $timeout Seconds to wait for another sync to finish.
     * @param bool $onlyifneeded Skip if, once the lock is held, nothing needs syncing any more.
     * @return bool Whether a sync ran.
     */
    public static function sync(?tier_config $config = null, int $timeout = 10, bool $onlyifneeded = false): bool {
        global $CFG;

        $lock = \core\lock\lock_config::get_lock_factory('tool_teacherscaffold')->get_lock('rolesync', $timeout);
        if (!$lock) {
            if (!$onlyifneeded) {
                // A change must not be lost: the next page view syncs instead.
                self::mark_sync_pending();
            }
            return false;
        }
        try {
            if ($onlyifneeded && !self::needs_sync()) {
                // Another request synced while this one waited.
                return false;
            }
            $config = $config ?? new tier_config();
            $syscontext = context_system::instance();
            $ids = self::role_ids();

            for ($tier = 1; $tier <= $config->count(); $tier++) {
                $ids[$tier] = self::ensure_role($tier, $ids[$tier] ?? null);
                self::sync_role_capabilities($ids[$tier], self::wanted_capabilities($config, $tier), $syscontext);
            }

            // Roles of stages that no longer exist (exact stage shortnames only).
            foreach (self::own_roles() as $roleid => $tier) {
                if ($tier > $config->count() || $ids[$tier] != $roleid) {
                    delete_role($roleid);
                }
            }
            $ids = array_filter($ids, fn($tier) => $tier <= $config->count(), ARRAY_FILTER_USE_KEY);

            set_config('roleids', json_encode($ids), 'tool_teacherscaffold');
            set_config('syncedhash', $CFG->allversionshash ?? '', 'tool_teacherscaffold');

            tracker::clamp_to($config->count());
            tracker::reconcile_all();
            return true;
        } finally {
            $lock->release();
        }
    }

    /**
     * Return the id of the stage's role, creating it if needed.
     *
     * @param int $tier Stage number.
     * @param int|null $roleid Id from the last sync, if any.
     * @return int
     */
    protected static function ensure_role(int $tier, ?int $roleid): int {
        global $DB;
        $shortname = self::SHORTNAME_PREFIX . $tier;
        if ($roleid && !$DB->record_exists('role', ['id' => $roleid, 'shortname' => $shortname])) {
            $roleid = null;
        }
        if (!$roleid) {
            $roleid = (int)$DB->get_field('role', 'id', ['shortname' => $shortname]);
        }
        if (!$roleid) {
            $roleid = create_role(
                get_string('rolename', 'tool_teacherscaffold', $tier),
                $shortname,
                get_string('roledescription', 'tool_teacherscaffold', $tier),
                ''
            );
        }
        // No context levels: the role never appears in the manual "Assign roles" menus.
        // role_assign() does not consult role_context_levels, so the plugin can still assign it.
        set_role_contextlevels($roleid, []);
        return $roleid;
    }

    /**
     * Make a role's definition exactly "prohibit these capabilities at system context".
     *
     * @param int $roleid Role id (one of this plugin's roles only).
     * @param string[] $wanted Capabilities to prohibit.
     * @param \context $syscontext System context.
     */
    protected static function sync_role_capabilities(int $roleid, array $wanted, \context $syscontext): void {
        global $DB;
        $existing = [];
        foreach ($DB->get_records('role_capabilities', ['roleid' => $roleid]) as $row) {
            $keep = $row->contextid == $syscontext->id && in_array($row->capability, $wanted);
            if ($keep) {
                $existing[$row->capability] = (int)$row->permission;
                continue;
            }
            if (get_capability_info($row->capability)) {
                unassign_capability($row->capability, $roleid, $row->contextid);
            } else {
                // Left behind by an uninstalled plugin; core ignores it, so just remove the row.
                $DB->delete_records('role_capabilities', ['id' => $row->id]);
            }
        }
        foreach ($wanted as $capability) {
            if (($existing[$capability] ?? null) !== CAP_PROHIBIT) {
                assign_capability($capability, CAP_PROHIBIT, $roleid, $syscontext->id, true);
            }
        }
    }

    /**
     * Delete every stage role. Used on uninstall.
     */
    public static function delete_all_roles(): void {
        foreach (array_keys(self::own_roles()) as $roleid) {
            delete_role($roleid);
        }
    }
}
