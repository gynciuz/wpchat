# WordPress.org Plugin Check — results & submission plan (2026-07-14)

Ran the **official Plugin Check plugin** (`wp plugin check`) against the actual
release artifact (`git archive HEAD`, i.e. what the ZIP ships), excluding the
vendored update-checker library. This is the same gate wp.org runs on submission.

## Blockers — ERRORS (wp.org will reject until resolved)

These are **product decisions**, not one-line fixes — the wp.org build differs
structurally from the GitHub-Releases build.

1. **Bundled update checker** — `plugin_updater_detected` (×3).
   > "Plugin Updater detected … not permitted in WordPress.org hosted plugins.
   > Use of the Update URI header is not allowed." (`YahnisElsts\PluginUpdateChecker`, `PucFactory`, the `Update URI` header)
   wp.org serves updates itself, so the vendored PUC (`vendor-puc/`) **and** the
   `Update URI:` header must be **removed** for the wp.org build. This is the
   update-channel decision already in `TASKS.md`.

2. **`proc_open()` is forbidden** — `Generic.PHP.ForbiddenFunctions.Found` in
   `includes/class-git-sync.php:191`. GitSync shells out to `git` via `proc_open`,
   which wp.org bans. GitSync is an **optional, off-by-default** power-user feature
   (gated behind `WPCHAT_GIT_SYNC_ENABLED`). Cleanest resolution: **exclude
   `class-git-sync.php` from the wp.org build** (it also drops the associated
   `unlink()`/`fopen()`/`fwrite()` file-op warnings).

3. **Inline `<script>` / `<link>`** — `NonEnqueuedScript` / `NonEnqueuedStylesheet`
   in `includes/class-frontend.php` (the bare `/wpchat` app page). The SPA page
   emits the Google-fonts `<link>` and the boot `<script>` inline. Fix: enqueue
   the built assets via `wp_enqueue_script/style` (+ `wp_add_inline_script` for
   the boot payload, which is already used in the admin path), or justify. Real
   code fix, doable independent of the decisions above.

## Naming — WARNING that reviewers commonly enforce

- **`trademarked_term` "wp"** (×3): "the plugin name 'WPChat' … and slug …
  contains the restricted term 'wp' which cannot be used at all." wp.org restricts
  "WP"/"WordPress" in names/slugs. May require a **different slug** (e.g.
  `chat-admin-for-woocommerce`, `shopchat`, `chatwoo`) and possibly a tweaked
  display name. Branding decision.

## Fixed in this pass (safe, decision-free)

- `parse_url()` → `wp_parse_url()` (frontend, seo, analytics)
- `date()` → `gmdate()` (rest.php traffic date)
- `wp_redirect()` → `wp_safe_redirect()` (frontend login redirect)
- Stop shipping internal files in the ZIP — `export-ignore` for `CLAUDE.md`,
  `TASKS.md`, `README.md`, `cloud/`, `.claude/`, `.DS_Store` (were included).
- (Earlier) `readme.txt` Tested-up-to → 7.0; superglobal `wp_unslash`+sanitize;
  justified `phpcs:ignore` on the text/plain llms.txt body.

## Remaining code warnings (address before submit; none are decisions)

- **Custom-table SQL** (`class-history.php`): `DirectDatabaseQuery.DirectQuery` /
  `NoCaching` / `PreparedSQL.InterpolatedNotPrepared` / `DirectDB.UnescapedDBParameter`.
  The values are prepared; the flags are the interpolated **table name** and the
  lack of object caching (a live messages table isn't cacheable). Resolve with
  `%i` table-name placeholders (WP 6.2+, we require 6.5) and/or
  `// phpcs:ignore … -- custom table, prepared, not cacheable` with justification.
- **`EscapeOutput`** (Exception/Output/Heredoc): mostly REST-JSON / `text/plain`
  contexts (not HTML). Add `esc_html` where genuinely HTML, else justified ignores.
- **i18n**: `MissingTranslatorsComment` (×5), `MissingArgDomain` (×2) — add
  `/* translators: */` comments and the `'wpchat'` domain where flagged.
- **`NonPrefixedHooknameFound`**: verify — WP core hooks we *hook into* are fine;
  only our own `do_action`/`apply_filters` need the `wpchat_` prefix (they have it).

## Submission runbook (human steps)

1. Resolve the three decisions above → produce a **wp.org build variant** (no PUC,
   no `Update URI`, no GitSync, possibly renamed slug).
2. Clear the remaining code warnings; re-run `wp plugin check` until clean.
3. Validate `readme.txt` (wordpress.org/plugins/developers/readme-validator/).
4. Upload the ZIP at wordpress.org/plugins/developers/add/ → human review (days–weeks).
5. On approval: push `trunk/` + a version `tag/`, and the `/assets` (icon, banner,
   screenshots from `site/wporg-assets/`) to the plugin's SVN repo.
