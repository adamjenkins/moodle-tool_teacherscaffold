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
 * Admin settings and pages for Teacher scaffold.
 *
 * @package    tool_teacherscaffold
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$ADMIN->add('courses', new admin_category(
    'tool_teacherscaffold',
    new lang_string('pluginname', 'tool_teacherscaffold')
));

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'tool_teacherscaffold_settings',
        new lang_string('settings', 'tool_teacherscaffold')
    );

    if ($ADMIN->fulltree) {
        $setting = new admin_setting_configcheckbox(
            'tool_teacherscaffold/enabled',
            new lang_string('enabled', 'tool_teacherscaffold'),
            new lang_string('enabled_desc', 'tool_teacherscaffold'),
            1
        );
        $setting->set_updatedcallback([\tool_teacherscaffold\local\tracker::class, 'reconcile_all']);
        $settings->add($setting);

        $setting = new \tool_teacherscaffold\admin\setting_tiers(
            'tool_teacherscaffold/tiers',
            new lang_string('tiers', 'tool_teacherscaffold'),
            new lang_string('tiers_desc', 'tool_teacherscaffold')
        );
        $setting->set_updatedcallback([\tool_teacherscaffold\local\role_manager::class, 'sync_after_settings']);
        $settings->add($setting);

        $settings->add(new admin_setting_configselect(
            'tool_teacherscaffold/unlockrule',
            new lang_string('unlockrule', 'tool_teacherscaffold'),
            new lang_string('unlockrule_desc', 'tool_teacherscaffold'),
            \tool_teacherscaffold\local\progress::RULE_TRYEACH,
            [
                'trieach' => new lang_string('unlockruletrieach', 'tool_teacherscaffold'),
                'addcount' => new lang_string('unlockruleaddcount', 'tool_teacherscaffold'),
            ]
        ));

        $settings->add(new admin_setting_configtext(
            'tool_teacherscaffold/unlockcount',
            new lang_string('unlockcount', 'tool_teacherscaffold'),
            new lang_string('unlockcount_desc', 'tool_teacherscaffold'),
            3,
            PARAM_INT,
            4
        ));

        $setting = new admin_setting_configcheckbox(
            'tool_teacherscaffold/blockimport',
            new lang_string('blockimport', 'tool_teacherscaffold'),
            new lang_string('blockimport_desc', 'tool_teacherscaffold'),
            1
        );
        $setting->set_updatedcallback([\tool_teacherscaffold\local\role_manager::class, 'sync_after_settings']);
        $settings->add($setting);

        $setting = new admin_setting_configcheckbox(
            'tool_teacherscaffold/autotrack',
            new lang_string('autotrack', 'tool_teacherscaffold'),
            new lang_string('autotrack_desc', 'tool_teacherscaffold'),
            0
        );
        $settings->add($setting);

        $cohorts = $DB->get_records_menu('cohort', ['contextid' => context_system::instance()->id], 'name', 'id, name');
        $setting = new admin_setting_configmultiselect(
            'tool_teacherscaffold/cohorts',
            new lang_string('cohorts', 'tool_teacherscaffold'),
            new lang_string('cohorts_desc', 'tool_teacherscaffold'),
            [],
            array_map('format_string', $cohorts)
        );
        $setting->set_updatedcallback([\tool_teacherscaffold\local\tracker::class, 'sync_cohorts']);
        $settings->add($setting);

        $settings->add(new admin_setting_heading(
            'tool_teacherscaffold/limitations',
            new lang_string('limitations', 'tool_teacherscaffold'),
            new lang_string('limitations_desc', 'tool_teacherscaffold')
        ));
    }
    $ADMIN->add('tool_teacherscaffold', $settings);
}

// Registered outside the site-config check so that holders of the plugin's own capability can reach it.
$ADMIN->add('tool_teacherscaffold', new admin_externalpage(
    'tool_teacherscaffold_report',
    new lang_string('report', 'tool_teacherscaffold'),
    new moodle_url('/admin/tool/teacherscaffold/report.php'),
    'tool/teacherscaffold:manage'
));
