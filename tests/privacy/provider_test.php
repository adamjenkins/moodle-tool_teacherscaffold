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

namespace tool_teacherscaffold\privacy;

use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use PHPUnit\Framework\Attributes\CoversClass;
use tool_teacherscaffold\local\role_manager;
use tool_teacherscaffold\local\tracker;

/**
 * Privacy provider tests.
 *
 * @package    tool_teacherscaffold
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var \stdClass First teacher, with data. */
    protected $one;

    /** @var \stdClass Second teacher, with data. */
    protected $two;

    /** @var \stdClass User without data. */
    protected $none;

    /**
     * Two tracked teachers with usage and one opt-out; one untracked user.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        role_manager::sync();
        $gen = $this->getDataGenerator();
        $this->one = $gen->create_user();
        $this->two = $gen->create_user();
        $this->none = $gen->create_user();
        tracker::track($this->one->id);
        tracker::track($this->two->id);
        tracker::opt_out($this->one->id);
        foreach ([$this->one, $this->two] as $user) {
            $DB->insert_record(
                'tool_teacherscaffold_usage',
                (object)['userid' => $user->id, 'modname' => 'forum', 'timefirstused' => time()]
            );
        }
    }

    /**
     * Every table, the preference and the role link are described.
     */
    public function test_get_metadata(): void {
        $items = provider::get_metadata(new collection('tool_teacherscaffold'))->get_collection();
        $names = array_map(fn($item) => $item->get_name(), $items);
        $this->assertEqualsCanonicalizing(['tool_teacherscaffold_user', 'tool_teacherscaffold_usage',
            tracker::PREF_OPTEDOUT, 'core_role'], $names);
    }

    /**
     * Contexts and users are found at system context only for users with data.
     */
    public function test_contexts_and_users(): void {
        $this->assertEquals(
            [context_system::instance()->id],
            provider::get_contexts_for_userid($this->one->id)->get_contextids()
        );
        $this->assertEmpty(provider::get_contexts_for_userid($this->none->id)->get_contextids());

        $userlist = new userlist(context_system::instance(), 'tool_teacherscaffold');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$this->one->id, $this->two->id], $userlist->get_userids());
    }

    /**
     * Export includes the stage, status, first uses and the preference.
     */
    public function test_export(): void {
        $context = context_system::instance();
        $this->export_context_data_for_user($this->one->id, $context, 'tool_teacherscaffold');
        $data = writer::with_context($context)->get_data([get_string('pluginname', 'tool_teacherscaffold')]);
        $this->assertSame(tracker::STATUS_OPTEDOUT, $data->status);
        $this->assertSame(1, $data->tier);
        $this->assertSame('forum', $data->usage[0]->modname);

        provider::export_user_preferences($this->one->id);
        $pref = writer::with_context($context)->get_user_preferences('tool_teacherscaffold');
        $this->assertObjectHasProperty(tracker::PREF_OPTEDOUT, $pref);
    }

    /**
     * Deleting one user removes their rows and role, and leaves others alone.
     */
    public function test_delete_for_user(): void {
        global $DB;
        $context = context_system::instance();
        provider::delete_data_for_user(new approved_contextlist($this->two, 'tool_teacherscaffold', [$context->id]));
        $this->assertFalse($DB->record_exists('tool_teacherscaffold_user', ['userid' => $this->two->id]));
        $this->assertFalse($DB->record_exists('tool_teacherscaffold_usage', ['userid' => $this->two->id]));
        $this->assertFalse($DB->record_exists(
            'role_assignments',
            ['userid' => $this->two->id, 'component' => tracker::COMPONENT]
        ));
        $this->assertTrue($DB->record_exists('tool_teacherscaffold_user', ['userid' => $this->one->id]));
    }

    /**
     * Deleting a list of users, and then everyone.
     */
    public function test_delete_for_users_and_all(): void {
        global $DB;
        $context = context_system::instance();
        provider::delete_data_for_users(new approved_userlist($context, 'tool_teacherscaffold', [$this->one->id]));
        $this->assertFalse($DB->record_exists('tool_teacherscaffold_user', ['userid' => $this->one->id]));
        $this->assertTrue($DB->record_exists('tool_teacherscaffold_user', ['userid' => $this->two->id]));

        provider::delete_data_for_all_users_in_context($context);
        $this->assertSame(0, $DB->count_records('tool_teacherscaffold_user'));
        $this->assertSame(0, $DB->count_records('tool_teacherscaffold_usage'));
    }
}
