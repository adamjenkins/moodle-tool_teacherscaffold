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

namespace tool_teacherscaffold\event;

/**
 * A tracked teacher reached a new stage.
 *
 * Shared contract with tool_wizards (dev-docs/new-moodle-user-help/RELATIONS.md), frozen:
 * context is system, relateduserid is the teacher, and other is
 * {"tier": int, "unlockedmodules": [module short names]}. 'tier' is the 1-based stage now
 * reached; 'unlockedmodules' lists the newly allowed modules in stage order, explicitly even for
 * the final stage. Renaming this class or those keys silently breaks observers elsewhere.
 *
 * @package    tool_teacherscaffold
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tier_unlocked extends \core\event\base {
    /**
     * Initialise the event.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventtierunlocked', 'tool_teacherscaffold');
    }

    /**
     * Non-localised description, as core events use.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->relateduserid}' reached stage {$this->other['tier']} of Teacher scaffold.";
    }

    /**
     * Validate the contract fields.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();
        if ($this->contextlevel != CONTEXT_SYSTEM) {
            throw new \coding_exception('The context must be the system context.');
        }
        if (empty($this->relateduserid)) {
            throw new \coding_exception('The relateduserid must be set.');
        }
        if (!isset($this->other['tier']) || !is_int($this->other['tier'])) {
            throw new \coding_exception('other[tier] must be an integer.');
        }
        if (!isset($this->other['unlockedmodules']) || !is_array($this->other['unlockedmodules'])) {
            throw new \coding_exception('other[unlockedmodules] must be an array.');
        }
    }

    /**
     * Nothing in 'other' refers to database ids.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
