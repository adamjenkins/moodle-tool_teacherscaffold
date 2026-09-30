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
 * The teacher's own page for the step-by-step activity list, reached from Preferences.
 *
 * @package    tool_teacherscaffold
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_teacherscaffold\local\tier_config;
use tool_teacherscaffold\local\tracker;

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('guestsarenotallowed');
}

$optin = optional_param('optin', 0, PARAM_BOOL);

$url = new moodle_url('/admin/tool/teacherscaffold/preferences.php');
$PAGE->set_url($url);
$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('preferences', 'tool_teacherscaffold'));
$PAGE->set_heading(fullname($USER));
$PAGE->navbar->add(get_string('preferences', 'moodle'), new moodle_url('/user/preferences.php'));
$PAGE->navbar->add(get_string('preferences', 'tool_teacherscaffold'), $url);

$record = tracker::get_record((int)$USER->id);
if (!$record) {
    redirect(new moodle_url('/user/preferences.php'));
}

if ($optin && confirm_sesskey()) {
    tracker::opt_in((int)$USER->id);
    $record = tracker::get_record((int)$USER->id);
    redirect(
        $url,
        get_string('optindone', 'tool_teacherscaffold', (int)$record->tier),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('preferences', 'tool_teacherscaffold'));
echo html_writer::tag('p', s(get_string('preferencesintro', 'tool_teacherscaffold')));

if ($record->status === tracker::STATUS_ACTIVE) {
    $config = new tier_config();
    echo html_writer::tag('p', s(get_string('preferencesactive', 'tool_teacherscaffold', (object)[
        'tier' => (int)$record->tier,
        'modules' => tier_config::module_names($config->allowed_at((int)$record->tier)),
    ])));
    echo $OUTPUT->single_button(new moodle_url(
        '/admin/tool/teacherscaffold/optout.php',
        ['returnurl' => $url->out_as_local_url(false)]
    ), get_string('noticeoptout', 'tool_teacherscaffold'), 'get');
} else if ($record->status === tracker::STATUS_OPTEDOUT) {
    echo html_writer::tag('p', s(get_string('preferencesoptedout', 'tool_teacherscaffold')));
    echo $OUTPUT->single_button(
        new moodle_url($url, ['optin' => 1, 'sesskey' => sesskey()]),
        get_string('optin', 'tool_teacherscaffold'),
        'post',
        ['type' => single_button::BUTTON_PRIMARY]
    );
} else {
    echo html_writer::tag('p', s(get_string('preferencesgraduated', 'tool_teacherscaffold')));
}

echo $OUTPUT->footer();
