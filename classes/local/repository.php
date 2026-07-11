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
 * Read-only view of a camp repository, filtered by this site's policy.
 *
 * Consumes the repository's Composer metadata (packages.json), which carries
 * camp-specific data (tier, disclosure labels, supported Moodle branches,
 * publication time) under each version's extra.camp key. All artifact
 * downloads are verified against the published SHA-256 by {@see installer},
 * so the transport and any mirror are untrusted.
 *
 * Privacy: requests carry no site or user identifiers (RFC §4.6).
 *
 * @package    tool_camp
 * @copyright  2026 the camp project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository {

    /**
     * Fetch and cache the repository's package metadata.
     *
     * @return array package name => (version => definition)
     */
    public static function get_packages(): array {
        $repourl = self::get_repourl();
        $cache = \cache::make('tool_camp', 'packages');
        $cachekey = 'packages_' . sha1($repourl);

        $packages = $cache->get($cachekey);
        if ($packages === false) {
            $json = download_file_content($repourl . '/packages.json');
            if ($json === false) {
                throw new \moodle_exception('errornorepo', 'tool_camp', '', $repourl);
            }
            $decoded = json_decode($json, true);
            if (!is_array($decoded) || !isset($decoded['packages']) || !is_array($decoded['packages'])) {
                throw new \moodle_exception('errorbadmetadata', 'tool_camp');
            }
            $packages = $decoded['packages'];
            $cache->set($cachekey, $packages);
        }
        return $packages;
    }

    /**
     * The newest version of each package that this site's policy allows.
     *
     * Policy filters: minimum trust tier, release cooldown, and Moodle
     * version support (the current branch must be listed).
     *
     * @return array package name => version definition (with 'version' key)
     */
    public static function get_installable(): array {
        $mintier = (int) get_config('tool_camp', 'mintier');
        $cooldown = (int) get_config('tool_camp', 'cooldown');
        $branch = self::current_branch();

        $installable = [];
        foreach (self::get_packages() as $name => $versions) {
            $best = null;
            foreach ($versions as $definition) {
                $camp = $definition['extra']['camp'] ?? null;
                if ($camp === null) {
                    continue;
                }
                if ((int) ($camp['tier'] ?? 0) < $mintier) {
                    continue;
                }
                if (!in_array($branch, $camp['supported-moodle'] ?? [], true)) {
                    continue;
                }
                if ($cooldown > 0) {
                    $published = strtotime($camp['published'] ?? '') ?: 0;
                    if ($published + $cooldown > time()) {
                        continue;
                    }
                }
                if (empty($definition['dist']['url']) || empty($definition['dist']['shasum'])) {
                    continue;
                }
                if ($best === null || version_compare($definition['version'], $best['version'], '>')) {
                    $best = $definition;
                }
            }
            if ($best !== null) {
                $installable[$name] = $best;
            }
        }
        ksort($installable);
        return $installable;
    }

    /**
     * Look up one policy-allowed package by name.
     *
     * @param string $name Composer package name
     * @return array version definition
     */
    public static function get_package(string $name): array {
        $installable = self::get_installable();
        if (!isset($installable[$name])) {
            throw new \moodle_exception('errorunknownpackage', 'tool_camp', '', s($name));
        }
        return $installable[$name];
    }

    /**
     * The configured repository base URL (https enforced).
     *
     * @return string
     */
    public static function get_repourl(): string {
        $repourl = rtrim((string) get_config('tool_camp', 'repourl'), '/');
        if ($repourl === '' || strpos($repourl, 'https://') !== 0) {
            throw new \moodle_exception('errornorepo', 'tool_camp', '', $repourl);
        }
        return $repourl;
    }

    /**
     * This site's Moodle branch in camp notation, e.g. branch 405 => "4.5",
     * 311 => "3.11", 502 => "5.2".
     *
     * @return string
     */
    public static function current_branch(): string {
        global $CFG;
        if (!preg_match('/^(\d+)(\d{2})$/', (string) $CFG->branch, $matches)) {
            return (string) $CFG->branch;
        }
        return $matches[1] . '.' . (int) $matches[2];
    }
}
