=== ChatAdmin – AI chat admin ===
Contributors: chatapp
Tags: woocommerce, chat, ai, claude, orders
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.7.13
License: MIT
License URI: https://opensource.org/licenses/MIT

Chat-based admin for WooCommerce orders, powered by Anthropic Claude.

== Description ==

Type "mark order 2833 used, customer spent 30€ of 100€" in the WP admin
sidebar — the ChatAdmin assistant calls the right WC functions and renders
rich UI inline.

Phase 1 (MVP):

* List orders with status / search / date filters
* Get full order detail
* Update order status (with optional note in one round-trip)
* Add order notes (private or customer-visible)
* Find orders by customer email or name

Bring your own Anthropic API key.

== External services ==

ChatAdmin relies on a third-party AI provider to answer your requests. You
choose the provider by pasting its API key; ChatAdmin detects which one the
key belongs to and talks only to that provider. Nothing is sent anywhere
until you have saved a key and typed a request.

**AI provider (one of the three below — whichever key you supply)**

What is sent: the text of your chat request, the conversation so far, and
the results of any WordPress or WooCommerce lookup the assistant performs
to answer you. When you ask about orders or customers, that data (names,
email addresses, addresses, order contents) is part of what is sent.
When it is sent: on each message you submit in the chat.

* Anthropic — endpoint api.anthropic.com. Terms: https://www.anthropic.com/legal/commercial-terms — Privacy policy: https://www.anthropic.com/legal/privacy
* OpenAI — endpoint api.openai.com. Terms: https://openai.com/policies/business-terms/ — Privacy policy: https://openai.com/policies/privacy-policy/
* Google Gemini — endpoint generativelanguage.googleapis.com. Terms: https://ai.google.dev/gemini-api/terms — Privacy policy: https://policies.google.com/privacy

**Error reporting to the plugin developer (off by default)**

What is sent: a PII-free failure summary — event name, error message, the
tool that failed, plugin/PHP/WordPress versions and your site host. No
order, customer or conversation content.
When it is sent: only when something fails, and only if you have ticked
"Send anonymous error reports" in Settings → Privacy & diagnostics. This
setting is off on a fresh install and nothing is transmitted until you
turn it on.

**"Report a problem" button (only when you press it)**

What is sent: your recent conversation, which can include customer data,
plus your login name and email, so the developer can reproduce the fault.
When it is sent: only on that explicit button press, never automatically.

== Privacy ==

Your conversation history is stored only in your own site's database, and
your API key is never exposed to the browser. Anything leaving your site is
listed in the External services section above. If you operate under GDPR or
similar, disclose the AI-provider processing in your own site's privacy
policy. See PRIVACY.md for full details.

== Installation ==

1. Upload the plugin ZIP via Plugins → Add New → Upload.
2. Activate.
3. ChatAdmin → Settings → paste your Anthropic API key.
4. ChatAdmin → Chat → type.

== Frequently Asked Questions ==

= Do I need an account or subscription? =
No. ChatAdmin is free and open-source. You bring your own Anthropic API key
and pay Anthropic directly for usage. There is no ChatAdmin subscription
today (a hosted "ChatAdmin Cloud" tier is on a waitlist).

= How do I get an Anthropic API key? =
Go to console.anthropic.com → sign in → Settings → API Keys → Create Key,
then paste it into ChatAdmin's first-run setup or ChatAdmin → Settings. Keys
start with "sk-ant-". ChatAdmin validates the key when you save it.

= How much does it cost to run? =
You pay Anthropic for the tokens each chat uses — typically a few cents
per request, depending on the model you pick (Haiku is cheapest, Opus the
most capable). You can see and cap spend in your Anthropic console.

= Does it work without WooCommerce? =
Yes — the content, SEO, image and admin-handoff features work on any
WordPress site. The order tools require WooCommerce.

= Is my data safe? =
Your requests are sent to your chosen AI provider to generate replies and
can include order/customer data; your conversation history stays on your
own site. Error reporting to the developer is off unless you turn it on.
See the External services and Privacy sections above, and PRIVACY.md.

= Where is the unminified source for the JavaScript? =
The chat interface is a React app. Its readable source ships inside this
plugin under app/src/, alongside the build configuration (app/package.json,
app/vite.config.ts, app/tsconfig*.json). build/assets/main-*.js is the
compiled output of exactly that source.

To rebuild it yourself:

    cd app
    pnpm install
    pnpm build      # tsc -b && vite build → writes ../build/

Node 20+ and pnpm are required. The full project, including tests and
history, is public at https://github.com/gynciuz/wpchat

= Something isn't working — how do I get help? =
Open the Help panel in the chat (footer) — it answers common questions,
and "Report a problem" sends the details to the developer.

== Screenshots ==

1. Ask in plain language — ChatAdmin runs the real WooCommerce/WordPress action and shows the result inline.
2. Rich order cards: list, filter, change status, add notes — with a confirmation step before anything reaches a customer.
3. A two-minute first-run wizard gets the plugin ready for your site.

== Changelog ==

= 0.7.13 =
* **Removed the built-in Git auto-commit (GitSync) integration.** Committing site files after a chat edit is a site-specific concern, not something the plugin should carry. A site whose custom content backend writes files and needs to commit them now does so from inside its own backend (registered via the `chatadmin_content_backends` filter), rather than through a plugin-provided helper. This has no effect on a standard install — GitSync was an optional, off-by-default power-user feature that no default content backend used.

= 0.7.12 =
* **Fix: "critical error" on the Plugins screen (Class "Parsedown" not found).** The release ZIP was built without the update checker's bundled Parsedown library, because a broad `vendor/` ignore rule had kept it out of version control. When WordPress ran the GitHub update check on a release with notes, the plugin fataled and could take down wp-admin/plugins.php. The library now ships with the plugin.
* **Fix: content-edit confirmation loop that could exhaust API credits.** Confirming an edit to two things at once (e.g. two pages), or the assistant re-showing a preview on the same turn you confirmed, could leave the change never applying — the assistant kept re-asking "confirm?" and burning API calls. Pending confirmations are now tracked per target and each confirms independently, while the same-turn security guard is preserved. Also accepts the Lithuanian "tinka" as a confirmation.

= 0.7.11 =
* **Fix: "critical error" after updating on some sites.** Sites that carried the plugin through its earlier folder rename (wpchat → chat-admin) could end up with two copies in the plugins directory; when both loaded, WordPress fataled with "Cannot redeclare class" and showed "There has been a critical error on this website" right after an update. The plugin now detects a second copy and bails harmlessly so the site stays up. To fully resolve it, delete the stale duplicate plugin folder under wp-content/plugins/ (typically a leftover "wpchat" folder alongside "chat-admin"), keeping only the active one.

= 0.7.10 =
* Internal housekeeping: removed a bundled example and tidied internal references. No functional change.

= 0.7.9 =
Groundwork so ChatAdmin stays universal and grows into whatever a site installs it on.
* **`find_text` now covers custom content backends too.** A site plugin that registers its own content backend (via the `chatadmin_content_backends` filter) can implement an optional `search()` method, and its content becomes findable through `find_text` alongside posts, meta and terms — so the assistant catches it organically, with no change to ChatAdmin's core.
* **No more phantom Confirm/Cancel bar.** The Confirm/Cancel buttons now appear only when a preview actually produced a change to confirm. Previously a *failed* preview (e.g. the assistant couldn't edit the item and handed you a wp-admin link instead) still showed the bar, offering to "confirm" a change it had already given up on.

Older releases are listed in changelog.txt, shipped with the plugin, and at
https://github.com/gynciuz/wpchat/releases
