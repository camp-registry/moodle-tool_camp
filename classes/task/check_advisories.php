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

namespace tool_camp\task;

use tool_camp\local\advisories;

/**
 * Scheduled advisory check (RFC §5.3): warn administrators when a
 * published security advisory affects a plugin installed on this site.
 *
 * Pull-based and privacy-preserving: the task downloads the complete
 * advisory feed and matches it locally; the repository never learns which
 * plugins this site runs (RFC §4.6). Each advisory/plugin pair is emailed
 * to the site administrators once; already-notified pairs are remembered
 * in plugin config and re-announced only if the advisory is republished
 * against a newly affected installed version.
 *
 * @package    tool_camp
 * @copyright  2026 the camp project
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_advisories extends \core\task\scheduled_task {
    /**
     * Task name shown in the scheduled task list.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskcheckadvisories', 'tool_camp');
    }

    /**
     * Fetch the feed, match locally, notify administrators of new matches.
     */
    public function execute(): void {
        if (!\tool_camp\local\repository::get_repos()) {
            mtrace('tool_camp: no repositories configured, skipping advisory check.');
            return;
        }

        $matches = advisories::affecting_installed();
        if (!$matches) {
            mtrace('tool_camp: no advisories affect installed plugins.');
            return;
        }

        $notified = json_decode((string) get_config('tool_camp', 'notifiedadvisories'), true);
        $notified = is_array($notified) ? $notified : [];

        $new = [];
        foreach ($matches as $match) {
            $key = $match['repo'] . '|' . ($match['advisory']['advisoryId'] ?? '') . '|' . $match['component'];
            if (!in_array($key, $notified, true)) {
                $new[] = $match;
                $notified[] = $key;
            }
        }
        mtrace('tool_camp: ' . count($matches) . ' advisory match(es), ' . count($new) . ' new.');
        if (!$new) {
            return;
        }

        if ($this->notify_admins($new)) {
            set_config('notifiedadvisories', json_encode($notified), 'tool_camp');
        } else {
            // Nothing reached an administrator; leave the matches unmarked
            // so the next run tries again rather than going silent.
            mtrace('tool_camp: not marking advisories as notified; will retry next run.');
        }
    }

    /**
     * Email every site administrator about newly matched advisories.
     *
     * @param array $matches from {@see advisories::affecting_installed}
     * @return bool true if at least one email was sent
     */
    protected function notify_admins(array $matches): bool {
        global $SITE;

        $lines = [];
        foreach ($matches as $match) {
            $advisory = $match['advisory'];
            $lines[] = get_string('advisoryemailline', 'tool_camp', (object) [
                'component' => $match['component'],
                'release' => $match['release'],
                'severity' => strtoupper((string) ($advisory['severity'] ?? '')),
                'id' => (string) ($advisory['advisoryId'] ?? ''),
                'title' => (string) ($advisory['title'] ?? ''),
                'affected' => (string) ($advisory['affectedVersions'] ?? ''),
                'link' => (string) ($advisory['link'] ?? ''),
                'repo' => $match['repo'],
            ]);
        }

        $subject = get_string('advisoryemailsubject', 'tool_camp', (object) [
            'count' => count($matches),
            'site' => format_string($SITE->fullname),
        ]);
        $body = get_string('advisoryemailintro', 'tool_camp')
            . "\n\n" . implode("\n\n", $lines) . "\n\n"
            . get_string('advisoryemailoutro', 'tool_camp');

        $sent = 0;
        $failed = 0;
        foreach (get_admins() as $admin) {
            if (email_to_user($admin, \core_user::get_noreply_user(), $subject, $body)) {
                $sent++;
            } else {
                $failed++;
            }
        }
        mtrace("tool_camp: notified $sent administrator(s)"
            . ($failed ? ", $failed email(s) failed to send" : '') . '.');
        return $sent > 0;
    }
}
