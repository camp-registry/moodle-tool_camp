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
 * English strings for the camp client.
 *
 * @package    tool_camp
 * @copyright  2026 the camp project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'CAMP plugin repository';
$string['browsetitle'] = 'Browse CAMP plugins';
$string['cachedef_packages'] = 'CAMP repository metadata';
$string['repourl'] = 'Repository URL';
$string['repourl_desc'] = 'Base URL of the CAMP repository (or any mirror — artifacts are hash-verified, so mirrors need not be trusted).';
$string['mintier'] = 'Minimum trust tier';
$string['mintier_desc'] = 'Tier 2 plugins are automatically verified to match their public source. Tier 3 plugins have additionally been reviewed by two independent community reviewers. (Tier 0 discovered and Tier 1 claimed listings have no verified releases and are never installable.)';
$string['tier2'] = 'Tier 2 — source-verified';
$string['tier3'] = 'Tier 3 — human-reviewed';
$string['cooldown'] = 'Release cooldown';
$string['cooldown_desc'] = 'Only offer releases older than this. Malicious releases are typically detected and revoked within hours of publication; a cooldown lets this site sit out the risky window. Zero disables the cooldown.';
$string['installplugin'] = 'Install {$a}';
$string['confirminstall'] = 'Install {$a->component} version {$a->version}? The download will be verified against the repository\'s published SHA-256 before any file is written: {$a->shasum}';
$string['installok'] = 'Plugin files deployed and verified. Continue to the upgrade page to complete installation.';
$string['searchplugins'] = 'Search plugins';
$string['noplugins'] = 'No installable plugins match the current policy (check the repository URL, minimum tier, and cooldown settings).';
$string['colplugin'] = 'Plugin';
$string['colversion'] = 'Newest allowed version';
$string['coltier'] = 'Tier';
$string['collabels'] = 'Disclosure';
$string['colmoodle'] = 'Moodle';
$string['colaction'] = 'Action';
$string['errornorepo'] = 'The repository at {$a} could not be fetched.';
$string['errorbadmetadata'] = 'The repository metadata is malformed.';
$string['errorunknownpackage'] = 'Package {$a} is not in the repository (or is excluded by policy).';
$string['errorhash'] = 'SECURITY: the downloaded archive does not match the published SHA-256. Nothing was installed. If this repeats, the repository or mirror may be compromised — please report it.';
$string['errornotwritable'] = 'The plugin directory {$a} is not writable by the web server, so installation from the web is not possible on this site.';
$string['errorunsupportedtype'] = 'Plugin type {$a} is not supported by this Moodle version.';
$string['privacy:metadata'] = 'The CAMP client only downloads public repository metadata and plugin packages. It stores no personal data and sends none: requests to the repository carry no user or site identifiers.';
