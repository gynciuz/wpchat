=== WPChat ===
Contributors: gynciuz
Tags: woocommerce, chat, ai, claude, orders
Requires at least: 6.5
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 0.4.7
License: MIT
License URI: https://opensource.org/licenses/MIT

Chat-based admin for WooCommerce orders, powered by Anthropic Claude.

== Description ==

Type "mark order 2833 used, customer spent 30€ of 100€" in the WP admin
sidebar — the WPChat assistant calls the right WC functions and renders
rich UI inline.

Phase 1 (MVP):

* List orders with status / search / date filters
* Get full order detail
* Update order status (with optional note in one round-trip)
* Add order notes (private or customer-visible)
* Find orders by customer email or name

Bring your own Anthropic API key.

== Installation ==

1. Upload the plugin ZIP via Plugins → Add New → Upload.
2. Activate.
3. WPChat → Settings → paste your Anthropic API key.
4. WPChat → Chat → type.

== Changelog ==

= 0.4.7 =
* Microphone UX overhaul (B5). The persistent red "Microphone access denied" banner that used to eat ~25% of mobile screen real estate is gone. Replaced with a dismissible muted toast that auto-clears after 6 seconds. The toast has its own X button. When the browser reports microphone permission is permanently denied (or the browser doesn't support voice at all), the mic icon disappears entirely instead of taunting you on every send — a small "balsas išjungtas" hint surfaces in the footer instead. iOS Safari users get an iOS-specific message pointing at Settings → Safari → Microphone (or Settings → WPChat → Microphone if installed as PWA), not the generic browser-address-bar copy that doesn't apply on iOS.
* Quick-action chips (B7). New tappable preset chips above the input bar: "Paskutiniai užsakymai", "Šios savaitės pardavimai", "Lankytojai", "Nepanaudoti kuponai", "Atviros klaidos". One tap pre-fills and auto-sends the matching query — no typing Lithuanian on iOS between clients. Always visible (not gated by empty state). Localized labels + queries for LT / RU / PL / EN. Future polish: swap statics for dev-telemetry-derived top-5 queries per user.

= 0.4.6 =
* Inline 3-dot actions on order tables (no AI involved). When the assistant lists orders (via `list_orders` or `find_customer_orders`), the chat now renders the orders as a structured React table ABOVE the LLM's text reply, with a 3-dot menu per row offering: (a) "Atidaryti WP admin" — deep link to the order in a new tab, (b) "Keisti statusą →" — submenu with every available WC status (including the custom `panaudotas`). One tap changes the status with zero Anthropic API spend and zero hallucination risk.
* Status changes happen via new direct-action REST endpoints (`POST /wpchat/v1/actions/order/{id}/status`, `POST /wpchat/v1/actions/order/{id}/note`, `GET /wpchat/v1/actions/order-statuses`) that share the chat permission check (manage_woocommerce / edit_shop_orders) but bypass the LLM entirely. The row re-renders in place with the new status badge after a successful change.
* New `boot.siteUrl` exposed in the frontend bootstrap payload so the table can construct correct HPOS-aware admin URLs without round-tripping through the LLM.
* Scenario test DirectActionsTest locks the architecture: direct-action endpoints work even when no Anthropic key is configured, never invoke the LLM, and require the same caps as the chat route.

= 0.4.5 =
* Fix "the page is static HTML, you need FTP" dead-end refusal. The LLM was reading the team_member backend's self-description as a reason to give up instead of as the tool that does the job. System prompt now has a "Discover before giving up — MANDATORY" section that requires the assistant to call list_content_blocks for every plausible kind before saying it can't help. Explicit ban on refusing a content edit on static-HTML grounds: if a registered kind handles the location, USE IT.
* Intent→kind mapping baked into the prompt: person/barber/master → team_member; page section → wp_page_slug; post → wp_post; setting → wp_post_meta; category/tag → wp_term.
* Scenario test DiscoverBeforeRefusingTest locks these rules so the regression can't reappear silently.

= 0.4.4 =
* Confirm / Cancel BUTTONS replace typed confirmation phrases. When the assistant previews a content change (preview_content_change tool call), the chat UI now renders localized `[Patvirtinti]` / `[Atšaukti]` (LT) / `[Подтвердить]` / `[Отмена]` (RU) / `[Potwierdź]` / `[Anuluj]` (PL) / `[Confirm]` / `[Cancel]` (EN) buttons under the assistant bubble. One tap applies or cancels — no more typing "taip" and getting rejected because you said "gerai" instead.
* Expanded affirmative whitelist for users who DO type: `gerai`, `sutinku`, `okay`, `sure`, `хорошо`, `ок`, `dobrze` are now all accepted alongside the previous yes/taip/patvirtinu/да/tak/ok/apply/confirm/do-it.
* Token-based matching instead of substring matching — fixes the bug where `negerai` (Lithuanian "not good") would have accidentally matched `gerai` ("good") via substring search. Now matches whole words only, AND explicitly rejects negations (`ne`, `нет`, `не`, `no`, `nie`, `cancel`, `atšaukti`, `negerai`) anywhere in the input, even if an affirmative is also present (mixed-signal inputs like "ne, taip" are rejected — user should retry clean).
* System prompt updated — assistant is told to describe the change in the user's language but NOT to demand a specific confirmation word; the buttons handle that.

= 0.4.3 =
* Bump base font on the `/wpchat` route from 16px → 18px (rem reference). Every Tailwind `text-*` utility scales proportionally, so message text, table cells, footers, and chips all become noticeably more readable on phones. wp-admin embed unchanged.

= 0.4.2 =
* New tool `get_admin_url(resource, id)` — returns the WordPress admin deep link for an order (HPOS-aware via `WC_Order::get_edit_order_url`), post, user, or list view. Lets the assistant hand off via a clickable link instead of dead-ending on "I can't do that".
* System prompt overhaul — adds a "How to be useful" preamble that bans dead-end "I can't" responses, requires concrete next-step handoffs via `get_admin_url`, and forbids hallucinating technical explanations for tool failures.
* Hard guardrails section — explicitly bans bulk destructive operations (no "cancel all", no "delete every", no `*_ids: [array]` ever) and pre-specs the future DELETE-word confirmation pattern for genuinely destructive ops (require typing literal `DELETE` / `IŠTRINTI` / `УДАЛИТЬ` for deletes).
* Multilingual status mapping table — explicit `user word → slug` rows for cancelled / completed / processing / pending / on-hold / refunded / failed / panaudotas, so Lithuanian "atšauktas" / Russian "отменён" / etc. reliably map to the right WC slug. Fixes the hallucinated "config has `ancelled` typo" failure mode seen in production 2026-05-26.
* Status reference now formatted as `\`slug\` → "Label"` for unambiguous parsing.

= 0.4.1 =
* Conversation history persistence — every user + assistant message is stored in a new `{prefix}_wpchat_messages` table (created by `dbDelta` on activation), scoped to the WP user who sent it. Each user only sees their own conversations.
* Auto-grouping by 30-minute idle gap — sending a new message within 30 min continues the previous conversation; after the gap, a new conversation UUID is minted automatically.
* History drawer in the chat UI — toggleable from a left-rail button in the header; shows recent conversations with first-message label, relative timestamp, and message count. Clicking one loads its full history into the chat view.
* "New chat" button in the header — clears the current view and starts a fresh conversation on the next message.
* REST: `POST /chat` now accepts/returns `conversation_id`; new `GET /conversations` and `GET /conversations/{uuid}` endpoints.

= 0.4.0 =
* Generic content-backend dispatch — `list_content_blocks`, `preview_content_change`, `apply_content_change` route to whichever backend claims a given `kind`. Replaces the previous Gentleman's Empire-specific `list_team_members` / `preview_team_member_role_change` / `apply_team_member_role_change` tools (those lived in the plugin and only worked on GE's static-HTML pages).
* New `\WPChat\ContentBackend` interface + default `WPContentBackend` handling `wp_post`, `wp_page_slug`, `wp_post_meta`, `wp_term` via core WP functions (`wp_update_post`, `update_post_meta`, `wp_update_term`).
* Site-specific backends register via the new `wpchat_content_backends` filter. Site code (theme / MU-plugin) implements `\WPChat\ContentBackend` and appends an instance to the filter; the plugin no longer carries any per-site paths or selectors.
* System prompt enumerates available content kinds + editable fields at runtime by walking the registered backends. The two-step preview-then-apply pattern with confirmation-phrase whitelist (yes/taip/да/tak/patvirtinu/...) is centralised in `\WPChat\ContentConfirmation::is_confirmed()` so every backend gates writes identically.

