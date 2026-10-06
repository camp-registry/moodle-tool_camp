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
 * Read-only merged view of the configured camp repositories, filtered by
 * this site's policy.
 *
 * Multiple repositories (RFC §6.3): the site configures an ordered list of
 * repositories — the community registry, a marketplace, a partner's
 * customer-only repository — and this class merges their Composer metadata
 * (packages.json, camp facts under each version's extra.camp key) under
 * rules that keep trust separable:
 *
 *  - collisions on a component are resolved by the list's priority order,
 *    and the losing sources are reported so the UI can show the shadowing;
 *  - once a component has been installed from a repository, only that
 *    repository may offer it again (source binding) until the binding is
 *    changed — a higher version elsewhere is never an update. This
 *    forecloses cross-repository dependency confusion.
 *
 * All artifact downloads are verified against the published SHA-256 by
 * {@see installer}, so transports and mirrors stay untrusted.
 *
 * Privacy: requests carry no site or user identifiers (RFC §4.6) beyond
 * the access token a commercial repository may require.
 *
 * @package    tool_camp
 * @copyright  2026 the camp project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository {
    /**
     * The configured repositories, in priority order.
     *
     * Parsed from the tool_camp/repos setting: one repository per line,
     * "name|https://url" with optional "|token=...", "|mintier=N" and
     * "|minstability=stable|rc|beta|alpha"
     * fields. Blank lines and lines starting with # are ignored, as are
     * malformed lines (with a developer debugging note). HTTPS is
     * enforced; plain http is permitted only in developer debug mode so a
     * locally served repository can be tested.
     *
     * @return array repository name => ['name', 'url', 'token', 'mintier', 'minstability']
     */
    public static function get_repos(): array {
        $repos = [];
        foreach (preg_split('/\R/', (string) get_config('tool_camp', 'repos')) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < 2 || !preg_match('/^[a-zA-Z0-9_-]+$/', $parts[0])) {
                debugging('tool_camp: ignoring malformed repository line: ' . $line, DEBUG_DEVELOPER);
                continue;
            }
            $url = rtrim($parts[1], '/');
            $ishttps = strpos($url, 'https://') === 0;
            $isdevhttp = strpos($url, 'http://') === 0 && debugging('', DEBUG_DEVELOPER);
            if (!$ishttps && !$isdevhttp) {
                debugging('tool_camp: ignoring non-https repository line: ' . $line, DEBUG_DEVELOPER);
                continue;
            }
            $repo = ['name' => $parts[0], 'url' => $url, 'token' => '', 'mintier' => 0,
                'minstability' => ''];
            foreach (array_slice($parts, 2) as $option) {
                if (strpos($option, 'token=') === 0) {
                    $repo['token'] = substr($option, strlen('token='));
                } else if (strpos($option, 'mintier=') === 0) {
                    $repo['mintier'] = (int) substr($option, strlen('mintier='));
                } else if (strpos($option, 'minstability=') === 0) {
                    $value = strtolower(substr($option, strlen('minstability=')));
                    if (isset(self::MATURITY_RANK[$value])) {
                        $repo['minstability'] = $value;
                    }
                }
            }
            if (!isset($repos[$repo['name']])) {
                $repos[$repo['name']] = $repo;
            }
        }
        return $repos;
    }

    /**
     * HTTP headers for requests to a repository (its access token, if any).
     *
     * @param array $repo one entry from {@see get_repos}
     * @return array|null headers for download_file_content, or null
     */
    public static function request_headers(array $repo): ?array {
        return $repo['token'] !== '' ? ['Authorization: Bearer ' . $repo['token']] : null;
    }

    /**
     * Fetch and cache one repository's package metadata.
     *
     * @param array $repo one entry from {@see get_repos}
     * @return array package name => (version => definition)
     */
    public static function get_packages(array $repo): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $cache = \cache::make('tool_camp', 'packages');
        $cachekey = 'packages_' . sha1($repo['url']);

        $packages = $cache->get($cachekey);
        if ($packages === false) {
            $json = download_file_content($repo['url'] . '/packages.json', self::request_headers($repo));
            if ($json === false) {
                throw new \moodle_exception('errornorepo', 'tool_camp', '', $repo['url']);
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

    /** Release maturity, least to most mature; a record without the field is stable. */
    const MATURITY_RANK = ['alpha' => 0, 'beta' => 1, 'rc' => 2, 'stable' => 3];

    /**
     * Whether a release of the given maturity may be offered under a policy floor.
     *
     * @param string $maturity the record's extra.camp.maturity (missing = stable)
     * @param string $floor the policy's minimum: stable, rc, beta or alpha
     * @return bool
     */
    public static function maturity_allowed(string $maturity, string $floor): bool {
        $rank = self::MATURITY_RANK[strtolower($maturity)] ?? self::MATURITY_RANK['stable'];
        $min = self::MATURITY_RANK[$floor] ?? self::MATURITY_RANK['stable'];
        return $rank >= $min;
    }

    /**
     * The newest version of each component that this site's policy allows,
     * merged across all configured repositories.
     *
     * Policy filters per repository: minimum trust tier (the repository's
     * own mintier= override, else the site default; never below the
     * registry's tier-2 installation floor, RFC §4.4), minimum release
     * maturity (minstability= override, else the site default; stable unless
     * the site opts in to rc, beta or alpha), release cooldown, and Moodle
     * branch support. Cross-repository rules: a component bound
     * by a previous installation is only offered from its bound
     * repository; otherwise the highest-priority repository offering an
     * eligible version wins. Every offered definition is annotated with
     * '_camprepo' (the supplying repository) and '_campshadowed' (other
     * configured repositories that also list the component).
     *
     * @return array package name => version definition (with 'version' key)
     */
    public static function get_installable(): array {
        $repos = self::get_repos();
        $bindings = self::get_bindings();
        $sitemintier = (int) get_config('tool_camp', 'mintier');
        $sitestability = (string) get_config('tool_camp', 'minstability');
        if (!isset(self::MATURITY_RANK[$sitestability])) {
            $sitestability = 'stable';
        }
        $cooldown = (int) get_config('tool_camp', 'cooldown');
        $branch = self::current_branch();

        // First pass: per repository, the best eligible version of each
        // component, plus which repositories list each component at all.
        $offers = [];
        $present = [];
        foreach ($repos as $repo) {
            // Tier 2 (source-verified) is the registry's installation floor
            // (RFC §4.4); below it there is no verified artifact, whatever
            // any setting says.
            $mintier = max(2, $repo['mintier'] > 0 ? $repo['mintier'] : $sitemintier);
            // Pre-releases are offered only on opt-in: the site setting, or
            // the repository's own minstability= override.
            $minstability = $repo['minstability'] !== '' ? $repo['minstability'] : $sitestability;
            try {
                $packages = self::get_packages($repo);
            } catch (\moodle_exception $e) {
                debugging('tool_camp: repository ' . $repo['name'] . ' unavailable: '
                    . $e->getMessage(), DEBUG_DEVELOPER);
                continue;
            }
            foreach ($packages as $name => $versions) {
                $best = null;
                $component = null;
                foreach ($versions as $definition) {
                    $camp = $definition['extra']['camp'] ?? null;
                    if ($camp === null || empty($camp['component'])) {
                        continue;
                    }
                    $component = $camp['component'];
                    if ((int) ($camp['tier'] ?? 0) < $mintier) {
                        continue;
                    }
                    if (!in_array($branch, $camp['supported-moodle'] ?? [], true)) {
                        continue;
                    }
                    if (!self::maturity_allowed($camp['maturity'] ?? 'stable', $minstability)) {
                        continue;
                    }
                    if ($cooldown > 0) {
                        $published = strtotime($camp['published'] ?? '') ?: 0;
                        if ($published + $cooldown > time()) {
                            continue;
                        }
                    }
                    // The SHA-256 rides in extra.camp (Composer's dist.shasum
                    // is SHA-1 only, so the feed stopped writing it there).
                    if (empty($definition['dist']['url']) || empty($camp['zip-sha256'])) {
                        continue;
                    }
                    if ($best === null || version_compare($definition['version'], $best['version'], '>')) {
                        $best = $definition;
                    }
                }
                if ($component === null) {
                    continue;
                }
                $present[$component][] = $repo['name'];
                if ($best !== null && !isset($offers[$component][$repo['name']])) {
                    $best['_camprepo'] = $repo['name'];
                    $best['_camppackage'] = $name;
                    $offers[$component][$repo['name']] = $best;
                }
            }
        }

        // Second pass: pick one supplier per component.
        $installable = [];
        foreach ($offers as $component => $byrepo) {
            if (isset($bindings[$component])) {
                // Source binding: only the repository this component was
                // installed from may offer it (RFC §6.3).
                $winner = $byrepo[$bindings[$component]] ?? null;
            } else {
                $winner = null;
                foreach ($repos as $repo) {
                    if (isset($byrepo[$repo['name']])) {
                        $winner = $byrepo[$repo['name']];
                        break;
                    }
                }
            }
            if ($winner === null) {
                continue;
            }
            $winner['_campshadowed'] = array_values(array_diff(
                array_unique($present[$component]),
                [$winner['_camprepo']]
            ));
            $installable[$winner['_camppackage']] = $winner;
        }
        ksort($installable);
        return $installable;
    }

    /**
     * Look up one policy-allowed package by name.
     *
     * @param string $name Composer package name
     * @return array version definition (annotated with '_camprepo')
     */
    public static function get_package(string $name): array {
        $installable = self::get_installable();
        if (!isset($installable[$name])) {
            throw new \moodle_exception('errorunknownpackage', 'tool_camp', '', s($name));
        }
        return $installable[$name];
    }

    /**
     * Source bindings: which repository each installed component came from.
     *
     * @return array component => repository name
     */
    public static function get_bindings(): array {
        $bindings = json_decode((string) get_config('tool_camp', 'sourcebindings'), true);
        return is_array($bindings) ? $bindings : [];
    }

    /**
     * Record that a component was installed from a repository. From now on
     * only that repository may offer the component (RFC §6.3), until an
     * administrator changes the binding.
     *
     * @param string $component frankenstyle component
     * @param string $reponame repository name from {@see get_repos}
     */
    public static function bind(string $component, string $reponame): void {
        $bindings = self::get_bindings();
        $bindings[$component] = $reponame;
        set_config('sourcebindings', json_encode($bindings), 'tool_camp');
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
