# tool_camp — the CAMP client for Moodle

Stage 1 client plugin (RFC §6.2): lets site administrators browse one or
more CAMP-format repositories and install plugins through Moodle's own
deployment machinery, with every download verified against the publishing
repository's SHA-256 before any file is written. No core changes required;
installs once via standard ZIP upload.

Requires Moodle 4.5+. Alpha.

## How it works

- Consumes each repository's Composer metadata (`packages.json`); the
  camp-specific facts (trust tier, disclosure labels, supported Moodle
  branches, publication time) ride along under `extra.camp`.
- Multiple repositories (RFC §6.3), RPM-style: an ordered list — e.g. the
  community registry, a marketplace, a partner's token-gated repository.
  Component collisions resolve by priority order with shadowing shown in
  the UI, and a component installed from a repository is only ever offered
  from that repository again (source binding, recorded at install time) —
  a higher version elsewhere is never an update. This forecloses
  cross-repository dependency confusion. Commercial repositories are
  supported via a per-repository bearer token.
- Site policy is enforced client-side: minimum trust tier (2 source-verified
  or 3 human-reviewed; below tier 2 nothing is installable, RFC §4.4), a
  minimum release maturity (stable by default; release candidates, betas
  and alphas only when the site opts in), an optional release cooldown
  ("only offer releases older than N days"), plus filtering to the site's
  own Moodle branch.
- Security advisories (RFC §5.3): a scheduled task (every 6 hours) downloads
  every configured repository's complete `security-advisories.json` feed and
  matches the union locally against the site's installed plugins — no
  repository learns what this site runs, and warnings don't depend on which
  repository a plugin came through. New matches are emailed to site
  administrators once.
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
- **Repositories** — one per line, highest priority first:
  `name|https://url` with optional `|token=…` (commercial repositories)
  `|mintier=N` (per-repository tier override) and `|minstability=…`
  (per-repository maturity override, e.g. a staging repository that should
  offer betas). Any mirror URL works — artifacts are hash-verified.
- **Minimum trust tier** — site default: Tier 2 (source-verified) or
  Tier 3 (human-reviewed)
- **Minimum release maturity** — stable only (default), or down to release
  candidates, betas or alphas; a pre-release that is offered is marked as
  such in the catalogue
- **Release cooldown** — sit out the first N hours/days of every release

Upgrading from a single-repository version migrates the old Repository URL
setting to a `default|<url>` line automatically.
