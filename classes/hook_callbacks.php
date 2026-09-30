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

use core\hook\output\before_http_headers;
use html_writer;
use moodle_url;
use stdClass;
use tool_teacherscaffold\local\progress;
use tool_teacherscaffold\local\role_manager;
use tool_teacherscaffold\local\tier_config;
use tool_teacherscaffold\local\tracker;

/**
 * Hook callbacks for Teacher scaffold.
 *
 * @package    tool_teacherscaffold
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Before headers: re-sync the stage roles if plugins changed, and queue the progress notice.
     *
     * The notice is queued as a session notification: header() dispatches this hook before
     * course_content_header() prints the queued notifications (lib/classes/output/core_renderer.php),
     * so it appears on the same course page.
     *
     * @param before_http_headers $hook The hook.
     */
    public static function before_http_headers(before_http_headers $hook): void {
        global $PAGE, $USER;

        role_manager::sync_if_needed();

        if (!tracker::enabled() || !isloggedin() || isguestuser()) {
            return;
        }
        if (!$PAGE->has_set_url() || !$PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)) {
            return;
        }
        if (!$PAGE->user_is_editing()) {
            return;
        }
        $record = tracker::get_record((int)$USER->id);
        if (!$record || $record->status !== tracker::STATUS_ACTIVE) {
            return;
        }
        \core\notification::add(self::progress_notice_html($record, $PAGE->url), \core\notification::INFO);
    }

    /**
     * The one-line progress notice with its "Show me everything" link.
     *
     * @param stdClass $record Row of tool_teacherscaffold_user.
     * @param moodle_url $returnurl Page to return to after opting out.
     * @return string HTML
     */
    public static function progress_notice_html(stdClass $record, moodle_url $returnurl): string {
        $counts = progress::for_user($record, new tier_config());
        $key = progress::rule() === progress::RULE_ADDCOUNT ? 'noticeprogressadd' : 'noticeprogresstry';
        $optout = new moodle_url(
            '/admin/tool/teacherscaffold/optout.php',
            ['returnurl' => $returnurl->out_as_local_url(false)]
        );
        return html_writer::span(s(get_string($key, 'tool_teacherscaffold', (object)$counts))) . ' ' .
            html_writer::link(
                $optout,
                s(get_string('noticeoptout', 'tool_teacherscaffold')),
                ['class' => 'ms-2 tool-teacherscaffold-optout']
            );
    }
}
