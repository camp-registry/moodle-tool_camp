# tool_camp — the CAMP client for Moodle

Stage 1 client plugin (RFC §6.2): lets site administrators browse a CAMP
repository and install plugins through Moodle's own deployment machinery,
with every download verified against the repository's published SHA-256
before any file is written. No core changes required; installs once via
standard ZIP upload.

Requires Moodle 4.5+. Alpha.

## How it works

- Consumes the repository's Composer metadata (`packages.json`); the
  camp-specific facts (trust tier, disclosure labels, supported Moodle
  branches, publication time) ride along under `extra.camp`.
- Site policy is enforced client-side: minimum trust tier (2 source-verified
  or 3 human-reviewed; below tier 2 nothing is installable, RFC §4.4) and an
  optional release cooldown ("only offer releases older than N days"),
  plus filtering to the site's own Moodle branch.
- Security advisories (RFC §5.3): a scheduled task (every 6 hours) downloads
  the repository's complete `security-advisories.json` feed and matches it
  locally against the site's installed plugins — the repository never learns
  what this site runs. New matches are emailed to site administrators once.
- Install flow: download to request-scoped temp → `hash_equals` the
  published SHA-256 → `\core\update\code_manager::unzip_plugin_file()` →
  redirect to the standard upgrade page. A hash mismatch aborts with
  nothing deployed.
- Privacy (RFC §4.6): requests carry no site or user identifiers; mirrors
  see anonymous file downloads only.

## Not yet implemented

- TUF metadata verification client-side (currently trusts TLS + hash from
  the fetched metadata; the signed-metadata client lands with Phase 2) —
  this also covers the advisory feed, which today is trusted via TLS
- Update notifications for already-installed plugins

## Deployment notes (from live testing on Moodle 4.5.12)

- **Moodle's cURL security applies to repository fetches.** Sites commonly
  block private IP ranges (`curlsecurityblockedhosts`) and restrict outbound
  ports to 80/443 (`curlsecurityallowedport`). A production camp repository
  on public HTTPS:443 needs no exceptions; mirrors on non-standard ports or
  internal networks require the site admin to allow them explicitly.
- Plain-http repository URLs work only when the site is in developer debug
  mode (for testing against a locally served repo). Artifact hashes are
  verified in every mode.

## Settings

Site administration → Plugins → Admin tools:
- **Repository URL** — the CAMP repository or any mirror
- **Minimum trust tier** — Tier 2 (source-verified) or Tier 3 (human-reviewed)
- **Release cooldown** — sit out the first N hours/days of every release
