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
use tool_teacherscaffold\event\tier_unlocked;
use tool_teacherscaffold\local\progress;
use tool_teacherscaffold\local\role_manager;
use tool_teacherscaffold\local\tracker;

/**
 * Tests for tracking, unlocking, opting out and the tier_unlocked event.
 *
 * @package    tool_teacherscaffold
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(tracker::class)]
#[CoversClass(progress::class)]
#[CoversClass(observer::class)]
#[CoversClass(tier_unlocked::class)]
#[CoversClass(hook_callbacks::class)]
final class tracker_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    protected $course;

    /** @var \stdClass Guided teacher. */
    protected $teacher;

    /** @var \phpunit_event_sink|null Event sink, when capturing. */
    protected $sink = null;

    /** @var int Events in the sink already delivered to the observer. */
    protected $delivered = 0;

    /**
     * Load course/lib.php for course_allowed_module().
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        parent::setUpBeforeClass();
    }

    /**
     * A course with one guided editing teacher at stage 1.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        role_manager::sync();
        $this->course = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->assertTrue(tracker::track($this->teacher->id));
        // So that the congratulation branch is deterministic whether or not tool_wizards is installed.
        set_config('enabled', 0, 'tool_wizards');
    }

    /**
     * Add a module as the teacher, which fires course_module_created with them as the actor.
     *
     * @param string $modname Module name.
     */
    protected function add(string $modname): void {
        $this->setUser($this->teacher);
        $this->getDataGenerator()->create_module($modname, ['course' => $this->course->id]);
        $this->setAdminUser();
        // A sink stops events reaching observers, so deliver course_module_created by hand.
        if ($this->sink) {
            $events = $this->sink->get_events();
            foreach (array_slice($events, $this->delivered) as $event) {
                if ($event instanceof \core\event\course_module_created) {
                    observer::course_module_created($event);
                }
            }
            $this->delivered = count($this->sink->get_events());
        }
    }

    /**
     * Start capturing events; add() still delivers module creations to the observer.
     *
     * @return \phpunit_event_sink
     */
    protected function sink(): \phpunit_event_sink {
        $this->sink = $this->redirectEvents();
        $this->delivered = 0;
        return $this->sink;
    }

    /**
     * The tier_unlocked events in a sink.
     *
     * @param \phpunit_event_sink $sink Sink.
     * @return tier_unlocked[]
     */
    protected function unlock_events(\phpunit_event_sink $sink): array {
        return array_values(array_filter($sink->get_events(), fn($e) => $e instanceof tier_unlocked));
    }

    /**
     * "Try each" rule: using every stage-1 module unlocks stage 2, fires the event exactly as
     * the contract says, and swaps the role.
     */
    public function test_tryeach_unlocks_next_stage(): void {
        $sink = $this->sink();
        $this->add('resource');
        $this->add('page');
        $this->add('forum');
        $this->add('forum');
        $this->assertEquals(1, tracker::get_record($this->teacher->id)->tier, 'label not tried yet');
        $this->assertFalse(course_allowed_module($this->course, 'url', $this->teacher));

        $this->add('label');

        $record = tracker::get_record($this->teacher->id);
        $this->assertEquals(2, $record->tier);
        $this->assertSame(tracker::STATUS_ACTIVE, $record->status);
        $this->assertTrue(course_allowed_module($this->course, 'url', $this->teacher));
        $this->assertFalse(course_allowed_module($this->course, 'quiz', $this->teacher));
        $sys = context_system::instance()->id;
        $this->assertTrue(user_has_role_assignment($this->teacher->id, role_manager::role_id(2), $sys));
        $this->assertFalse(user_has_role_assignment($this->teacher->id, role_manager::role_id(1), $sys));

        $events = $this->unlock_events($sink);
        $this->assertCount(1, $events);
        $data = $events[0]->get_data();
        $this->assertSame('\\tool_teacherscaffold\\event\\tier_unlocked', $data['eventname']);
        $this->assertEquals(CONTEXT_SYSTEM, $data['contextlevel']);
        $this->assertEquals($this->teacher->id, $data['relateduserid']);
        $this->assertSame(['tier' => 2, 'unlockedmodules' => ['url', 'folder', 'assign', 'glossary']], $data['other']);
        $this->assertEqualsCanonicalizing(['tier', 'unlockedmodules'], array_keys($data['other']));
    }

    /**
     * "Add N" rule: only activities of the current stage count.
     */
    public function test_addcount_unlocks_after_n(): void {
        set_config('unlockrule', progress::RULE_ADDCOUNT, 'tool_teacherscaffold');
        set_config('unlockcount', 2, 'tool_teacherscaffold');
        $this->add('quiz');
        $this->assertEquals(0, tracker::get_record($this->teacher->id)->tieradds, 'quiz is not a stage-1 module');
        $this->add('forum');
        $this->assertEquals(1, tracker::get_record($this->teacher->id)->tier);
        $this->add('forum');
        $record = tracker::get_record($this->teacher->id);
        $this->assertEquals(2, $record->tier);
        $this->assertEquals(0, $record->tieradds);
    }

    /**
     * Completing the last listed stage unlocks everything: no role, graduated, and the event lists
     * the final stage's modules explicitly.
     */
    public function test_last_stage_graduates(): void {
        tracker::advance($this->teacher->id);
        tracker::advance($this->teacher->id);
        $sink = $this->sink();
        $this->add('quiz');
        $this->add('choice');
        $this->add('feedback');

        $record = tracker::get_record($this->teacher->id);
        $this->assertSame(tracker::STATUS_GRADUATED, $record->status);
        $this->assertEquals(4, $record->tier);
        $this->assertTrue(course_allowed_module($this->course, 'book', $this->teacher));
        $events = $this->unlock_events($sink);
        $this->assertCount(1, $events);
        $this->assertSame(4, $events[0]->other['tier']);
        $this->assertContains('book', $events[0]->other['unlockedmodules']);
        $this->assertNotContains('quiz', $events[0]->other['unlockedmodules']);
        $this->assertNotContains('qbank', $events[0]->other['unlockedmodules']);
    }

    /**
     * The congratulation appears only when tool_wizards is not active, and only for an earned unlock.
     */
    public function test_congratulation_only_without_wizards(): void {
        \core\notification::fetch();
        tracker::advance($this->teacher->id);
        $this->assertCount(0, \core\notification::fetch(), 'an admin unlock is not congratulated');

        foreach (['resource', 'page', 'forum', 'url', 'folder', 'assign'] as $modname) {
            $this->add($modname);
        }
        \core\notification::fetch();
        $this->add('glossary');
        $messages = array_map(fn($n) => $n->get_message(), \core\notification::fetch());
        $this->assertContains(get_string('unlocked', 'tool_teacherscaffold', 'Quiz, Choice, Feedback'), $messages);
        $this->assertFalse(tracker::wizards_active());
    }

    /**
     * With tool_wizards installed and enabled, the unlock is left to it: no congratulation here,
     * but the event still fires.
     */
    public function test_no_congratulation_when_wizards_active(): void {
        $info = \core_plugin_manager::instance()->get_plugin_info('tool_wizards');
        if (!$info || empty($info->versiondb)) {
            $this->markTestSkipped('tool_wizards is not installed on this site.');
        }
        set_config('enabled', 1, 'tool_wizards');
        $this->assertTrue(tracker::wizards_active());

        foreach (['resource', 'page', 'forum'] as $modname) {
            $this->add($modname);
        }
        \core\notification::fetch();
        $sink = $this->sink();
        $this->add('label');
        $this->assertCount(0, \core\notification::fetch());
        $this->assertCount(1, $this->unlock_events($sink));
    }

    /**
     * Opting out removes the restriction and stores the preference; opting in restores both.
     */
    public function test_opt_out_and_back_in(): void {
        global $DB;
        $this->assertFalse(course_allowed_module($this->course, 'quiz', $this->teacher));

        $this->assertTrue(tracker::opt_out($this->teacher->id));
        $this->assertTrue(course_allowed_module($this->course, 'quiz', $this->teacher));
        $this->assertFalse($DB->record_exists(
            'role_assignments',
            ['userid' => $this->teacher->id, 'component' => tracker::COMPONENT]
        ));
        $this->assertEquals(1, get_user_preferences(tracker::PREF_OPTEDOUT, null, $this->teacher->id));

        // The notice is not shown to someone who opted out, and module use no longer counts.
        $this->add('forum');
        $this->assertFalse($DB->record_exists('tool_teacherscaffold_usage', ['userid' => $this->teacher->id]));

        $this->assertTrue(tracker::opt_in($this->teacher->id));
        $this->assertFalse(course_allowed_module($this->course, 'quiz', $this->teacher));
        $this->assertNull(get_user_preferences(tracker::PREF_OPTEDOUT, null, $this->teacher->id));
    }

    /**
     * The master switch removes every restriction and puts them back.
     */
    public function test_master_switch(): void {
        set_config('enabled', 0, 'tool_teacherscaffold');
        tracker::reconcile_all();
        $this->assertTrue(course_allowed_module($this->course, 'quiz', $this->teacher));
        set_config('enabled', 1, 'tool_teacherscaffold');
        tracker::reconcile_all();
        $this->assertFalse(course_allowed_module($this->course, 'quiz', $this->teacher));
    }

    /**
     * Site admins are never tracked; opted-out users are not re-tracked; reset starts again.
     */
    public function test_tracking_rules(): void {
        $this->assertFalse(tracker::track(get_admin()->id));
        tracker::opt_out($this->teacher->id);
        $this->assertFalse(tracker::track($this->teacher->id));
        $this->assertSame(tracker::STATUS_OPTEDOUT, tracker::get_record($this->teacher->id)->status);

        $this->add('forum');
        $this->assertTrue(tracker::reset($this->teacher->id));
        $record = tracker::get_record($this->teacher->id);
        $this->assertSame(tracker::STATUS_ACTIVE, $record->status);
        $this->assertEquals(1, $record->tier);
        $this->assertFalse(course_allowed_module($this->course, 'quiz', $this->teacher));

        $this->assertTrue(tracker::graduate($this->teacher->id));
        $this->assertTrue(course_allowed_module($this->course, 'quiz', $this->teacher));
    }

    /**
     * Automatic tracking picks up a first-time editing teacher only, and only when switched on.
     */
    public function test_autotrack_first_time_teacher(): void {
        $gen = $this->getDataGenerator();
        $other = $gen->create_course();
        $newteacher = $gen->create_user();
        $gen->enrol_user($newteacher->id, $this->course->id, 'editingteacher');
        $this->assertNull(tracker::get_record($newteacher->id), 'off by default');

        set_config('autotrack', 1, 'tool_teacherscaffold');
        $experienced = $gen->create_user();
        $gen->enrol_user($experienced->id, $other->id, 'editingteacher');
        $this->assertNotNull(tracker::get_record($experienced->id), 'first editing teacher role');

        $gen->enrol_user($newteacher->id, $other->id, 'editingteacher');
        $this->assertNull(tracker::get_record($newteacher->id), 'already an editing teacher elsewhere');

        $student = $gen->create_user();
        $gen->enrol_user($student->id, $other->id, 'student');
        $this->assertNull(tracker::get_record($student->id));
    }

    /**
     * Members of a configured cohort are tracked, including later joiners.
     */
    public function test_cohort_tracking(): void {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');
        $gen = $this->getDataGenerator();
        $cohort = $gen->create_cohort();
        $early = $gen->create_user();
        cohort_add_member($cohort->id, $early->id);
        set_config('cohorts', (string)$cohort->id, 'tool_teacherscaffold');

        $this->assertSame(1, tracker::sync_cohorts());
        $this->assertNotNull(tracker::get_record($early->id));

        $late = $gen->create_user();
        cohort_add_member($cohort->id, $late->id);
        $this->assertNotNull(tracker::get_record($late->id));
    }

    /**
     * Deleting a user deletes their data.
     */
    public function test_user_deleted(): void {
        global $DB;
        $this->add('forum');
        delete_user($this->teacher);
        $this->assertFalse($DB->record_exists('tool_teacherscaffold_user', ['userid' => $this->teacher->id]));
        $this->assertFalse($DB->record_exists('tool_teacherscaffold_usage', ['userid' => $this->teacher->id]));
    }

    /**
     * The progress notice counts the stage and links to the opt-out page.
     */
    public function test_progress_notice(): void {
        $this->add('forum');
        $record = tracker::get_record($this->teacher->id);
        $html = hook_callbacks::progress_notice_html(
            $record,
            new \moodle_url('/course/view.php', ['id' => $this->course->id])
        );
        $this->assertStringContainsString(s(get_string(
            'noticeprogresstry',
            'tool_teacherscaffold',
            (object)['done' => 1, 'total' => 4]
        )), $html);
        $this->assertStringContainsString('/admin/tool/teacherscaffold/optout.php', $html);
        $this->assertStringContainsString(get_string('noticeoptout', 'tool_teacherscaffold'), $html);
    }

    /**
     * The event rejects data that breaks the contract.
     */
    public function test_event_validation(): void {
        $this->expectException(\coding_exception::class);
        tier_unlocked::create([
            'context' => \context_course::instance($this->course->id),
            'relateduserid' => $this->teacher->id,
            'other' => ['tier' => 2, 'unlockedmodules' => []],
        ]);
    }
}
