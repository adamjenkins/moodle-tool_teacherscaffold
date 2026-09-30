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
 * The stage ("tier") configuration and the maths of which modules each stage locks.
 *
 * Stages 1..n are listed by the admin. Stage n + 1, the final stage, is implicit: it holds
 * every lockable module that no listed stage names, so a newly installed module is locked
 * at every listed stage until an admin adds it to one.
 *
 * @package    tool_teacherscaffold
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tier_config {
    /** @var string[] Modules that are never restricted: question banks are infrastructure, subsections are course structure. */
    const NEVER_LOCKED = ['qbank', 'subsection'];

    /** @var array Default stages, used until the admin saves their own. */
    const DEFAULT_TIERS = [
        ['resource', 'page', 'forum', 'label'],
        ['url', 'folder', 'assign', 'glossary'],
        ['quiz', 'choice', 'feedback'],
    ];

    /** @var array[] The listed stages, each a list of module short names. */
    protected array $tiers;

    /** @var string[] Short names of the lockable modules installed on this site. */
    protected array $lockable;

    /**
     * Constructor.
     *
     * @param array[]|null $tiers Listed stages, or null to read the saved configuration.
     * @param string[]|null $lockable Lockable module names, or null to read them from the site.
     */
    public function __construct(?array $tiers = null, ?array $lockable = null) {
        $this->lockable = $lockable ?? self::site_lockable_modules();
        $this->tiers = $tiers ?? self::saved_tiers();
    }

    /**
     * The stages saved in the plugin configuration, or the defaults.
     *
     * @return array[]
     */
    public static function saved_tiers(): array {
        $json = get_config('tool_teacherscaffold', 'tiers');
        $tiers = $json ? json_decode($json, true) : null;
        if (!is_array($tiers) || !$tiers) {
            return self::DEFAULT_TIERS;
        }
        return array_values(array_map(fn($tier) => array_values((array)$tier), $tiers));
    }

    /**
     * Every installed module that this plugin can restrict.
     *
     * A module qualifies when it is shown in the activity chooser, is not in NEVER_LOCKED and
     * defines mod/NAME:addinstance. Modules without that capability can always be added
     * (course_allowed_module() in course/lib.php returns true for them).
     *
     * @return string[]
     */
    public static function site_lockable_modules(): array {
        global $DB;
        $result = [];
        foreach ($DB->get_fieldset_select('modules', 'name', '1 = 1', [], 'name') as $name) {
            if (self::is_lockable($name)) {
                $result[] = $name;
            }
        }
        return $result;
    }

    /**
     * Installed modules that can never be restricted because they have no addinstance capability.
     *
     * @return string[]
     */
    public static function site_unrestrictable_modules(): array {
        global $DB;
        $result = [];
        foreach ($DB->get_fieldset_select('modules', 'name', '1 = 1', [], 'name') as $name) {
            if (!in_array($name, self::NEVER_LOCKED) && self::displayable($name) && !self::has_addinstance($name)) {
                $result[] = $name;
            }
        }
        return $result;
    }

    /**
     * Whether a module can be restricted by this plugin.
     *
     * @param string $modname Module short name.
     * @return bool
     */
    public static function is_lockable(string $modname): bool {
        return !in_array($modname, self::NEVER_LOCKED) && self::displayable($modname) && self::has_addinstance($modname);
    }

    /**
     * Whether the module can appear in the activity chooser at all.
     *
     * @param string $modname Module short name.
     * @return bool
     */
    protected static function displayable(string $modname): bool {
        return (bool)plugin_supports('mod', $modname, FEATURE_CAN_DISPLAY, true);
    }

    /**
     * Whether the module defines the capability for adding it.
     *
     * @param string $modname Module short name.
     * @return bool
     */
    protected static function has_addinstance(string $modname): bool {
        return (bool)get_capability_info('mod/' . $modname . ':addinstance');
    }

    /**
     * The listed stages, as saved (may name modules that are no longer installed).
     *
     * @return array[]
     */
    public function get_tiers(): array {
        return $this->tiers;
    }

    /**
     * Number of listed stages. The final stage is this plus one.
     *
     * @return int
     */
    public function count(): int {
        return count($this->tiers);
    }

    /**
     * The number of the implicit final stage.
     *
     * @return int
     */
    public function final_tier(): int {
        return $this->count() + 1;
    }

    /**
     * The lockable modules installed on the site.
     *
     * @return string[]
     */
    public function get_lockable(): array {
        return $this->lockable;
    }

    /**
     * The modules of one stage that exist and are lockable, in configuration order.
     *
     * For the final stage this is every lockable module no listed stage names.
     *
     * @param int $tier 1-based stage number.
     * @return string[]
     */
    public function modules_in(int $tier): array {
        if ($tier >= $this->final_tier()) {
            return array_values(array_diff($this->lockable, $this->allowed_at($this->count())));
        }
        if ($tier < 1) {
            return [];
        }
        return array_values(array_intersect($this->tiers[$tier - 1], $this->lockable));
    }

    /**
     * Modules a teacher at this stage may add.
     *
     * @param int $tier 1-based stage number.
     * @return string[]
     */
    public function allowed_at(int $tier): array {
        if ($tier >= $this->final_tier()) {
            return $this->lockable;
        }
        $allowed = [];
        for ($i = 1; $i <= $tier; $i++) {
            $allowed = array_merge($allowed, $this->modules_in($i));
        }
        return array_values(array_unique($allowed));
    }

    /**
     * Modules a teacher at this stage may not add.
     *
     * @param int $tier 1-based stage number.
     * @return string[]
     */
    public function locked_at(int $tier): array {
        return array_values(array_diff($this->lockable, $this->allowed_at($tier)));
    }

    /**
     * Modules that become available on reaching a stage, in configuration order.
     *
     * @param int $tier 1-based stage number that has just been reached.
     * @return string[]
     */
    public function unlocked_by_reaching(int $tier): array {
        return array_values(array_diff($this->modules_in($tier), $this->allowed_at($tier - 1)));
    }

    /**
     * Parse the admin's text (one stage per line, names separated by commas).
     *
     * @param string $text The text from the settings form.
     * @param string[] $lockable Lockable modules on the site.
     * @param string[] $installed All installed module names.
     * @return array[]|string The stages, or a localised error message (plain text; the admin template escapes it).
     */
    public static function parse_text(string $text, array $lockable, array $installed) {
        $tiers = [];
        $seen = [];
        foreach (preg_split('/\R/', $text) as $line) {
            $names = array_values(array_filter(array_map(
                fn($name) => \core_text::strtolower(trim($name)),
                explode(',', $line)
            ), 'strlen'));
            if (!$names) {
                continue;
            }
            foreach ($names as $name) {
                if (isset($seen[$name])) {
                    return get_string('tierserrorduplicate', 'tool_teacherscaffold', $name);
                }
                $seen[$name] = true;
                if (!in_array($name, $installed)) {
                    return get_string('tierserrorunknown', 'tool_teacherscaffold', $name);
                }
                if (!in_array($name, $lockable)) {
                    return get_string('tierserrorlocked', 'tool_teacherscaffold', $name);
                }
            }
            $tiers[] = $names;
        }
        if (!$tiers) {
            return get_string('tierserrorempty', 'tool_teacherscaffold');
        }
        return $tiers;
    }

    /**
     * Turn stages into the text the admin edits.
     *
     * @param array[] $tiers The stages.
     * @return string
     */
    public static function to_text(array $tiers): string {
        return implode("\n", array_map(fn($tier) => implode(', ', $tier), $tiers));
    }

    /**
     * Localised, comma-separated module names.
     *
     * @param string[] $modnames Module short names.
     * @return string
     */
    public static function module_names(array $modnames): string {
        $names = [];
        foreach ($modnames as $modname) {
            $names[] = get_string_manager()->string_exists('modulename', 'mod_' . $modname)
                ? get_string('modulename', 'mod_' . $modname) : $modname;
        }
        return implode(', ', $names);
    }
}
