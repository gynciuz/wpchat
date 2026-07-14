# TASKS

This file is the work queue for the coding agent. It is both the **instructions**
for how to operate and the **list** of work to do. Read the whole file before
starting.

---

## HOW TO USE THIS FILE (agent operating procedure)

1. **Pick the next task.** Work in the order tasks were added. New tasks are
   added to the **top**; the **oldest unchecked task is the bottom-most one**.
   Always start at the bottom and work upward. Never skip an unchecked task
   unless it is explicitly marked blocked (see status tags below).

2. **Understand before coding.** If a task is ambiguous or under-specified:
   - Gather the relevant files yourself first (search the repo, read the code).
   - If still unclear, ask the user a specific question via CLI rather than
     guessing. State what you found and what decision you need.
   - For tasks tagged `scope and discuss`, do NOT start coding — produce a plan
     and wait for user approval first.

3. **Do the work** following the CONVENTIONS section below.

4. **Verify.** Run the build (and tests/linter if present) after each task.
   Do not check off a task whose build fails.

5. **Check it off.** Edit this file: change `[ ]` to `[x]` for the completed
   task. Optionally add a short parenthetical note, e.g.
   `[x](done 2026-06-02) short note on what was done`.

6. **Commit.** Make one commit per task with a clear message describing the
   change. Then move to the next unchecked task.

7. **Repeat** until every task is checked off.

### Status tags
- `[ ]` — not started.
- `[x]` — done.
- `[x](manual, done DATE)` — completed by the user outside the codebase
  (e.g. a dashboard/config change). Recorded here for history; no code action.
- `[x](infra, done DATE)` — infrastructure/ops change done by the user.
- `[ ](blocked: reason)` — cannot proceed; leave unchecked and skip. Re-attempt
  only when the stated unblock condition is met. Document why in the task.
- `(scope and discuss)` — requires a plan + user sign-off before any code.
- `(priority)` / `(later)` — relative ordering hints from the user.

### Sub-agents / delegation
If a research or specialist agent is available, hand it the gathered files and a
focused question rather than dumping the whole repo. Pass back only what the
implementing step needs.

---

## CONVENTIONS

These rules are project-specific and enforceable. See `CLAUDE.md` for the full
architecture notes; this section is the short, checkable version.

**Build & verify (do this before checking off any task):**
- PHP change → run `composer test` (or the relevant suite: `test:unit` /
  `test:integration` / `test:scenarios`). A single test:
  `vendor/bin/phpunit --filter=<method>`.
- React change (`app/src/**`) → run `cd app && pnpm build` AND commit the
  regenerated `build/` output. The released ZIP ships prebuilt assets, so a
  source change without a rebuilt `build/` is incomplete. Also run `pnpm lint`.
- Releasing → bump the version in **both** places in `wpchat.php`: the
  `Version:` header and the `WPCHAT_VERSION` constant.

**Adding/changing a tool (`includes/class-tools.php`):**
- Register it in **both** `Tools::definitions()` (JSON schema) and
  `Tools::implementations()` (name → callable).
- Order tools take a single id — never add bulk-destructive operations
  (intentional safety feature, also enforced in the system prompt).
- When no tool fits a request, the path is `get_admin_url` (deep-link
  handoff), not a refusal.

**Behavioral / prompt changes:**
- Most product behavior lives in the dynamic system prompt
  (`Rest::system_prompt`), not in code. Prefer editing the prompt.
- Lock in behavioral rules with a scenario test in `tests/Scenarios/`,
  using the `wpchat_anthropic_http_response` mock (`tests/MockAnthropic.php`)
  so the LLM call is deterministic and free.

**Extensibility — prefer filters over core edits:**
- Content kinds → `wpchat_content_backends` (`ContentBackend` impls).
- Analytics → `wpchat_analytics_providers` (`AnalyticsProvider` impls).
- Content edits are gated by capability; keep the three gate layers in sync
  (prompt visibility, `Tools::user_can_edit_kind`, dispatch `check_kind_access`).

