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

/**
 * The unlock rules: how far a teacher is through their current stage.
 *
 * @package    tool_teacherscaffold
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class progress {
    /** @var string Unlock when every module of the stage has been added at least once. */
    const RULE_TRYEACH = 'trieach';

    /** @var string Unlock after a number of activities of the stage have been added. */
    const RULE_ADDCOUNT = 'addcount';

    /**
     * The configured unlock rule.
     *
     * @return string One of the RULE_ constants.
     */
    public static function rule(): string {
        return get_config('tool_teacherscaffold', 'unlockrule') === self::RULE_ADDCOUNT
            ? self::RULE_ADDCOUNT : self::RULE_TRYEACH;
    }

    /**
     * Activities needed under the "add a number" rule (at least one).
     *
     * @return int
     */
    public static function unlock_count(): int {
        return max(1, (int)get_config('tool_teacherscaffold', 'unlockcount'));
    }

    /**
     * Module types the user has ever added while tracked.
     *
     * @param int $userid User id.
     * @return string[]
     */
    public static function used_modules(int $userid): array {
        global $DB;
        return $DB->get_fieldset_select('tool_teacherscaffold_usage', 'modname', 'userid = ?', [$userid]);
    }

    /**
     * Progress through the user's current stage.
     *
     * Under the "try each" rule a module first used at an earlier stage counts, so nobody is
     * asked to repeat something they already did.
     *
     * @param \stdClass $record Row of tool_teacherscaffold_user.
     * @param tier_config $config Stage configuration.
     * @return array With int keys 'done' and 'total'.
     */
    public static function for_user(\stdClass $record, tier_config $config): array {
        $modules = $config->modules_in((int)$record->tier);
        if (!$modules) {
            // Nothing left to use at this stage (its modules were uninstalled): pass it under either rule.
            return ['done' => 0, 'total' => 0];
        }
        if (self::rule() === self::RULE_ADDCOUNT) {
            $total = self::unlock_count();
            return ['done' => min((int)$record->tieradds, $total), 'total' => $total];
        }
        $done = count(array_intersect($modules, self::used_modules((int)$record->userid)));
        return ['done' => $done, 'total' => count($modules)];
    }

    /**
     * Whether the stage is complete, so the next one should unlock.
     *
     * A stage whose modules are all uninstalled has nothing to try and counts as complete.
     *
     * @param array $progress From for_user().
     * @return bool
     */
    public static function is_complete(array $progress): bool {
        return $progress['done'] >= $progress['total'];
    }
}