= 0.3.4 =
* Transparent chat container — drop the surrounding card bg/border so the dark page background shows through; keep the structure and message bubbles.
* Markdown rendering for assistant replies — tables, lists, code, bold, links all rendered properly (react-markdown + remark-gfm).

= 0.3.3 =
* Fix tool dispatch crash when invoking no-arg tools (list_team_members): cast stdClass back to array before calling tool function.
* Better voice-error message guiding users to grant microphone permission.

= 0.3.2 =
* Fix Anthropic API error "tool_use.input: Input should be an object" when calling tools with no arguments (list_team_members). Empty PHP arrays now correctly re-serialize as JSON `{}` instead of `[]`.
* Fix light-mode gutter around the /wpchat page — dark class now on `<html>` + `<body>`, dark background matches the chat card.

= 0.3.1 =
* Team-page edit tools live: list_team_members, preview_team_member_role_change, apply_team_member_role_change. Two-step pattern: preview returns diff for both homepage and team page; apply requires confirmation phrase (yes/taip/да/ok/patvirtinu/...).
* Edits ALL occurrences across both pages in one apply call.

= 0.3.0 =
* Public /wpchat URL — full-screen chat for editors + admins (no wp-admin chrome).
* Dark theme by default (shadcn/ui radix-nova).
* Browser voice input (Web Speech API) — Russian / Lithuanian / English picked from user locale.
* Multilingual system prompt — Vlad can write in Russian; replies in Russian; stored content in Lithuanian.
* Design polish (text-wrap balance, tabular nums, concentric radii, staggered enter, subtle exit, icon swap animations, antialiased text).
* Two-step team-page-edit tool definitions ready (preview + apply with confirmation gate). Tool implementations still pending — see plan.

= 0.2.0 =
* Live chat backend: /wp-json/wpchat/v1/chat wired to Claude with tool-use loop.
* 5 order tools: list_orders, get_order, update_order_status, add_order_note, find_customer_orders.
* Tool results visible in collapsible detail under each assistant reply.
* System prompt auto-discovers WC order statuses (custom statuses just work).

= 0.1.0 =
* Initial scaffold: admin menu, settings page, REST endpoint shape.
