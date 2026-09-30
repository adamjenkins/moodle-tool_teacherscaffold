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
 * Report of guided teachers, with actions to unlock, restart or finish their stages.
 *
 * @package    tool_teacherscaffold
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_teacherscaffold\local\tier_config;
use tool_teacherscaffold\local\tracker;

admin_externalpage_setup('tool_teacherscaffold_report');
require_capability('tool/teacherscaffold:manage', context_system::instance());

$action = optional_param('action', '', PARAM_ALPHA);
$userid = optional_param('userid', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$baseurl = new moodle_url('/admin/tool/teacherscaffold/report.php');

$actions = ['advance' => 'actionadvance', 'reset' => 'actionreset', 'graduate' => 'actiongraduate'];
if ($action && isset($actions[$action])) {
    $record = tracker::get_record($userid);
    if (!$record) {
        redirect($baseurl);
    }
    $name = fullname(core_user::get_user($record->userid, '*', MUST_EXIST));
    if ($confirm && confirm_sesskey()) {
        $done = match ($action) {
            'advance' => tracker::advance($userid),
            'reset' => tracker::reset($userid),
            'graduate' => tracker::graduate($userid),
        };
        // Redirect messages are printed unescaped by the notification template, so escape here.
        $message = $done ? s(get_string($actions[$action] . 'done', 'tool_teacherscaffold', $name)) : '';
        redirect($baseurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        s(get_string($actions[$action] . 'confirm', 'tool_teacherscaffold', $name)),
        new moodle_url($baseurl, ['action' => $action, 'userid' => $userid, 'confirm' => 1, 'sesskey' => sesskey()]),
        $baseurl
    );
    echo $OUTPUT->footer();
    die();
}

$form = new \tool_teacherscaffold\form\add_teachers($baseurl);
if ($data = $form->get_data()) {
    $added = [];
    foreach ((array)$data->userids as $id) {
        if (tracker::track((int)$id)) {
            $added[] = fullname(core_user::get_user((int)$id));
        }
    }
    if ($added) {
        redirect(
            $baseurl,
            s(get_string('addteachersdone', 'tool_teacherscaffold', implode(', ', $added))),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    redirect(
        $baseurl,
        get_string('addteachersnone', 'tool_teacherscaffold'),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report', 'tool_teacherscaffold'));

// Checks.
$checks = [];
if (!tracker::enabled()) {
    $checks[] = get_string('healthdisabled', 'tool_teacherscaffold');
}
$admins = array_filter(array_map('intval', explode(',', (string)$CFG->siteadmins)));
if ($admins) {
    [$insql, $params] = $DB->get_in_or_equal($admins);
    $trackedadmins = $DB->get_fieldset_select('tool_teacherscaffold_user', 'userid', "userid $insql", $params);
    if ($trackedadmins) {
        $names = array_map(fn($id) => fullname(core_user::get_user($id)), $trackedadmins);
        $checks[] = get_string('healthadmins', 'tool_teacherscaffold', implode(', ', $names));
    }
}
if ($unrestrictable = tier_config::site_unrestrictable_modules()) {
    $checks[] = get_string('healthunlockable', 'tool_teacherscaffold', tier_config::module_names($unrestrictable));
}
$switchroles = $DB->get_records_sql(
    "SELECT DISTINCT r.*
       FROM {role_allow_switch} ras
       JOIN {role} fromrole ON fromrole.id = ras.roleid
       JOIN {role} r ON r.id = ras.allowswitch
       JOIN {role_capabilities} rc ON rc.roleid = r.id AND rc.contextid = :syscontext
      WHERE fromrole.archetype IN ('editingteacher', 'teacher')
        AND rc.capability = :cap AND rc.permission = :allow",
    ['syscontext' => context_system::instance()->id, 'cap' => 'moodle/course:manageactivities', 'allow' => CAP_ALLOW]
);
if ($switchroles) {
    $names = array_map(fn($role) => role_get_name($role), $switchroles);
    $checks[] = get_string('healthswitchrole', 'tool_teacherscaffold', implode(', ', $names));
}
echo $OUTPUT->heading(get_string('health', 'tool_teacherscaffold'), 3);
if ($checks) {
    echo html_writer::alist(array_map('s', $checks));
} else {
    echo html_writer::tag('p', s(get_string('healthok', 'tool_teacherscaffold')));
}

echo $OUTPUT->heading(get_string('addteachers', 'tool_teacherscaffold'), 3);
$form->display();

echo $OUTPUT->heading(get_string('report', 'tool_teacherscaffold'), 3);
if (!$DB->record_exists('tool_teacherscaffold_user', [])) {
    echo html_writer::tag('p', s(get_string('nousers', 'tool_teacherscaffold')));
} else {
    $table = new \tool_teacherscaffold\output\report_table($baseurl);
    $table->out(30, true);
}

echo $OUTPUT->footer();
