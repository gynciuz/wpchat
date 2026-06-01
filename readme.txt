=== WPChat ===
Contributors: gynciuz
Tags: woocommerce, chat, ai, claude, orders
Requires at least: 6.5
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 0.5.9
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

= 0.5.9 =
* **Auto-updates from GitHub Releases — no more SCP-from-Actions.** Vendor Yahnis Elsts' Plugin Update Checker library (PUC, v5.7, MIT) under `vendor-puc/`. Wired in `wpchat.php` to track `gynciuz/wpchat` release assets. Every WP site running the plugin now sees update notifications in **Plugins → Updates** within ~12 h of a release being published (or on manual "Check Again"), and the standard one-click "Update Now" pulls the latest release ZIP. Same flow WP.org plugins use; no submission queue, no review delays.
* New `Update URI: https://github.com/gynciuz/wpchat` header in the plugin metadata pins WordPress's update lookups to this repository — a future plugin on the WP.org directory with the same slug can't silently hijack our update channel. (WP 5.8+ honors this header.)
* This release ships via the existing `install-wpchat.yml` workflow ONE LAST TIME. From v0.5.10 onward, GE (and any other site running the plugin) will auto-update via wp-admin — no GitHub Actions, no SSH, no SCP. The install workflow can be deleted after one verified auto-update cycle.

= 0.5.8 =
* Autolink bare URLs in assistant replies. `analytics.google.com` etc. without an `https://` prefix were rendered as plain text by react-markdown (only `https://` and `www.` prefixed URLs autolink by default in remark-gfm). New preprocessing step wraps bare domain.tld matches in markdown link brackets before passing to react-markdown, so they render as proper `<a>` tags with the existing underline styling. Covers common TLDs (.com .lt .net .org .app .io .dev .ai .co .eu .ru .de .uk .pl .fr .es). Skips text already inside markdown links / inline code / explicit autolinks.
* System prompt updated to tell the LLM to prefer full URLs (`https://…`) for the most reliable rendering — autolink is a fallback for when it doesn't.

= 0.5.7 =
* Mobile order tables scroll horizontally now. Wrapping container gets `overflow-x-auto` and the inner table has a `min-w-[480px]` so the rightmost columns (Statusas badge + 3-dot menu) don't get clipped when the chat column is narrower than the table needs.
* Markdown URLs in assistant replies now have a visible underline at rest (`text-primary` with `decoration-primary/60`), brightening to full primary on hover. Previously the underline only appeared on hover, making links easy to miss.
* (Voice removal from v0.5.6 also lands here — v0.5.6's install was blocked by an install-workflow YAML regression that's now reverted.)

= 0.5.6 =
* Voice / SpeechRecognition feature removed entirely. Browser SpeechRecognition added more friction than value in real usage — the mic permission UX from v0.4.7 + B5 was a series of patches around iOS Safari quirks, and the assistant never actually used voice. MicButton, MicStatusHint, the voice-toast UI, the `listening` / `speechLang` state, and the "balsas išjungtas" / `· en-US` footer tails are all gone. The mic icon is gone from both the empty-state hero and the bottom bar. If voice transcription comes back later it'll route through Anthropic's server-side audio API, not browser SpeechRecognition.

= 0.5.5 =
* QuickChips: underline at-rest opacity cut in half (foreground/60 → foreground/30). Still clearly readable, less visual weight, brightens fully on hover and tap.

= 0.5.4 =
* QuickChips: tap target enforced to >= 44px on every link (Apple HIG / Material guideline). Vertical padding centres the small text inside the larger hit area without making the underline look fat. Horizontal padding widens the per-link hit zone so adjacent links don't conflict on a thumb-tap.

= 0.5.3 =
* QuickChips: underline is now always clearly visible (was too faint). Hover and active (tap) states still brighten both the text and the underline. Tap affordance is obvious at first glance.

= 0.5.2 =
* Input simplified — borderless, with the attach button as a small `+` icon inline on the right side of the input. Standalone paperclip button removed from both the empty-state hero and the messages-mode bottom bar.
* QuickChips simplified — no border, no icon, no background. Underlined text links only. Same locale-aware presets, lighter visual weight that doesn't compete with the input.

