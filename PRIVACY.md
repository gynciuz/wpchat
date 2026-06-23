# WPChat — Privacy & Data Handling

_Last updated: 2026-06-22 · Applies to WPChat the WordPress plugin._

WPChat is an admin assistant that runs on **your** WordPress site. This
document explains exactly what data leaves your site, where it goes, and
what is stored. In plain terms: WPChat sends the content of your chat
requests to Anthropic to generate replies, keeps your conversation
history on your own site, and — only if you opt in or explicitly ask —
sends limited diagnostics to the plugin developer.

## What is sent to Anthropic (the AI provider)

To answer a request, WPChat sends the content of your conversation to
**Anthropic's Claude API** (`https://api.anthropic.com`). This can
include:

- the text you type into the chat;
- data the assistant reads to fulfil the request — for example **order
  details, customer names, emails, and addresses**, post/page content,
  and SEO fields;
- images you upload via the chat.

This data is processed by Anthropic under **Anthropic's Commercial Terms
and Privacy Policy** (see <https://www.anthropic.com/legal/privacy> and
<https://www.anthropic.com/legal/commercial-terms>). Per Anthropic's
commercial terms, API inputs/outputs are **not used to train their
models**. WPChat sends data to Anthropic only when you send a chat
message; nothing is sent in the background.

Your **Anthropic API key** is stored in your site (in the
`wpchat_settings` option, or in the `WPCHAT_ANTHROPIC_API_KEY` constant
in `wp-config.php`). It is sent only to Anthropic as the authentication
header and is **never exposed to the browser/frontend**.

## What WPChat stores on your own site

- **Conversation history** — your messages and the assistant's replies
  are saved in a table in your site's database
  (`{prefix}wpchat_messages`), scoped to the WordPress user who sent
  them. This never leaves your site (except the parts sent to Anthropic
  as above). Deleting the plugin's data removes it.
- **Recent error log** — the last ~50 errors are kept in a site option
  (`wpchat_error_log`) for troubleshooting. No order or customer data.

## What is sent to the plugin developer

WPChat has two narrow, clearly-scoped channels back to the developer.
Neither sends order or customer data automatically.

1. **Error telemetry (optional, on by default, toggle in WPChat →
   Settings → Privacy & diagnostics).** If enabled, when a failure
   occurs WPChat sends a **PII-free** report: the error message, the
   plugin/PHP/WordPress versions, your site's hostname, and the name of
   the tool that failed. **No order data, no customer data, no
   conversation content.** You can turn this off at any time.

2. **"Report a problem" (only when you click it).** When you submit a
   report from the Help panel, WPChat sends your note, the recent
   conversation, the error you hit, and your WordPress login/email so
   the developer can help. This is **explicit and user-initiated** —
   nothing is sent unless you press the button.

If you join the **WPChat Cloud waitlist**, the email you provide and
your site URL are sent to the developer so you can be notified when that
tier opens.

## What WPChat does NOT do

- No tracking, advertising, or analytics of your site's visitors.
- No selling or sharing of data with third parties other than Anthropic
  (to provide the AI) and, only as described above, the plugin
  developer.
- No background "phone home" of your content.

## Your responsibilities as a site operator (GDPR / privacy law)

Because WPChat can send **personal data contained in your orders/content
(e.g. customer names and emails) to Anthropic**, if you operate in a
jurisdiction with data-protection law (such as the EU/UK under GDPR)
you are responsible for:

- disclosing this processing in **your own** site privacy policy;
- ensuring you have a lawful basis to share that data with a processor;
- reviewing Anthropic's terms / DPA for your use case.

WPChat is provided as free, open-source software (MIT). It collects no
payment and stores no payment details.

## Questions

Open an issue at <https://github.com/gynciuz/wpchat> or use the "Report
a problem" button inside the plugin.
