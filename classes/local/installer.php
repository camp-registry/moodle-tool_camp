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

namespace tool_camp\local;

/**
 * Downloads, verifies and deploys plugin packages from a camp repository.
 *
 * The security-critical ordering is: download to a request-scoped temp
 * directory, compare the file's SHA-256 against the hash published in the
 * signed repository metadata, and only then hand the archive to Moodle's
 * own code manager for deployment. A hash mismatch aborts with nothing
 * written to the plugin directories.
 *
 * Deployment reuses core's machinery (\core\update\code_manager), the same
 * code path the built-in installer uses, so directory validation and ZIP
 * handling are Moodle's, not ours.
 *
 * @package    tool_camp
 * @copyright  2026 the camp project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class installer {
    /**
     * Install the policy-allowed version of a package.
     *
     * @param string $packagename Composer package name from the repository
     * @return string the installed component name
     */
    public static function install(string $packagename): string {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        require_capability('moodle/site:config', \context_system::instance());

        $definition = repository::get_package($packagename);
        $component = $definition['extra']['camp']['component'];
        [$type, $name] = \core_component::normalize_component($component);

        $plugintypes = \core_component::get_plugin_types();
        if (!isset($plugintypes[$type])) {
            throw new \moodle_exception('errorunsupportedtype', 'tool_camp', '', s($type));
        }
        $targetdir = $plugintypes[$type];
        if (!is_writable($targetdir)) {
            throw new \moodle_exception('errornotwritable', 'tool_camp', '', s($targetdir));
        }

        $tempdir = make_request_directory();
        $zipfile = $tempdir . '/package.zip';
        if (!download_file_content($definition['dist']['url'], null, null, false, 300, 20, false, $zipfile)) {
            throw new \moodle_exception('errornorepo', 'tool_camp', '', s($definition['dist']['url']));
        }

        // The trust anchor: the artifact must match the hash the repository
        // published (and signs). A mirror or MITM cannot alter it undetected.
        if (!hash_equals($definition['dist']['shasum'], hash_file('sha256', $zipfile))) {
            @unlink($zipfile);
            throw new \moodle_exception('errorhash', 'tool_camp');
        }

        $codemanager = new \core\update\code_manager();
        $codemanager->unzip_plugin_file($zipfile, $targetdir, $name);

        purge_all_caches();
        return $component;
    }
}
