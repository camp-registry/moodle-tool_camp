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

$string['advisoryemailintro'] = 'The CAMP plugin repository has published security advisories that affect plugins installed on this site. This site checked the public advisory feed and matched it against its own installed plugins; no information about this site was sent anywhere.';
$string['advisoryemailline'] = '{$a->severity}: {$a->component} {$a->release} — {$a->title} ({$a->id}, published by repository "{$a->repo}"; affects {$a->affected}). Details: {$a->link}';
$string['advisoryemailoutro'] = 'Review each advisory and update or disable the affected plugins. You will not be emailed again about these advisories.';
$string['advisoryemailsubject'] = 'Security advisories affect {$a->count} installed plugin(s) on {$a->site}';
$string['alsoavailablefrom'] = 'also in: {$a}';
$string['browsetitle'] = 'Browse CAMP plugins';
$string['cachedef_packages'] = 'CAMP repository metadata';
$string['colaction'] = 'Action';
$string['collabels'] = 'Disclosure';
$string['colmoodle'] = 'Moodle';
$string['colplugin'] = 'Plugin';
$string['colsource'] = 'Source';
$string['coltier'] = 'Tier';
$string['colversion'] = 'Newest allowed version';
$string['confirminstall'] = 'Install {$a->component} version {$a->version}? The download will be verified against the repository\'s published SHA-256 before any file is written: {$a->shasum}';
$string['cooldown'] = 'Release cooldown';
$string['cooldown_desc'] = 'Only offer releases older than this. Malicious releases are typically detected and revoked within hours of publication; a cooldown lets this site sit out the risky window. Zero disables the cooldown.';
$string['errorbadmetadata'] = 'The repository metadata is malformed.';
$string['errorhash'] = 'SECURITY: the downloaded archive does not match the published SHA-256. Nothing was installed. If this repeats, the repository or mirror may be compromised — please report it.';
$string['errornorepo'] = 'The repository at {$a} could not be fetched.';
$string['errornotwritable'] = 'The plugin directory {$a} is not writable by the web server, so installation from the web is not possible on this site.';
$string['errorunknownpackage'] = 'Package {$a} is not in the repository (or is excluded by policy).';
$string['errorunsupportedtype'] = 'Plugin type {$a} is not supported by this Moodle version.';
$string['installok'] = 'Plugin files deployed and verified. Continue to the upgrade page to complete installation.';
$string['installplugin'] = 'Install {$a}';
$string['maturity_alpha'] = 'Everything, including alpha';
$string['maturity_beta'] = 'Beta, release candidates and stable';
$string['maturity_rc'] = 'Release candidates and stable';
$string['maturity_stable'] = 'Stable only (default)';
$string['minstability'] = 'Minimum release maturity';
$string['minstability_desc'] = 'Plugin authors mark releases as stable, release candidate, beta or alpha. Only releases at this maturity or above are offered; the default offers stable releases only. A repository line may override this with <code>|minstability=…</code>, for example a staging repository that should offer betas.';
$string['mintier'] = 'Minimum trust tier';
$string['mintier_desc'] = 'Tier 2 plugins are automatically verified to match their public source. Tier 3 plugins have additionally been reviewed by two independent community reviewers. (Tier 0 discovered and Tier 1 claimed listings have no verified releases and are never installable.)';
$string['movedto'] = 'moved — new versions publish at {$a}';
$string['noplugins'] = 'No installable plugins match the current policy (check the repository list, minimum tier, minimum release maturity, and cooldown settings).';
$string['pluginname'] = 'CAMP plugin repository';
$string['prerelease'] = 'pre-release ({$a})';
$string['privacy:metadata'] = 'The CAMP client only downloads public repository metadata and plugin packages. It stores no personal data and sends none: requests to the repository carry no user or site identifiers.';
$string['repos'] = 'Repositories';
$string['repos_desc'] = 'One repository per line, highest priority first: <code>name|https://url</code>, with optional fields <code>|token=…</code> (access token for commercial repositories), <code>|mintier=N</code> (per-repository minimum tier, overriding the site default below) and <code>|minstability=…</code> (per-repository minimum release maturity, overriding the site default below). Lines starting with # are ignored. When several repositories offer the same plugin, the highest-priority one supplies it; a plugin already installed from a repository is only ever updated from that same repository. Mirrors need not be trusted — artifacts are hash-verified.';
$string['searchplugins'] = 'Search plugins';
$string['taskcheckadvisories'] = 'Check security advisories';
$string['tier2'] = 'Tier 2 — source-verified';
$string['tier3'] = 'Tier 3 — human-reviewed';
