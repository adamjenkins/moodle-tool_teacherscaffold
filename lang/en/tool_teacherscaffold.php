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
 * English strings for Teacher scaffold.
 *
 * @package    tool_teacherscaffold
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action'] = 'Action';
$string['actionadvance'] = 'Unlock next stage';
$string['actionadvanceconfirm'] = 'Unlock the next stage for {$a}?';
$string['actionadvancedone'] = 'The next stage has been unlocked for {$a}.';
$string['actiongraduate'] = 'Show everything';
$string['actiongraduateconfirm'] = 'Give {$a} the full list of activities now? They will no longer be guided.';
$string['actiongraduatedone'] = '{$a} now sees every activity.';
$string['actionreset'] = 'Start again';
$string['actionresetconfirm'] = 'Put {$a} back at stage 1? Their record of activities tried will be cleared.';
$string['actionresetdone'] = '{$a} is back at stage 1.';
$string['addteachers'] = 'Add teachers';
$string['addteachers_help'] = 'The teachers you add start at stage 1. Site administrators cannot be added, because the restrictions never apply to them.';
$string['addteachersdone'] = 'Teachers added: {$a}.';
$string['addteachersnone'] = 'No one was added. They may already be in the list, or be site administrators.';
$string['autotrack'] = 'Add new teachers automatically';
$string['autotrack_desc'] = 'When someone is given an editing teacher role for the first time anywhere on the site, add them at stage 1.';
$string['blockimport'] = 'Block import and restore';
$string['blockimport_desc'] = 'Guided teachers can\'t import from other courses, restore backups, duplicate single activities or restore items from the recycle bin, because these could add activities that are not unlocked yet. Everything comes back when they unlock the final stage or choose to see everything.';
$string['cohorts'] = 'Add members of these cohorts';
$string['cohorts_desc'] = 'Members of the selected site cohorts are added at stage 1, including people who join later.';
$string['enabled'] = 'Enable Teacher scaffold';
$string['enabled_desc'] = 'When this is off, nobody is restricted. Stages and progress are kept, and come back when it is turned on again.';
$string['eventtierunlocked'] = 'Stage unlocked';
$string['finaltier'] = 'Final stage: everything else';
$string['health'] = 'Checks';
$string['healthadmins'] = 'These people are site administrators. Restrictions never apply to them, so they are not guided: {$a}';
$string['healthdisabled'] = 'Teacher scaffold is turned off. Nobody is restricted at the moment.';
$string['healthok'] = 'Everything looks fine.';
$string['healthswitchrole'] = 'Teachers can switch to these roles, which can add activities. While switched, the restrictions do not apply: {$a}';
$string['healthunlockable'] = 'These activities can\'t be restricted, because they have no permission for adding them: {$a}';
$string['lastactivity'] = 'Last activity added';
$string['limitations'] = 'Things to know';
$string['limitations_desc'] = 'Teacher scaffold keeps activities out of the activity chooser. It is guidance, not a lock. With "Block import and restore" on, guided teachers also can\'t import, restore, duplicate single activities or restore from the recycle bin. They can still duplicate a whole section, which copies every activity in it. Activities added this way do not count as progress.';
$string['modulesnotinstalled'] = 'Not installed, ignored: {$a}';
$string['noticeoptout'] = 'Show me everything';
$string['noticeprogressadd'] = 'Getting started: {$a->done} of {$a->total} activities added in this stage.';
$string['noticeprogresstry'] = 'Getting started: {$a->done} of {$a->total} tried in this stage.';
$string['nousers'] = 'No teachers are being guided yet.';
$string['optedoutdone'] = 'You can now add every type of activity. You can turn the step-by-step list back on from your preferences.';
$string['optin'] = 'Turn the step-by-step list back on';
$string['optindone'] = 'The step-by-step list is back on. You are at stage {$a}.';
$string['optoutconfirm'] = 'Show every type of activity from now on? You can turn the step-by-step list back on later from your preferences.';
$string['optoutheading'] = 'Show me everything';
$string['pluginname'] = 'Teacher scaffold';
$string['preferences'] = 'Step-by-step activity list';
$string['preferencesactive'] = 'You are at stage {$a->tier}. You can add: {$a->modules}.';
$string['preferencesgraduated'] = 'You have unlocked every type of activity.';
$string['preferencesintro'] = 'New teachers start with a few simple activities. More are unlocked as you try them.';
$string['preferencesoptedout'] = 'The step-by-step list is off. You can add every type of activity.';
$string['privacy:metadata:core_role'] = 'Teacher scaffold gives each guided teacher a site-level role that hides the activities they have not unlocked yet.';
$string['privacy:metadata:preference:optedout'] = 'Whether the user chose to see every type of activity.';
$string['privacy:metadata:tool_teacherscaffold_usage'] = 'The first time the user added each type of activity.';
$string['privacy:metadata:tool_teacherscaffold_usage:modname'] = 'The type of activity.';
$string['privacy:metadata:tool_teacherscaffold_usage:timefirstused'] = 'When the user first added this type of activity.';
$string['privacy:metadata:tool_teacherscaffold_usage:userid'] = 'The user.';
$string['privacy:metadata:tool_teacherscaffold_user'] = 'Which stage the user has reached.';
$string['privacy:metadata:tool_teacherscaffold_user:status'] = 'Whether the user is being guided, chose to see everything, or has unlocked everything.';
$string['privacy:metadata:tool_teacherscaffold_user:tier'] = 'The stage the user has reached.';
$string['privacy:metadata:tool_teacherscaffold_user:tieradds'] = 'How many activities of the current stage the user has added.';
$string['privacy:metadata:tool_teacherscaffold_user:timecreated'] = 'When the user started being guided.';
$string['privacy:metadata:tool_teacherscaffold_user:timemodified'] = 'When the record last changed.';
$string['privacy:metadata:tool_teacherscaffold_user:userid'] = 'The user.';
$string['progress'] = 'Progress';
$string['progressvalue'] = '{$a->done} of {$a->total}';
$string['report'] = 'Guided teachers';
$string['roledescription'] = 'Managed by Teacher scaffold. Hides the activities that are not unlocked yet at stage {$a}. Do not edit or assign this role by hand.';
$string['rolename'] = 'Teacher scaffold: stage {$a}';
$string['settings'] = 'Teacher scaffold settings';
$string['stage'] = 'Stage';
$string['stagevalue'] = '{$a->tier} of {$a->total}';
$string['status'] = 'Status';
$string['statusactive'] = 'Guided';
$string['statusgraduated'] = 'Everything unlocked';
$string['statusoptedout'] = 'Chose to see everything';
$string['taskmaintenance'] = 'Keep Teacher scaffold roles and cohort members up to date';
$string['teacherscaffold:manage'] = 'Manage Teacher scaffold';
$string['tier'] = 'Stage {$a}';
$string['tiers'] = 'Stages';
$string['tiers_desc'] = 'One stage per line, in order. List the short names of the activities, separated by commas, for example: resource, page, forum. Everything not listed is unlocked at the final stage. Question banks and subsections are never restricted.';
$string['tierserrorduplicate'] = 'This activity is listed more than once: {$a}';
$string['tierserrorempty'] = 'Add at least one stage.';
$string['tierserrorlocked'] = 'This activity can\'t be restricted, so it can\'t be put in a stage: {$a}';
$string['tierserrorunknown'] = 'This is not an installed activity: {$a}';
$string['unlockcount'] = 'Number of activities';
$string['unlockcount_desc'] = 'Used with the rule "Add a number of activities from the current stage".';
$string['unlocked'] = 'Nice work! You\'ve unlocked: {$a}.';
$string['unlockrule'] = 'When to unlock the next stage';
$string['unlockrule_desc'] = 'Choose what a teacher needs to do before more activities appear.';
$string['unlockruleaddcount'] = 'Add a number of activities from the current stage';
$string['unlockruletrieach'] = 'Use every activity in the current stage at least once';