**General:**
- PHP 8.1+, namespace `WPChat\`, every file opens with the
  `if (!defined('ABSPATH')) exit;` guard.
- Status slugs: stored WC-prefixed (`wc-completed`), used unprefixed
  (`completed`) via `Tools::unprefixed_status`.
- Keep multilingual support (LT/RU/PL/EN) intact in UI strings, confirmation
  whitelists, and status mapping.

---

## TASKS

> Newest at the top. Work bottom-up. One commit per task. Use `[x]` to check off.

[ ] (scope and discuss) **Harden the preview→apply confirmation with a server-minted token.**
    Security-audit finding #2 (medium): the two-step preview→apply / order-confirm
    gate currently trusts the model to (a) actually run a `preview_*` before
    `apply_*` and (b) supply a genuine `confirmation` phrase — both are only
    enforced in the system prompt. A prompt-injection payload in tool-returned
    content (an order note, page HTML) could make the model call `apply_*` /
    `update_order_status` with `confirmation: "yes"` and skip the preview.
    Fix direction: `preview_content_change` mints a single-use token (stored in a
    short-TTL transient, bound to the conversation + target); `apply_content_change`
    only proceeds if given a matching, unconsumed token. Same shape for order
    mutations. React passes the token through the Confirm button. Add scenario tests.
    NOTE: capability gating (audit finding: holds) already caps blast radius to the
    acting user's own privileges, and the `_confirmed`-flag bypass (finding #1) is
    already fixed — this closes the residual "model asserts consent" gap.

[ ] (priority) **Prep for WordPress.org plugin-directory submission.**
    - Install the official **Plugin Check (PCP)** plugin locally; fix every error/
      warning (escaping, nonces, sanitization, direct-file-access, i18n text-domain).
    - Validate `readme.txt` with the official readme validator.
    - Directory assets (icon 256×256 + 128×128, banner 1544×500 + 772×250,
      screenshots) — generated under `site/wporg-assets/`.
    - **Decide the update channel:** listing on WP.org means WP.org serves updates;
      reconcile with the current GitHub-Releases + PUC + `Update URI` auto-update
      (recommend: drop PUC, remove the `Update URI` pin, use WP.org as the update
      source; keep `bin/release.sh` GitHub ZIP as a pre-release/secondary channel).
    - Guideline compliance: GPL-compat (MIT ✓), Anthropic external-service disclosure
      + privacy policy (✓), no remote code loading, slug `wpchat` (no trademarked
      terms). Submit at wordpress.org/plugins/developers/add/.
    - Address findings from the security audit (see the separate `(priority)` fix task) first.

[x](done 2026-07-14) **Expand built-in language coverage to the marketing-priority set.**
    Done: added EN/ES/FR/PT/HI/ZH/DE affirmatives + negations to
    `ContentConfirmation::is_confirmed` (Mandarin matched by substring — no word
    boundaries; Hindi/Latin by token), extended the React confirm/cancel + hero +
    help/report locales in `Chat.tsx` (locale is 2-letter from `substr(get_user_locale,0,2)`),
    and broadened the "Languages" line in the help system prompt. Russian kept in
    the whitelist + UI. Tests: `ContentConfirmationTest` now 62 assertions green
    (new accepted/rejected cases per language). React `pnpm build` green; `build/` recommitted.
    The landing page advertised **EN, ES, FR, PT, HI (Hindi), ZH (Mandarin),
    DE** — plus "dozens more" — with **Russian kept working but no longer featured**.
    The LLM already handles arbitrary languages, but the hardcoded multilingual bits
    must cover the new set so we don't over-claim in marketing:
    - `ContentConfirmation::is_confirmed` confirmation whitelist — add sí/confirmar,
      oui/confirmer, sim/confirmar, ja/bestätigen, हाँ/पुष्टि करें, 确认/是, etc.
    - The status→slug multilingual map in `Rest::system_prompt`.
    - React confirm/cancel button locales (currently LT/RU/PL/EN → add ES/FR/PT/HI/ZH/DE).
    **Keep Russian** in the whitelist and support paths (do not remove) — only
    de-emphasized in marketing. Add locale tests mirroring the existing confirmation
    tests. Priority order: EN, ES, FR, PT, HI, ZH, DE, then the rest.

[ ] (scope and discuss) (priority) **Stand up the paid WPChat Cloud tier (Stripe subscription billing).**
    Goal: let a site owner subscribe to hosted WPChat with **no BYO API key** —
    the backend runs on *our* Anthropic key behind a proxy. Revenue track;
    requested "asap". Pricing already scoped in `docs/wpchat-cloud-pricing.md`
    (**€12/mo**, Sonnet default, fair-use soft cap ~150 chats/mo, overage =
    throttle-to-Haiku or top-up). Stripe account is live (display name "Loupe",
    `acct_1Ts8WKHRn8ZbPNdX`).
    Design must cover: Stripe product/price + Checkout/Customer Portal;
    subscription state → plugin entitlement (webhook or license-key check);
    the hosted proxy that injects our key and enforces the per-site cap;
    `Settings::get_api_key` precedence when Cloud is active; onboarding/UI
    (the ProviderCard `cloudPrice` already surfaces "€12/mo"); overage policy;
    security (no key ever reaches the browser; webhook signature verification;
    restricted Stripe keys). **This is `scope and discuss` — produce the design
    + implementation plan and wait for sign-off before writing billing code.**
    Being drafted in an isolated worktree session (2026-07-13).

[x](done 2026-06-22) **Create posts/pages with images + guided taxonomy/SEO.**
    Fixed the upload bug: oversized file (over PHP upload_max_filesize) was
    misreported as "No file provided" — now reports the size limit; dev server
    raised to 12M. New tools: `create_content` (draft post/page; categories/tags
    create-if-missing; featured + inline images; SEO title/desc), `publish_content`
    (confirmed), `list_taxonomy_terms`. Frontend: multi-image attach (multiple
    chips, upload-all, thumbnails). System-prompt consultative mini-tour for
    unsure users + draft-first/offer-publish. Tests: CreateContentTest,
    UploadErrorTest, Playwright multi-image (178 PHP + 5 E2E green). Smoke-tested
    on dev site: consultancy flow + create_content + publish prompt all work.

[x](done 2026-06-22) **Add SEO audit + fix skills to the chat.** New `WPChat\Seo`
    + `SeoBackend` (`includes/class-seo.php`): read-only `seo_audit` tool; fixes
    via the content-backend pattern — kinds `seo_setting` (search_engine_visibility,
    permalink_structure, ai_crawlers, llms_txt, site_title, tagline) and `seo_meta`
    (seo_title, meta_description; Yoast/Rank Math/SEOPress; AIOSEO handoff).
    robots_txt filter + virtual /llms.txt route. SEO section in the system prompt;
    guides in docs/seo/. Tests: SeoAuditTest + SeoBackendTest (169 total, green).
    Smoke-tested on the dev site: audit chat works, /robots.txt serves GPTBot/
    ClaudeBot/PerplexityBot, /llms.txt 200.

[x](done 2026-06-21) **Fix wc_status() order-count crash found via manual testing.**
    `Onboarding::wc_status()` read `wp_count_posts('shop_order')->processing/
    ->completed`, which don't exist (WC statuses are `wc-*`; HPOS stores orders
    outside the posts table). The "Undefined property" warnings leaked into the
    onboarding/status JSON, breaking the frontend parse ("The string did not
    match the expected pattern"). Now sums `wc_orders_count()` over
    `wc_get_order_statuses()` (HPOS + legacy safe). Verified: endpoint 200,
    order_count 6; full PHP suite green. Dev server also hardened with
    display_errors=0 so stray warnings can't corrupt REST responses.

[x](done 2026-06-19) **Run a local WordPress dev server to manually test the plugin.**
    Live at http://localhost:8080 (admin/admin). WP 7.0 + WooCommerce + WPChat
    (symlinked from the repo) at ~/wpchat-dev, served by WP-CLI's built-in server
    on PHP 8.3 (WP-CLI's phar fatals on the system's PHP 8.5, so it's invoked
    directly with the 8.3 binary). `/wpchat` redirects to login then renders the
    app. Anthropic key still needs to be set in-app (Settings / onboarding) for
    chat to work.

[x](done 2026-06-19) **Add Playwright visual/E2E testing for the React app.**
    Added `@playwright/test` + Chromium, `app/playwright.config.ts` (Vite
    webServer + snapshot config), `app/e2e/harness.html` (mounts the real app
    with injected `WPCHAT_BOOT`), `app/e2e/fixtures.ts` (mock REST), and specs
    `chat.spec.ts` (empty-state, orders table, error banner) + `onboarding.spec.ts`
    (welcome step). Visual baselines under `app/e2e/__screenshots__/`. Scripts
    `pnpm test:e2e` / `test:e2e:update`; CI `e2e` job added to test.yml.
    **`pnpm test:e2e` → 4 passed** with stable snapshots; new files lint clean.
    Note: `pnpm lint` is red on pre-existing `src/` files (OrdersTable/badge/
    button) — unrelated to this work, was failing on main.

[x](done 2026-06-19) **Stand up the local PHP test environment and run the suite.**
    Installed PHP 8.5 / Composer / MySQL 9.6 / svn via Homebrew, `composer install`,
    `bin/install-wp-tests.sh wpchat_tests root '' 127.0.0.1 latest` + WooCommerce.
    `composer test` → **157 tests, 332 assertions, 0 failures** (5 pre-existing
    skips). PHP 8.5-only deprecation notices (imagedestroy/finfo_close) are
    non-fatal and absent on CI's 8.1–8.3.

[x](done 2026-06-19) **Use AnalyticsRouter in onboarding detection.**
    `Onboarding::analytics_status()` now maps `AnalyticsRouter::detected()` to
    the `{id, name}` shape the React card reads; stale inline `class_exists`
    detection + comment removed. Frontend only displays `name`, so the shape
    is preserved.

[x](done 2026-06-19) **Wire the `get_traffic_summary` tool to AnalyticsRouter.**
    Added tool def + impl (`Tools::get_traffic_summary`), a `# Site analytics /
    traffic` prompt section, and `tests/Scenarios/TrafficSummaryTest.php`.
    Verified: `composer test` green (TrafficSummaryTest 5/5).

_Add new tasks above this line, newest at the top._
