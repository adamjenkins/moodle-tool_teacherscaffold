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

namespace tool_teacherscaffold\admin;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

use html_writer;
use tool_teacherscaffold\local\tier_config;

/**
 * Admin setting for the stages: edited as one line per stage, stored as JSON.
 *
 * @package    tool_teacherscaffold
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_tiers extends \admin_setting_configtextarea {

    /**
     * Constructor.
     *
     * @param string $name Setting name.
     * @param string $visiblename Label.
     * @param string $description Description.
     */
    public function __construct($name, $visiblename, $description) {
        parent::__construct($name, $visiblename, $description, tier_config::to_text(tier_config::DEFAULT_TIERS),
            PARAM_RAW, 60, 6);
    }

    /**
     * Return the stored stages as editable text.
     *
     * @return string|null
     */
    public function get_setting() {
        $json = $this->config_read($this->name);
        if ($json === null) {
            return null;
        }
        $tiers = json_decode($json, true);
        return is_array($tiers) ? tier_config::to_text($tiers) : '';
    }

    /**
     * Validate the text and store it as JSON.
     *
     * @param string $data The submitted text.
     * @return string Empty on success, otherwise an error message.
     */
    public function write_setting($data) {
        global $DB;
        $installed = $DB->get_fieldset_select('modules', 'name', '1 = 1');
        $tiers = tier_config::parse_text((string)$data, tier_config::site_lockable_modules(), $installed);
        if (is_string($tiers)) {
            return $tiers;
        }
        return $this->config_write($this->name, json_encode($tiers)) ? '' : get_string('errorsetting', 'admin');
    }

    /**
     * Render the textarea followed by a preview of what each stage contains.
     *
     * @param mixed $data Current value.
     * @param string $query Admin search query.
     * @return string
     */
    public function output_html($data, $query = '') {
        $description = $this->description;
        $this->description .= $this->preview_html();
        $html = parent::output_html($data, $query);
        $this->description = $description;
        return $html;
    }

    /**
     * A list of each stage's modules as the site currently sees them.
     *
     * @return string
     */
    protected function preview_html(): string {
        $config = new tier_config();
        $items = [];
        for ($tier = 1; $tier <= $config->final_tier(); $tier++) {
            $label = $tier === $config->final_tier() ? get_string('finaltier', 'tool_teacherscaffold')
                : get_string('tier', 'tool_teacherscaffold', $tier);
            $item = html_writer::tag('strong', s($label)) . ': ' .
                s(tier_config::module_names($config->unlocked_by_reaching($tier)));
            if ($tier < $config->final_tier()) {
                $missing = array_diff($config->get_tiers()[$tier - 1], $config->get_lockable());
                if ($missing) {
                    $item .= ' ' . html_writer::span(s(get_string('modulesnotinstalled', 'tool_teacherscaffold',
                        implode(', ', $missing))), 'text-muted');
                }
            }
            $items[] = $item;
        }
        return html_writer::alist($items);
    }
}
