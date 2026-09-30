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
 * Opt out of the step-by-step activity list: one click plus a confirmation.
 *
 * @package    tool_teacherscaffold
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_teacherscaffold\local\tracker;

require_login(null, false);
if (isguestuser()) {
    throw new require_login_exception('guestsarenotallowed');
}

$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$url = new moodle_url('/admin/tool/teacherscaffold/optout.php', $returnurl ? ['returnurl' => $returnurl] : []);
$PAGE->set_url($url);
$PAGE->set_context(context_user::instance($USER->id));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('optoutheading', 'tool_teacherscaffold'));
$PAGE->set_heading(get_string('optoutheading', 'tool_teacherscaffold'));

$return = $returnurl ? new moodle_url($returnurl) : new moodle_url('/admin/tool/teacherscaffold/preferences.php');

$record = tracker::get_record((int)$USER->id);
if (!$record || $record->status !== tracker::STATUS_ACTIVE) {
    redirect($return);
}

if ($confirm && confirm_sesskey()) {
    tracker::opt_out((int)$USER->id);
    redirect($return, get_string('optedoutdone', 'tool_teacherscaffold'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->confirm(
    get_string('optoutconfirm', 'tool_teacherscaffold'),
    new moodle_url($url, ['confirm' => 1, 'sesskey' => sesskey()]),
    $return
);
echo $OUTPUT->footer();
