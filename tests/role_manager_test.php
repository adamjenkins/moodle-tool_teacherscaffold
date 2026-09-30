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

namespace tool_teacherscaffold;

use context_system;
use PHPUnit\Framework\Attributes\CoversClass;
use tool_teacherscaffold\admin\setting_tiers;
use tool_teacherscaffold\local\role_manager;
use tool_teacherscaffold\local\tier_config;
use tool_teacherscaffold\local\tracker;

/**
 * Tests for the stage roles and their sync.
 *
 * @package    tool_teacherscaffold
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(role_manager::class)]
#[CoversClass(tier_config::class)]
#[CoversClass(setting_tiers::class)]
final class role_manager_test extends \advanced_testcase {
    /**
     * Load course/lib.php for course_allowed_module().
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        parent::setUpBeforeClass();
    }

    /**
     * Capabilities a role prohibits at system context.
     *
     * @param int $roleid Role id.
     * @return string[] Sorted.
     */
    protected function prohibited(int $roleid): array {
        global $DB;
        $caps = $DB->get_fieldset_select(
            'role_capabilities',
            'capability',
            'roleid = ? AND contextid = ? AND permission = ?',
            [$roleid, context_system::instance()->id, CAP_PROHIBIT]
        );
        sort($caps);
        return $caps;
    }

    /**
     * Save stages through the admin setting, then run its callback as the admin framework does.
     *
     * @param string $text One stage per line.
     */
    protected function save_tiers(string $text): void {
        $setting = new setting_tiers('tool_teacherscaffold/tiers', 'Stages', '');
        $this->assertSame('', $setting->write_setting($text));
        role_manager::sync_after_settings('tool_teacherscaffold/tiers');
    }

    /**
     * Installing the plugin creates one role per default stage, hidden from manual assignment.
     */
    public function test_install_creates_default_roles(): void {
        global $DB;
        $this->resetAfterTest();
        role_manager::sync();

        $ids = role_manager::role_ids();
        $this->assertSame([1, 2, 3], array_keys($ids));
        foreach ($ids as $roleid) {
            $this->assertSame(0, $DB->count_records('role_context_levels', ['roleid' => $roleid]));
        }
        $stage1 = $this->prohibited($ids[1]);
        $this->assertContains('mod/quiz:addinstance', $stage1);
        $this->assertContains('mod/url:addinstance', $stage1);
        $this->assertNotContains('mod/forum:addinstance', $stage1);
        $this->assertNotContains('mod/label:addinstance', $stage1);
        // Never restricted.
        $this->assertNotContains('mod/qbank:addinstance', $stage1);
        $this->assertNotContains('mod/subsection:addinstance', $stage1);
        // Stage 3 allows quiz, but still not book (only in the final stage).
        $this->assertNotContains('mod/quiz:addinstance', $this->prohibited($ids[3]));
        $this->assertContains('mod/book:addinstance', $this->prohibited($ids[3]));
    }

    /**
     * Changing the stages in the settings rebuilds the roles, deletes surplus roles and moves
     * users on a removed stage to the last listed stage.
     */
    public function test_config_change_rebuilds_roles(): void {
        global $DB;
        $this->resetAfterTest();
        role_manager::sync();
        $oldstage3 = role_manager::role_id(3);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->get_plugin_generator('tool_teacherscaffold')
            ->create_tracked_user(['userid' => $teacher->id, 'tier' => 3]);

        $this->save_tiers("page, forum\nquiz");

        $ids = role_manager::role_ids();
        $this->assertSame([1, 2], array_keys($ids));
        $this->assertFalse($DB->record_exists('role', ['id' => $oldstage3]));
        $this->assertNotContains('mod/page:addinstance', $this->prohibited($ids[1]));
        $this->assertContains('mod/resource:addinstance', $this->prohibited($ids[1]));
        $this->assertContains('mod/quiz:addinstance', $this->prohibited($ids[1]));
        $this->assertNotContains('mod/quiz:addinstance', $this->prohibited($ids[2]));

        // The teacher was on stage 3, which no longer exists: now on stage 2, holding its role.
        $this->assertEquals(2, tracker::get_record($teacher->id)->tier);
        $this->assertTrue(user_has_role_assignment($teacher->id, $ids[2], context_system::instance()->id));
    }

    /**
     * The settings text is validated.
     */
    public function test_setting_rejects_bad_stages(): void {
        $this->resetAfterTest();
        $setting = new setting_tiers('tool_teacherscaffold/tiers', 'Stages', '');
        $this->assertSame(
            get_string('tierserrorunknown', 'tool_teacherscaffold', 'nosuchmod'),
            $setting->write_setting("page\nnosuchmod")
        );
        $this->assertSame(
            get_string('tierserrorduplicate', 'tool_teacherscaffold', 'page'),
            $setting->write_setting("page\nforum, page")
        );
        $this->assertSame(
            get_string('tierserrorlocked', 'tool_teacherscaffold', 'qbank'),
            $setting->write_setting("page, qbank")
        );
        $this->assertSame(get_string('tierserrorempty', 'tool_teacherscaffold'), $setting->write_setting("\n  \n"));
        // A good value round-trips through the stored JSON.
        $this->assertSame('', $setting->write_setting("Page ,forum\n\nquiz"));
        $this->assertSame("page, forum\nquiz", $setting->get_setting());
    }

    /**
     * Edits made to a stage role by hand are undone by the next sync.
     */
    public function test_sync_overwrites_drift(): void {
        $this->resetAfterTest();
        role_manager::sync();
        $roleid = role_manager::role_id(1);
        $sys = context_system::instance()->id;
        assign_capability('mod/quiz:addinstance', CAP_ALLOW, $roleid, $sys, true);
        assign_capability('moodle/course:update', CAP_PROHIBIT, $roleid, $sys, true);

        role_manager::sync();

        $prohibited = $this->prohibited($roleid);
        $this->assertContains('mod/quiz:addinstance', $prohibited);
        $this->assertNotContains('moodle/course:update', $prohibited);
    }

    /**
     * A module that no stage names is locked at every listed stage (so a newly installed module
     * plugin is locked by default) until an admin puts it in a stage.
     */
    public function test_module_in_no_stage_is_locked_until_added(): void {
        $this->resetAfterTest();
        $this->save_tiers("page, forum\nquiz");
        $this->assertContains('mod/url:addinstance', $this->prohibited(role_manager::role_id(1)));
        $this->assertContains('mod/url:addinstance', $this->prohibited(role_manager::role_id(2)));
        $this->assertContains('url', (new tier_config())->modules_in(3), 'url belongs to the final stage');

        $this->save_tiers("page, forum, url\nquiz");
        $this->assertNotContains('mod/url:addinstance', $this->prohibited(role_manager::role_id(1)));
    }

    /**
     * When plugins change, core rewrites allversionshash; the next page's hook re-syncs.
     */
    public function test_plugin_change_triggers_sync(): void {
        global $CFG;
        $this->resetAfterTest();
        role_manager::sync();
        $this->assertFalse(role_manager::needs_sync());

        // Simulate an install/upgrade run having changed the hash, and a hand edit since.
        set_config('syncedhash', 'hash-before-plugin-change', 'tool_teacherscaffold');
        unassign_capability('mod/quiz:addinstance', role_manager::role_id(1));
        $this->assertTrue(role_manager::needs_sync());

        global $PAGE;
        \core\di::get(\core\hook\manager::class)->dispatch(
            new \core\hook\output\before_http_headers($PAGE->get_renderer('core'))
        );

        $this->assertSame($CFG->allversionshash, get_config('tool_teacherscaffold', 'syncedhash'));
        $this->assertContains('mod/quiz:addinstance', $this->prohibited(role_manager::role_id(1)));
    }

    /**
     * A teacher at stage 1 is offered only stage-1 modules in the chooser and cannot add
     * a locked one; an untracked teacher in the same course sees everything.
     */
    public function test_chooser_and_add_check_for_real_teacher(): void {
        $this->resetAfterTest();
        role_manager::sync();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $guided = $gen->create_and_enrol($course, 'editingteacher');
        $control = $gen->create_and_enrol($course, 'editingteacher');
        $gen->get_plugin_generator('tool_teacherscaffold')->create_tracked_user(['userid' => $guided->id]);

        $this->assertTrue(course_allowed_module($course, 'forum', $guided));
        $this->assertFalse(course_allowed_module($course, 'quiz', $guided));
        $this->assertTrue(course_allowed_module($course, 'quiz', $control), 'positive control');

        $service = new \core_course\local\service\content_item_service(
            new \core_course\local\repository\content_item_readonly_repository()
        );
        $names = fn($user) => array_map(
            fn($item) => $item->name,
            $service->get_content_items_for_user_in_course($user, $course)
        );
        $this->assertEqualsCanonicalizing(['forum', 'label', 'page', 'resource'], $names($guided));
        $this->assertContains('quiz', $names($control));
    }

    /**
     * "Block import and restore" adds the import capabilities; switching it off removes them.
     */
    public function test_block_import_setting(): void {
        $this->resetAfterTest();
        role_manager::sync();
        $roleid = role_manager::role_id(1);
        $this->assertContains('moodle/restore:restoretargetimport', $this->prohibited($roleid));
        $this->assertContains('tool/recyclebin:restoreitems', $this->prohibited($roleid));
        $this->assertContains('mod/glossary:import', $this->prohibited($roleid), 'glossary is locked at stage 1');
        $this->assertNotContains('mod/glossary:import', $this->prohibited(role_manager::role_id(2)));

        set_config('blockimport', 0, 'tool_teacherscaffold');
        role_manager::sync_after_settings('tool_teacherscaffold/blockimport');
        $prohibited = $this->prohibited($roleid);
        foreach (role_manager::IMPORT_CAPABILITIES as $capability) {
            $this->assertNotContains($capability, $prohibited);
        }
        $this->assertContains('mod/quiz:addinstance', $prohibited);
    }

    /**
     * Roles an admin named with the plugin's prefix, but not exactly as a stage role, are never
     * deleted by sync or uninstall.
     */
    public function test_foreign_prefixed_roles_are_untouched(): void {
        global $DB;
        $this->resetAfterTest();
        $custom = create_role('Custom', 'teacherscaffoldtier1custom', '');
        $extra = create_role('Extra', 'teacherscaffoldtierextra', '');
        role_manager::sync();
        $this->assertTrue($DB->record_exists('role', ['id' => $custom]));
        $this->assertTrue($DB->record_exists('role', ['id' => $extra]));

        role_manager::delete_all_roles();
        $this->assertTrue($DB->record_exists('role', ['id' => $custom]));
        $this->assertTrue($DB->record_exists('role', ['id' => $extra]));
        $this->assertFalse($DB->record_exists('role', ['shortname' => 'teacherscaffoldtier1']));
    }

    /**
     * Saving the settings through the admin framework runs the registered callbacks
     * (settings.php wiring): new stages rebuild the roles, the master switch lifts restrictions.
     */
    public function test_settings_save_runs_callbacks(): void {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        $this->resetAfterTest();
        $this->setAdminUser();
        role_manager::sync();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        tracker::track($teacher->id);

        admin_write_settings((object)['s_tool_teacherscaffold_tiers' => "page, forum\nquiz"]);
        $this->assertSame([1, 2], array_keys(role_manager::role_ids()));
        $this->assertFalse(course_allowed_module($course, 'resource', $teacher));

        admin_write_settings((object)['s_tool_teacherscaffold_enabled' => '0']);
        $this->assertTrue(course_allowed_module($course, 'resource', $teacher));
    }

    /**
     * Deleting a stage role by hand makes the next page view rebuild it.
     */
    public function test_deleted_stage_role_is_rebuilt(): void {
        global $DB;
        $this->resetAfterTest();
        // The role_deleted observer is not internal: see tracker_test::setUp().
        $this->preventResetByRollback();
        role_manager::sync();
        $old = role_manager::role_id(1);
        $this->assertFalse(role_manager::needs_sync());

        delete_role($old);
        $this->assertTrue(role_manager::needs_sync());
        role_manager::sync_if_needed();
        $new = role_manager::role_id(1);
        $this->assertNotEquals($old, $new);
        $this->assertTrue($DB->record_exists('role', ['id' => $new, 'shortname' => 'teacherscaffoldtier1']));
    }

    /**
     * Uninstalling deletes the stage roles and the opt-out preference, and nothing else.
     */
    public function test_uninstall_cleanup(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/admin/tool/teacherscaffold/db/uninstall.php');
        $this->resetAfterTest();
        role_manager::sync();
        $teacher = $this->getDataGenerator()->create_user();
        tracker::track($teacher->id);
        tracker::opt_out($teacher->id);
        set_user_preference('some_other_preference', 1, $teacher->id);

        $this->assertTrue(xmldb_tool_teacherscaffold_uninstall());

        $like = $DB->sql_like('shortname', ':prefix');
        $this->assertSame(0, $DB->count_records_select('role', $like, ['prefix' => 'teacherscaffoldtier%']));
        $this->assertFalse($DB->record_exists('user_preferences', ['name' => tracker::PREF_OPTEDOUT]));
        $this->assertTrue($DB->record_exists('user_preferences', ['name' => 'some_other_preference']));
    }
}