= 0.5.1 =
* Empty-state redesign. Old "Pasakyk, ką padaryti" preamble + bullet examples are gone. When the chat is empty (fresh load or after "New chat"), the centre of the screen now shows a single outcome-focused title ("Kokio rezultato siekiate?" / "Какого результата хотите?" / "What outcome do you want?") with the input field directly below it — cursor auto-focused, ready to type. AttachButton, MicButton and Send sit next to the input as usual; QuickChips appear under the form for tap-to-fill shortcuts. The bottom input bar is hidden in this state so the focus is on one clear action.
* As soon as a message lands in the conversation, the layout snaps back to the standard messages-above + input-at-bottom flow.

= 0.5.0 =
* **First slice of v0.5-media — image upload + team_member photo replacement.** Tap the 📎 button in the chat input → native picker → pick a JPG / PNG / WebP. The file uploads to the WP media library before the message is sent; a `[Uploaded foo.jpg → attachment 1234]` marker is silently prepended to the message text so the LLM knows the attachment id, while the user sees a thumbnail next to their bubble. Subsequent preview / apply uses the same confirmation buttons as text edits.
* New `POST /wpchat/v1/upload` REST endpoint (same permission check as /chat). Uses WP core's `wp_handle_upload` + `wp_insert_attachment` + `wp_generate_attachment_metadata` — no custom file handling. Validates: JPG / PNG / WebP only (415 otherwise), max 10 MB (413 otherwise).
* GE-side team_member backend gains a `photo` field. Preview returns old + new image URLs; apply rewrites the `<img src>` attribute in both index.html and musu-meistrai.html. Inherits existing cache-purge + git auto-commit tail for free.
* New components: `AttachButton.tsx`, plus pending-attachment chip + thumbnail rendering in `Chat.tsx`.
* New tests: `UploadTest` (jpeg/png happy path, 415 pdf, 413 oversized, 401/403 subscriber, 400 missing) + `PhotoReplaceTest` (preview returns urls without writing, apply rewrites src, no-confirmation rejected).

= 0.4.10 =
* **CRITICAL FIX — "Unknown status: ancelled" bug present since v0.1.0.** `ltrim($slug, 'wc-')` was used in four places to strip the WooCommerce `wc-` prefix from status slugs. PHP's `ltrim` treats its second arg as a character SET, not a literal prefix, so it also stripped leading "c" / "w" / "-" chars: `cancelled` → `ancelled`, `completed` → `ompleted`, `wc-cancelled` → `ancelled`. This broke every status change involving cancelled/completed via tool calls AND polluted the dropdown's status list returned by `/actions/order-statuses` AND the system prompt's status reference. New `Tools::unprefixed_status()` helper does proper prefix removal. Earlier the resulting error was misdiagnosed as an LLM hallucination — it was real code. UnprefixedStatusTest locks the regression.
* Redundant order-table markdown removed from assistant replies. When `list_orders` or `find_customer_orders` runs, the chat UI already renders a structured React `<OrdersTable>` above the assistant's prose. System prompt now explicitly instructs the LLM to write a short prose summary only (not a markdown reproduction). Defensive: the frontend also strips any GFM markdown table the model still emits in that context, so the user never sees the same data twice.

= 0.4.9 =
* Fix double-HTML-encoding in the `/wpchat` page title and chat header. Sites where `blogname` is stored already entity-encoded (e.g. "Gentleman&#039;s Empire") were rendering "Gentleman&#039;s Empire" literally in the browser tab title and the chat header subtitle. Decode entities first, then escape once.

= 0.4.8 =
* New `\WPChat\GitSync` helper. Site backends that mutate tracked files (e.g. the GE team_member backend writing to static HTML) can call `GitSync::commit_files($paths, $message, $author)` after a successful write to commit + push automatically. Without this, WPChat-originated edits would sit uncommitted on prod and silently disappear on the next disaster-recovery reset (which is what happened during the 2026-05-26 reconcile incident).
* Opt-in by design — gracefully no-ops with a clear `skipped_reason` when `WPCHAT_GIT_SYNC_ENABLED` is not set to true in wp-config.php. Operator sees "(Git auto-sync skipped: …)" in the chat assistant's success message, never silent.
* `proc_open` with argv arrays (no shell invocation, no escaping bugs). Concurrent writes serialise via `flock` against a lockfile so two rapid edits don't race. Push failure is surfaced as a distinct error from commit failure — the commit lands locally even if push can't reach origin, and the response reflects that.
* GE backend wired in: a successful team_member apply now commits + pushes the changed HTML files with the WP user's display name + email as commit author. Defaults to bot identity if the WP user has no email.
* GitSyncTest covers: opt-in default-off behaviour, commit + push happy path, idempotent no-op when file is unchanged, rejection of files outside repo root, and the commit-succeeded-push-failed split error.

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
