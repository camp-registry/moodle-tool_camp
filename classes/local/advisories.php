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
 * Security advisory feed: fetch, version matching, local comparison.
 *
 * Implements the client half of RFC §5.3: the site downloads the complete
 * signed advisory feed (security-advisories.json, Packagist-compatible
 * shape) and compares it locally against its own installed plugins. The
 * repository never learns what this site runs (RFC §4.6) — the fetch is
 * the same anonymous static file for every site, and all matching happens
 * here.
 *
 * Version matching mirrors tools/camp/advisory.py: a comma-separated AND
 * of >=, <=, >, <, = clauses against dotted numeric versions, with any
 * non-numeric suffix compared as a string.
 *
 * @package    tool_camp
 * @copyright  2026 the camp project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class advisories {
    /**
     * Fetch one repository's advisory feed.
     *
     * @param array $repo one entry from {@see repository::get_repos}
     * @return array advisory records keyed by Composer package name
     */
    public static function fetch(array $repo): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $json = download_file_content(
            $repo['url'] . '/security-advisories.json',
            repository::request_headers($repo)
        );
        if ($json === false) {
            throw new \moodle_exception('errornorepo', 'tool_camp', '', $repo['url']);
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded) || !isset($decoded['advisories']) || !is_array($decoded['advisories'])) {
            throw new \moodle_exception('errorbadmetadata', 'tool_camp');
        }
        return $decoded['advisories'];
    }

    /**
     * Advisories from every configured repository that affect a plugin
     * installed on this site.
     *
     * Feeds union across repositories (RFC §6.3): a warning should not
     * depend on which repository a plugin came through, so every feed is
     * matched against every installed plugin. An unreachable repository is
     * skipped so one storefront being down cannot suppress another's
     * warnings.
     *
     * A plugin matches when its frankenstyle component is installed and its
     * release string satisfies the advisory's affectedVersions constraint.
     * Plugins that declare no release string cannot be matched and are
     * skipped.
     *
     * @return array of ['component', 'release', 'repo', 'advisory' => feed record]
     */
    public static function affecting_installed(): array {
        $installed = self::installed_releases();

        $matches = [];
        $seen = [];
        foreach (repository::get_repos() as $repo) {
            try {
                $feed = self::fetch($repo);
            } catch (\moodle_exception $e) {
                debugging('tool_camp: advisory feed for repository ' . $repo['name']
                    . ' unavailable: ' . $e->getMessage(), DEBUG_DEVELOPER);
                continue;
            }
            foreach ($feed as $package => $records) {
                $component = self::component_from_package($package);
                if ($component === null || !isset($installed[$component])) {
                    continue;
                }
                $release = $installed[$component];
                foreach ($records as $advisory) {
                    // The same advisory served by several repositories
                    // (e.g. a mirror) is one warning, attributed to the
                    // highest-priority repository that carries it.
                    $key = (($advisory['advisoryId'] ?? '') ?: ($advisory['title'] ?? '')) . '|' . $component;
                    if (isset($seen[$key])) {
                        continue;
                    }
                    if (self::version_matches($release, (string) ($advisory['affectedVersions'] ?? ''))) {
                        $seen[$key] = true;
                        $matches[] = [
                            'component' => $component,
                            'release' => $release,
                            'repo' => $repo['name'],
                            'advisory' => $advisory,
                        ];
                    }
                }
            }
        }
        return $matches;
    }

    /**
     * Release strings of every installed plugin, keyed by component.
     *
     * The registry's release ledger records versions from $plugin->release,
     * so that is the value advisories constrain against. Only the first
     * whitespace-separated token is kept — the common Moodle convention
     * "1.1.0 (Build: 2025070116)" must match a "<=1.1.0" constraint, and
     * the registry's Composer projection applies the same split.
     *
     * @return array component => release
     */
    public static function installed_releases(): array {
        $releases = [];
        foreach (\core_plugin_manager::instance()->get_plugins() as $plugins) {
            foreach ($plugins as $plugininfo) {
                $release = explode(' ', trim((string) $plugininfo->release))[0];
                if ($release !== '') {
                    $releases[$plugininfo->component] = $release;
                }
            }
        }
        return $releases;
    }

    /**
     * Frankenstyle component from a Composer package name.
     *
     * The repository names packages "<vendor>/moodle-<component>" (RFC §6.1).
     *
     * @param string $package Composer package name
     * @return string|null component, or null if the name has another shape
     */
    public static function component_from_package(string $package): ?string {
        $slash = strpos($package, '/');
        if ($slash === false) {
            return null;
        }
        $name = substr($package, $slash + 1);
        if (strpos($name, 'moodle-') !== 0) {
            return null;
        }
        return substr($name, strlen('moodle-'));
    }

    /**
     * True if $version satisfies the AND of all comparator clauses.
     *
     * An unparseable clause is treated as satisfied: the feed is validated
     * before publication, so a clause this code cannot read means the
     * matcher is out of date — for a security warning, over-warning beats
     * silence.
     *
     * @param string $version release string, e.g. "1.2.0"
     * @param string $constraint e.g. ">=1.0, <1.2"
     * @return bool
     */
    public static function version_matches(string $version, string $constraint): bool {
        $key = self::version_key($version);
        foreach (explode(',', $constraint) as $clause) {
            if (!preg_match('/^(>=|<=|>|<|=)(.+)$/', trim($clause), $m)) {
                debugging('tool_camp: unparseable advisory constraint clause: ' . $clause, DEBUG_DEVELOPER);
                continue;
            }
            $cmp = self::compare_keys($key, self::version_key(trim($m[2])));
            $ok = match ($m[1]) {
                '>=' => $cmp >= 0,
                '<=' => $cmp <= 0,
                '>' => $cmp > 0,
                '<' => $cmp < 0,
                '=' => $cmp === 0,
            };
            if (!$ok) {
                return false;
            }
        }
        return true;
    }

    /**
     * Sortable key: numeric dotted prefix, then any remaining suffix.
     *
     * @param string $version
     * @return array [int[] numbers, string suffix]
     */
    protected static function version_key(string $version): array {
        $version = ltrim($version, 'vV');
        if (!preg_match('/^(\d+(?:\.\d+)*)(.*)$/', $version, $m)) {
            return [[], $version];
        }
        return [array_map('intval', explode('.', $m[1])), $m[2]];
    }

    /**
     * Lexicographic comparison of two version keys (like Python tuples:
     * element by element, a shorter prefix sorts first, suffix breaks ties).
     *
     * @param array $a from {@see version_key}
     * @param array $b from {@see version_key}
     * @return int negative, zero or positive
     */
    protected static function compare_keys(array $a, array $b): int {
        [$anums, $asuffix] = $a;
        [$bnums, $bsuffix] = $b;
        $count = max(count($anums), count($bnums));
        for ($i = 0; $i < $count; $i++) {
            if (!isset($anums[$i])) {
                return -1;
            }
            if (!isset($bnums[$i])) {
                return 1;
            }
            if ($anums[$i] !== $bnums[$i]) {
                return $anums[$i] <=> $bnums[$i];
            }
        }
        return strcmp($asuffix, $bsuffix);
    }
}
