<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Admin settings and navigation for the camp client.
 *
 * @package    tool_camp
 * @copyright  2026 the camp project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('tools', new admin_externalpage(
        'toolcampbrowse',
        get_string('browsetitle', 'tool_camp'),
        new moodle_url('/admin/tool/camp/index.php')
    ));

    $settings = new admin_settingpage('toolcampsettings', get_string('pluginname', 'tool_camp'));
    $ADMIN->add('tools', $settings);

    $settings->add(new admin_setting_configtextarea(
        'tool_camp/repos',
        get_string('repos', 'tool_camp'),
        get_string('repos_desc', 'tool_camp'),
        '',
        PARAM_RAW
    ));

    $settings->add(new admin_setting_configselect(
        'tool_camp/mintier',
        get_string('mintier', 'tool_camp'),
        get_string('mintier_desc', 'tool_camp'),
        2,
        [
            2 => get_string('tier2', 'tool_camp'),
            3 => get_string('tier3', 'tool_camp'),
        ]
    ));

    $settings->add(new admin_setting_configduration(
        'tool_camp/cooldown',
        get_string('cooldown', 'tool_camp'),
        get_string('cooldown_desc', 'tool_camp'),
        0,
        DAYSECS
    ));
}
