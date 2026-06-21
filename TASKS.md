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
