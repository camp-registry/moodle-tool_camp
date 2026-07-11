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
 * Browse installable plugins from the configured camp repository.
 *
 * @package    tool_camp
 * @copyright  2026 the camp project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('toolcampbrowse');

$search = optional_param('search', '', PARAM_RAW_TRIMMED);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('browsetitle', 'tool_camp'));

$installable = \tool_camp\local\repository::get_installable();

if ($search !== '') {
    $needle = \core_text::strtolower($search);
    $installable = array_filter($installable, function ($definition, $name) use ($needle) {
        $component = $definition['extra']['camp']['component'] ?? '';
        return strpos(\core_text::strtolower($name . ' ' . $component), $needle) !== false;
    }, ARRAY_FILTER_USE_BOTH);
}

echo html_writer::start_tag('form', ['method' => 'get', 'action' => new moodle_url('/admin/tool/camp/index.php')]);
echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'search',
    'value' => $search, 'placeholder' => get_string('searchplugins', 'tool_camp'),
    'class' => 'form-control d-inline-block w-auto me-2']);
echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-secondary',
    'value' => get_string('search')]);
echo html_writer::end_tag('form');

if (empty($installable)) {
    echo $OUTPUT->notification(get_string('noplugins', 'tool_camp'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('colplugin', 'tool_camp'),
        get_string('colversion', 'tool_camp'),
        get_string('coltier', 'tool_camp'),
        get_string('collabels', 'tool_camp'),
        get_string('colmoodle', 'tool_camp'),
        get_string('colaction', 'tool_camp'),
    ];
    foreach ($installable as $name => $definition) {
        $camp = $definition['extra']['camp'];
        $installurl = new moodle_url(
            '/admin/tool/camp/install.php',
            ['package' => $name, 'sesskey' => sesskey()]
        );
        $supported = $camp['supported-moodle'];
        $table->data[] = [
            html_writer::tag('strong', s($camp['component'])) . html_writer::empty_tag('br')
                . html_writer::tag('small', s($name)),
            s($definition['version']),
            'Tier ' . (int) $camp['tier'],
            s(implode(', ', $camp['labels'] ?? [])),
            s(reset($supported) . ' – ' . end($supported)),
            html_writer::link(
                $installurl,
                get_string('installplugin', 'tool_camp', s($definition['version'])),
                ['class' => 'btn btn-primary btn-sm']
            ),
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
