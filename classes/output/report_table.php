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

namespace tool_teacherscaffold\output;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

use html_writer;
use moodle_url;
use tool_teacherscaffold\local\progress;
use tool_teacherscaffold\local\tier_config;
use tool_teacherscaffold\local\tracker;

/**
 * Table of guided teachers on the report page.
 *
 * @package    tool_teacherscaffold
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_table extends \table_sql {
    /** @var tier_config Stage configuration. */
    protected tier_config $config;

    /**
     * Constructor.
     *
     * @param moodle_url $baseurl Report URL.
     */
    public function __construct(moodle_url $baseurl) {
        parent::__construct('tool_teacherscaffold_report');
        $this->config = new tier_config();
        $this->define_baseurl($baseurl);
        $this->define_columns(['fullname', 'tier', 'progress', 'status', 'lastused', 'actions']);
        $this->define_headers([
            get_string('fullname'),
            get_string('stage', 'tool_teacherscaffold'),
            get_string('progress', 'tool_teacherscaffold'),
            get_string('status', 'tool_teacherscaffold'),
            get_string('lastactivity', 'tool_teacherscaffold'),
            get_string('action', 'tool_teacherscaffold'),
        ]);
        $this->no_sorting('progress');
        $this->no_sorting('actions');
        $this->sortable(true, 'lastname');
        $this->collapsible(false);

        $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $this->set_sql(
            "t.id, t.userid, t.tier, t.status, t.tieradds, t.timemodified, $namefields,
             (SELECT MAX(us.timefirstused) FROM {tool_teacherscaffold_usage} us WHERE us.userid = t.userid) AS lastused",
            "{tool_teacherscaffold_user} t JOIN {user} u ON u.id = t.userid AND u.deleted = 0",
            '1 = 1'
        );
    }

    /**
     * Name column.
     *
     * @param \stdClass $row Row.
     * @return string
     */
    public function col_fullname($row) {
        return html_writer::link(new moodle_url('/user/profile.php', ['id' => $row->userid]), s(fullname($row)));
    }

    /**
     * Stage column.
     *
     * @param \stdClass $row Row.
     * @return string
     */
    public function col_tier($row) {
        return s(get_string(
            'stagevalue',
            'tool_teacherscaffold',
            (object)['tier' => min((int)$row->tier, $this->config->final_tier()), 'total' => $this->config->final_tier()]
        ));
    }

    /**
     * Progress column.
     *
     * @param \stdClass $row Row.
     * @return string
     */
    public function col_progress($row) {
        if ($row->status !== tracker::STATUS_ACTIVE) {
            return '-';
        }
        return s(get_string('progressvalue', 'tool_teacherscaffold', (object)progress::for_user($row, $this->config)));
    }

    /**
     * Status column.
     *
     * @param \stdClass $row Row.
     * @return string
     */
    public function col_status($row) {
        return s(get_string('status' . $row->status, 'tool_teacherscaffold'));
    }

    /**
     * Last activity column.
     *
     * @param \stdClass $row Row.
     * @return string
     */
    public function col_lastused($row) {
        return $row->lastused ? userdate($row->lastused, get_string('strftimedatetimeshort', 'langconfig')) : '-';
    }

    /**
     * Actions column: each opens a confirmation first.
     *
     * @param \stdClass $row Row.
     * @return string
     */
    public function col_actions($row) {
        $actions = [];
        if ($row->status === tracker::STATUS_ACTIVE) {
            $actions['advance'] = 'actionadvance';
        }
        $actions['reset'] = 'actionreset';
        if ($row->status !== tracker::STATUS_GRADUATED) {
            $actions['graduate'] = 'actiongraduate';
        }
        $links = [];
        foreach ($actions as $action => $string) {
            $links[] = html_writer::link(
                new moodle_url($this->baseurl, ['action' => $action, 'userid' => $row->userid]),
                s(get_string($string, 'tool_teacherscaffold')),
                ['class' => 'me-2']
            );
        }
        return implode(' ', $links);
    }
}
