# WPChat

Chat-based admin for WooCommerce. Type *"mark order 2833 used, customer
spent 30€ of 100€"* in your WordPress site's chat at `/wpchat` and watch
it happen — the assistant calls the right WC functions, renders rich
cards (orders table, confirm buttons, image previews) inline, and writes
back to your site.

Built with PHP 8.1+ · React 19 · Tailwind v4 · shadcn/ui · Anthropic
Claude. MIT-licensed. Auto-updates from GitHub Releases (no WP.org
listing required).

## Install

1. Download the latest **`wpchat-vX.Y.Z.zip`** from
   [Releases](https://github.com/gynciuz/wpchat/releases).
2. WP admin → **Plugins → Add New → Upload Plugin** → choose the ZIP →
   **Install Now** → **Activate**.
3. Sidebar → **WPChat → Settings** → paste your Anthropic API key from
   [console.anthropic.com](https://console.anthropic.com).
4. Visit **`https://your-site.example/wpchat`** → start chatting.

## Auto-updates

The plugin uses [Plugin Update Checker (PUC)](https://github.com/YahnisElsts/plugin-update-checker)
bundled under `vendor-puc/` to track this repository's GitHub Releases.
Within ~12 hours of a new release being published (or on a manual
"Check Again" in wp-admin → Plugins → Updates), the standard WordPress
update flow appears — one-click **Update Now** pulls the latest release
ZIP. No SSH, no SFTP, no GitHub Actions on your side.

The `Update URI` header in the plugin metadata pins update lookups to
this repository, so a future plugin on the WP.org directory with the
same slug can't silently hijack your update channel.

## Requirements

- WordPress 6.5+
- PHP 8.1+ (the plugin uses typed properties, enums, and `str_starts_with`)
- WooCommerce active (for the order tools — non-WC sites can still use
  the content / image-replacement / admin-handoff features)
- An Anthropic API key

## Pricing

WPChat itself is **free and open-source (MIT)**. You **bring your own
Anthropic API key** and are billed directly by Anthropic for the tokens
your chats use — WPChat collects no payment and stores no card details,
so there is nothing to refund. A hosted **WPChat Cloud** tier (no key
setup required) is on the roadmap as a waitlist; pricing will be
announced if and when it launches.

## What it does

**Out of the box on any WP+WC install:**

- **Orders** — list, filter, search, get detail, change status, add
  note. Custom statuses (e.g. `panaudotas`) are auto-discovered from
  `wc_get_order_statuses()`. Direct 3-dot inline actions on order
  tables bypass the LLM entirely (zero API spend for routine status
  changes).
- **Content editing** — change any `wp_post` / `wp_page_slug` /
  `wp_post_meta` / `wp_term` via the chat with two-step preview + confirm
  buttons. Localized confirmation phrases (`taip` / `gerai` / `да` /
  `tak` / `confirm` etc.) or one-tap **Patvirtinti / Atšaukti** /
  **Подтвердить / Отмена** / **Potwierdź / Anuluj** / **Confirm / Cancel**
  buttons.
- **Media** — upload an image via the inline `+` button, replace
  references via the same preview/confirm flow. JPEG / PNG / WebP, ≤10 MB.
- **Smart handoff** — when something can't be done via tools, the
  assistant returns a deep link to the relevant wp-admin page instead of
  dead-ending ("I can't…").

**Extensible via filters:**

- `wpchat_content_backends` — register custom content kinds for your
  site (e.g. a `team_member` backend that writes to static HTML files,
  a `testimonial` backend that updates an ACF repeater).
- Cache purge + git-commit-on-write hooks — see the `WPChat\CachePurge`
  and `WPChat\GitSync` helpers; gated behind wp-config constants so
  default behavior is pure-WP.

## Dev

```bash
# React app
cd app/
pnpm install
pnpm build     # outputs to ../build/
pnpm dev       # vite dev server (use with `pnpm build --watch` for live mount)

# PHP test suite (requires MySQL + WP test scaffold)
composer install
bin/install-wp-tests.sh wpchat_tests root '' 127.0.0.1 latest
composer test
```

## Releases

We tag every release on this repo and attach a `wpchat-vX.Y.Z.zip` to
the GitHub Release. The PUC auto-update mechanism reads this asset; no
WP.org submission needed today. WP.org listing is planned for ~v0.7 once
the plugin has more public users.

## Roadmap

See the [changelog in `readme.txt`](./readme.txt) for shipped releases.
Highlights:

- **v0.5.x** (done) — image upload + photo replacement, first-run
  onboarding wizard, analytics provider router (Site Kit / Jetpack /
  WP Statistics / …), SEO/AEO audit + fixes, content creation.
- **v0.6.0** (this release) — order-mutation confirmations, in-product
  Help + "Report a problem", error telemetry, live API-key validation,
  privacy disclosure, packaging + CI guards.
- **Next** — multiple LLM providers (OpenAI / Gemini behind a provider
  abstraction), hosted **WPChat Cloud** tier (Stripe), more guided
  handoffs (comments, broken links).
- **v0.7+** — WP.org plugin directory submission, more content kinds.

## Privacy & data handling

WPChat sends the content of your chat requests — which can include **order
and customer data** — to **Anthropic** (`api.anthropic.com`) to generate
replies. Your conversation history is stored only in your own site's
database; your API key is never exposed to the browser. Optional,
PII-free error telemetry (on by default, toggle in **Settings → Privacy
& diagnostics**) and an explicit "Report a problem" button are the only
channels back to the developer.

If you operate under GDPR or similar, disclose this processing in your
own privacy policy. Full details: **[PRIVACY.md](./PRIVACY.md)**.

## License

MIT — see [LICENSE](./LICENSE).
