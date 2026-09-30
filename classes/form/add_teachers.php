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

namespace tool_teacherscaffold\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for adding teachers to Teacher scaffold by hand.
 *
 * @package    tool_teacherscaffold
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_teachers extends \moodleform {
    /**
     * Define the form.
     */
    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('autocomplete', 'userids', get_string('addteachers', 'tool_teacherscaffold'), [], [
            'multiple' => true,
            'ajax' => 'core_user/form_user_selector',
            'valuehtmlcallback' => function ($userid) {
                $user = \core_user::get_user($userid);
                return $user ? s(fullname($user)) : false;
            },
        ]);
        $mform->setType('userids', PARAM_INT);
        $mform->addHelpButton('userids', 'addteachers', 'tool_teacherscaffold');
        $mform->addRule('userids', get_string('required'), 'required', null, 'client');
        $this->add_action_buttons(false, get_string('addteachers', 'tool_teacherscaffold'));
    }
}
