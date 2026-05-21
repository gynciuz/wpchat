=== WPChat ===
Contributors: gynciuz
Tags: woocommerce, chat, ai, claude, orders
Requires at least: 6.5
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 0.3.1
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
