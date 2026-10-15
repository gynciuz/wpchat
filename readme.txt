=== ChatAdmin – AI chat admin ===
Contributors: chatapp
Tags: woocommerce, chat, ai, claude, orders
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.8.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Run your WooCommerce store and WordPress site by chatting: orders, content, SEO and traffic, in any language.

== Description ==

ChatAdmin adds a chat assistant for shop owners and editors. Type a request
in plain language — "mark order 2833 completed and add a note that the
customer spent 30€ of 100€" — and the assistant calls the matching
WooCommerce or WordPress function and shows the result inline. It opens at
yoursite.com/chatadmin (full screen) and under ChatAdmin in wp-admin.

What it can do:

* **Orders (WooCommerce)** — list and filter orders, open an order, change
  its status, add private or customer-visible notes, find a customer's
  orders, and run WooCommerce order actions such as resending an email.
* **Content** — create posts and pages as drafts (with categories, tags,
  featured and inline images), publish them on your confirmation, and edit
  titles, text, excerpts, custom fields and category or tag descriptions.
  Every edit is shown as a preview first and applied only after you confirm.
* **SEO** — audit the site's search and AI-answer-engine readiness, and set
  per-page SEO titles and meta descriptions through Yoast SEO, Rank Math or
  SEOPress.
* **Traffic** — summarise visits from an analytics plugin you already use
  (Jetpack Stats, WP Statistics, Koko Analytics).
* **Images** — upload a JPEG, PNG or WebP image in the chat and use it in a
  post.
* **Everything else** — when no tool fits, the assistant gives you a direct
  link to the right wp-admin screen instead of refusing.

What it will not do: bulk or delete operations. Every change touches one
order or one item at a time, and nothing is deleted from chat, by design.

What a user can do follows their WordPress role. ChatAdmin never grants
capabilities: an editor without order access can edit content but cannot
see orders. Text the assistant writes into your site is filtered with
WordPress's own HTML sanitizer, so scripts are never stored, whatever the
user's role.

You bring your own API key from Anthropic (Claude), OpenAI or Google
Gemini, and pay that provider directly for usage.

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
3. Follow the first-run setup, or go to ChatAdmin → Settings, and paste an API key from Anthropic, OpenAI or Google Gemini.
4. ChatAdmin → Chat → type.

== Frequently Asked Questions ==

= Do I need an account or subscription? =
No. ChatAdmin is free and open-source. You bring your own API key
(Anthropic, OpenAI or Google Gemini) and pay that provider directly for usage. There is no ChatAdmin subscription
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

= 0.8.0 =
Changes from the WordPress.org review. No change to what the assistant can do.
* **Chat-written content is always filtered.** Posts, pages, excerpts, custom fields, term descriptions and SEO fields written from chat now pass through WordPress's HTML sanitizer for every user, including administrators, so a script can never be stored.
* **Image upload needs the media permission.** Uploading in chat now requires WordPress's "upload files" capability, and editors who can upload media can now attach images to the drafts they create.
* **Assets load the WordPress way.** The /chatadmin page and the admin screens now load their scripts and styles through the WordPress enqueue system. The /chatadmin page also uses your site's language setting.
* Readme rewritten to describe every feature.

= 0.7.14 =
Packaging and disclosure work for the WordPress.org submission. No change to how the plugin behaves once configured.
* **Error reporting is now off until you turn it on.** Anonymous error reports previously defaulted to on for a fresh install. They now stay off until you tick the box in Settings → Privacy & diagnostics, so nothing leaves your site unless you ask for it.
* **Every AI provider is now disclosed in readme.txt.** The new "External services" section lists Anthropic, OpenAI and Google Gemini with their endpoints, what data is sent and when, and links to each provider's terms and privacy policy.
* **The React source ships with the plugin.** app/src/ and the build configuration now travel alongside the compiled bundle in build/assets/, with build instructions in readme.txt.
* Changelog trimmed to the recent releases; the full history moved to changelog.txt. Dropped finfo_close(), deprecated in PHP 8.5.

= 0.7.13 =
* **Removed the built-in Git auto-commit (GitSync) integration.** Committing site files after a chat edit is a site-specific concern, not something the plugin should carry. A site whose custom content backend writes files and needs to commit them now does so from inside its own backend (registered via the `chatadmin_content_backends` filter), rather than through a plugin-provided helper. This has no effect on a standard install — GitSync was an optional, off-by-default power-user feature that no default content backend used.

= 0.7.12 =
* **Fix: "critical error" on the Plugins screen (Class "Parsedown" not found).** The release ZIP was built without the update checker's bundled Parsedown library, because a broad `vendor/` ignore rule had kept it out of version control. When WordPress ran the GitHub update check on a release with notes, the plugin fataled and could take down wp-admin/plugins.php. The library now ships with the plugin.
* **Fix: content-edit confirmation loop that could exhaust API credits.** Confirming an edit to two things at once (e.g. two pages), or the assistant re-showing a preview on the same turn you confirmed, could leave the change never applying — the assistant kept re-asking "confirm?" and burning API calls. Pending confirmations are now tracked per target and each confirms independently, while the same-turn security guard is preserved. Also accepts the Lithuanian "tinka" as a confirmation.

= 0.7.11 =
* **Fix: "critical error" after updating on some sites.** Sites that carried the plugin through its earlier folder rename (wpchat → chat-admin) could end up with two copies in the plugins directory; when both loaded, WordPress fataled with "Cannot redeclare class" and showed "There has been a critical error on this website" right after an update. The plugin now detects a second copy and bails harmlessly so the site stays up. To fully resolve it, delete the stale duplicate plugin folder under wp-content/plugins/ (typically a leftover "wpchat" folder alongside "chat-admin"), keeping only the active one.

Older releases are listed in changelog.txt, shipped with the plugin, and at
https://github.com/gynciuz/wpchat/releases
