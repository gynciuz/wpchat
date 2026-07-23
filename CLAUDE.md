# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

ChatAdmin is a WordPress plugin (slug `chat-admin`, distributed as `chat-admin-vX.Y.Z.zip`) that adds a chat-based admin assistant for WooCommerce. A logged-in editor/admin visits `/chatadmin` (the canonical public route; `/wpchat` and `/chat-admin` are kept as aliases), types a request in any language (e.g. "mark order 2833 used, customer spent 30€ of 100€"), and the backend runs an LLM tool-use loop (Anthropic Claude, OpenAI, or Gemini — user's choice) that calls WC/WP PHP functions directly and renders rich React cards inline (orders table, confirm buttons, image previews).

Two halves:
- **PHP plugin** (`includes/`, `chat-admin.php`) — REST API, the Anthropic tool-use loop, all tools.
- **React app** (`app/`) — the `/chatadmin` SPA, built with Vite into `build/` and served by the PHP `Frontend` class.

The plugin auto-updates from GitHub Releases via the vendored Plugin Update Checker (`vendor-puc/`) — there is no WP.org listing. The version must be bumped in **all four** places when releasing: `chat-admin.php` (header `Version:` **and** `CHATADMIN_VERSION`) and `readme.txt` (`Stable tag:` **and** a matching `= X.Y.Z =` changelog heading). `bin/release.sh` asserts these agree, rebuilds the app, and packages a clean `chat-admin-vX.Y.Z.zip` via `git archive` (honoring `.gitattributes` export-ignore); the CI `release-guard` job enforces version consistency + that committed `build/` is current.

## Commands

```bash
# React app (run from app/)
cd app && pnpm install
pnpm build          # tsc -b && vite build → outputs to ../build/ (committed)
pnpm dev            # vite dev server
pnpm lint           # eslint
pnpm test:e2e       # Playwright E2E

# PHP tests (require MySQL + WP test scaffold installed once)
composer install
bin/install-wp-tests.sh chatadmin_tests root '' 127.0.0.1 latest   # one-time DB + scaffold
composer test                 # all suites (phpunit)
composer test:unit            # tests/Unit  — pure PHP, no WP boot
composer test:integration     # tests/Integration — real WP + MySQL via WP_UnitTestCase
composer test:scenarios       # tests/Scenarios — end-to-end flows with mocked Anthropic

# A single file / method
vendor/bin/phpunit tests/Scenarios/ProactiveHandoffTest.php
vendor/bin/phpunit --filter=test_delete_order_request_triggers_get_admin_url
```

The `build/` output is committed to the repo because the released ZIP serves prebuilt assets — after changing anything under `app/src/`, run `pnpm build` and commit the regenerated `build/` files. CI (`.github/workflows/test.yml`) runs phpunit across PHP 8.1/8.2/8.3 × WP 6.6/latest (installing WooCommerce first) plus a `php -l` lint of every PHP file.

## Architecture

### Request flow
1. `Frontend::maybe_render` intercepts `/chatadmin` (and the `/wpchat` + `/chat-admin` aliases) via `template_redirect`, gates on login + `edit_posts`, forces an HTTP 200 (the virtual route would otherwise inherit the main query's 404 status), reads `build/manifest.json`, and emits a bare HTML page with `window.CHATADMIN_BOOT` (REST URL, nonce, locale, site info, and `mode: 'chat' | 'onboarding'`).
2. The React app (`app/src/main.tsx`) renders `Chat` or `OnboardingWizard` based on `boot.mode`.
3. `Chat` POSTs to `chatadmin/v1/chat` (`includes/class-rest.php`). `Rest::handle_chat` persists the user message (`History`), builds the **system prompt**, then calls `LLM::run_with_tools` (the active provider).
4. `BaseLLMProvider::run_with_tools` (`includes/class-llm-providers.php`) runs the tool-use loop (max 8 turns), executing tool callables and feeding `tool_result` blocks back until `end_turn`. Returns `{text, messages, tool_calls}`.

### Multi-provider LLM (`includes/class-llm-providers.php`)
The engine speaks **one canonical format internally: Anthropic content blocks** (text / tool_use / tool_result). `BaseLLMProvider` owns the canonical tool-use loop; each adapter translates to/from its wire format **only at the HTTP boundary**, so tools, the system prompt, History, and the React UI (which reads the neutral `{name, input, output}` capture) are provider-independent. Providers: `AnthropicProvider` (near-identity, in `class-anthropic.php`, keeps the `chatadmin_anthropic_http_response` test seam), `OpenAIProvider` (Chat Completions), `GeminiProvider` (generateContent, with a `sanitize_schema()` for Gemini's stricter function-declaration schema). `LLM::active()` resolves the configured provider (`Settings::get_provider()`); register more via the **`chatadmin_llm_providers`** filter. Each provider has its own test seam: `chatadmin_{id}_http_response` (mocks: `tests/MockAnthropic|MockOpenAI|MockGemini`).

**No provider picker** — onboarding and Settings have a single API-key field. `LLM::detect($key)` infers the provider from the key prefix (`sk-ant-` → Anthropic, `sk-` → OpenAI, `AIza` → Google), and saving a key sets the active provider + resets the model to that provider's default. Each provider implements `matches_key()`.
5. `Rest` persists the assistant turn and returns it; the React UI renders `tool_calls` output as structured cards.

### Tools — the core abstraction (`includes/class-tools.php`)
- `Tools::definitions()` returns the JSON schemas exposed to the model; `Tools::implementations()` maps tool name → PHP callable. To add a tool, add to **both**.
- Order tools (`list_orders`, `get_order`, `update_order_status`, `add_order_note`, `find_customer_orders`, `list_order_actions`, `trigger_order_action`) call WC functions directly (no REST roundtrip). Each takes a single id — bulk destructive ops are intentionally impossible (a sold safety feature, enforced in the system prompt).
- `get_admin_url` is the "smart handoff" — when no tool can do the job, the assistant returns a deep link instead of refusing.
- Content tools (`list_content_blocks`, `preview_content_change`, `apply_content_change`) route through the content-backend system. **Two-step preview→apply is mandatory** and enforced by the system prompt; confirmation phrases are validated by `ContentConfirmation::is_confirmed` (multilingual whitelist).
- Content-creation tools (`list_taxonomy_terms`, `create_content`, `publish_content`) create posts/pages **draft-first**: `create_content` builds a draft (categories/tags by name, `featured_image`/`image_ids` from upload markers, optional `seo_title`/`seo_description`), and `publish_content` publishes it only after a multilingual confirmation phrase. `list_taxonomy_terms` is called first to reuse existing terms.
- SEO/analytics tools (`seo_audit`, `get_traffic_summary`) are read-only. `seo_audit` (`class-seo.php`) returns a structured SEO/AEO/GEO report with `fixable` flags that route to the `seo_setting`/`seo_meta` content kinds; `get_traffic_summary` reads from the analytics-providers system over a `date_range` enum.

> Note: there is **no "skills" registry** — the commit-message word "skills" just means chat capabilities implemented as Anthropic tools + content backends following the patterns above.

### The system prompt is dynamic (`Rest::system_prompt`)
It is rebuilt per-request and encodes most of the product behavior: the live WC order-status list, a multilingual status→slug map, the registered content kinds (filtered by site-disabled kinds and the current user's caps), guardrails, and language rules. **Behavioral changes usually mean editing this prompt, not adding code.** Scenario tests in `tests/Scenarios/` lock in these behaviors.

### Extensibility via filters (the key design pattern)
- `chatadmin_content_backends` — register `ContentBackend` implementations (`includes/class-content-backends.php`). The default `WPContentBackend` handles `wp_post` / `wp_page_slug` / `wp_post_meta` / `wp_term`. `ContentRouter` dispatches each tool call to whichever backend claims the target `kind`. Custom backends (e.g. a `team_member` kind writing static HTML) live in separate site plugins.
- `chatadmin_analytics_providers` — register `AnalyticsProvider` implementations (`includes/class-analytics-providers.php`). `AnalyticsRouter` auto-detects the first available host plugin (Site Kit, Jetpack Stats, MonsterInsights, WP Statistics, Koko Analytics, Statify).
- `chatadmin_llm_providers` — register `LLMProvider` implementations (`includes/class-llm-providers.php`). Defaults are Anthropic / OpenAI / Gemini; add a custom one (e.g. a self-hosted proxy) by appending to the filter. `LLM::active()` picks the configured provider.
- `chatadmin_anthropic_http_response` — returning non-null short-circuits the real HTTP call. **This is the test seam** — `tests/MockAnthropic.php` enqueues scripted Anthropic responses so real tools run against real WP while the LLM is deterministic and free.

### Per-kind capability gating
Content edits are gated at three layers: hidden from the system prompt if disabled or the user lacks the cap (`Tools::user_can_edit_kind`), and re-checked on dispatch in `Tools::apply_content_change` (`check_kind_access`). REST permission is `manage_woocommerce || edit_shop_orders` for chat, `edit_posts` for the page.

### Optional, off-by-default integrations (gated by wp-config constants)
- `CHATADMIN_ANTHROPIC_API_KEY` — overrides the DB-stored key (`Settings::get_api_key` prefers the constant).
- `GitSync` (`includes/class-git-sync.php`) — `CHATADMIN_GIT_SYNC_ENABLED` + `CHATADMIN_GIT_SYNC_PATH`: commit+push files mutated by file-writing backends. No-ops gracefully when prerequisites are missing.

### Other components
- `History` (`includes/class-history.php`) — custom `{prefix}chatadmin_messages` table; conversations grouped by a 30-min idle gap. `History::migrate()` runs on activation and in the test bootstrap.
- `Onboarding` (`includes/class-onboarding.php`) — first-run wizard; its REST routes set API key, model, provider choice, and site-disabled kinds. `Onboarding::should_show_for_user` decides `boot.mode`.
- `Upload` (`includes/class-upload.php`) — `chatadmin/v1/upload`, image-only (JPEG/PNG/WebP ≤10MB), mime-sniffed via finfo. Returns an attachment id the chat references via an `[Uploaded … → attachment N]` marker line.
- `Seo` / `SeoBackend` (`includes/class-seo.php`) — the SEO/AEO subsystem (no REST routes). `Seo` powers the `seo_audit` tool, hooks `robots_txt` to open the site to AI answer-engine crawlers (GPTBot/ClaudeBot/PerplexityBot, opt-in via the `chatadmin_seo_allow_ai_crawlers` option), and serves a virtual `/llms.txt` on `init` (from the `chatadmin_llms_txt` option — no filesystem write). `SeoBackend` registers via the `chatadmin_content_backends` filter and adds two editable kinds: `seo_setting` (site options like visibility/permalinks/title/tagline, cap `manage_options`) and `seo_meta` (per-post title/meta-description via the active SEO plugin — Yoast / Rank Math / SEOPress, cap `edit_posts`). Both flow through the standard preview→apply pipeline.
- Direct-action REST routes (`chatadmin/v1/actions/order/...`) bypass the LLM entirely — the order-table 3-dot menus mutate status/notes via `Tools` methods directly, with zero API spend.

### Frontend notes
- React 19 + Vite 8 + Tailwind v4 + shadcn/ui (`app/src/components/ui/`) + Framer Motion (`motion`); `@` aliases `app/src/`. The page is always dark mode. Markdown via `react-markdown` + `remark-gfm`.
- Key files: `Chat.tsx`, `OrdersTable.tsx`, `HistoryDrawer.tsx`, `QuickChips.tsx`, and `Onboarding/` (`Wizard.tsx` + `cards/`).
- `OrdersTable` is the **only** rich card — all other tool output (SEO audits, traffic, content/taxonomy results, draft confirmations) renders as plain markdown. The Confirm/Cancel bar is generic and name-prefix-driven (appears when tool calls include a `preview_*` with no following `apply_*`), so new preview kinds need no React changes.

## Conventions
- PHP 8.1+ (typed properties, enums, `str_starts_with`). All classes are namespaced `ChatAdmin\`; every file starts with the `if (!defined('ABSPATH')) exit;` guard.
- Status slugs are stored WC-prefixed (`wc-completed`) but tools/prompt use unprefixed (`completed`) via `Tools::unprefixed_status`. Custom statuses (e.g. `panaudotas`) are auto-discovered from `wc_get_order_statuses()`.
- Multilingual throughout (LT/RU/PL/EN) — UI strings, confirmation whitelists, and status mapping all expect non-English input; preserve this when editing.
