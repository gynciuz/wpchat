# WPChat

Chat-based admin for WooCommerce. Type *"mark order 2833 used, customer
spent 30€ of 100€"* in the WP admin sidebar and watch it happen — the
assistant calls the right WC functions and renders rich cards (OrderCard,
StatusBadge, confirm dialogs) inline. Pure WordPress plugin: upload the
ZIP, activate, paste your Anthropic API key, start chatting.

Built with PHP 8.1+ + React 19 + Tailwind v4 + shadcn/ui + Anthropic Claude.
MIT-licensed.

## Status

**v0.1.0 — scaffold.** Admin menu + settings page + REST endpoint shape
are in place. The chat UI bundle and the order tools are NOT wired yet
(see `~/.claude-personal/plans/snug-chasing-bunny.md` for the plan).

## Install

1. Download the latest release ZIP (or `git clone` + zip the folder).
2. WP admin → Plugins → Add New → Upload Plugin → choose the ZIP.
3. Activate.
4. Sidebar → WPChat → Settings → paste your Anthropic API key
   (https://console.anthropic.com).
5. Sidebar → WPChat → Chat → type.

## Requirements

- WordPress 6.5+
- PHP 8.1+
- WooCommerce active
- An Anthropic API key

## Dev

```bash
cd app/
pnpm install
pnpm build     # produces ../build/chat.js + ../build/chat.css
```

For active UI development:

```bash
pnpm dev       # Vite dev server, HMR — admin page reads from build/ so
               # you'll need to run `pnpm build --watch` for live reload
```

## Scope

**Phase 1 (MVP)** — order management only:

- `list_orders` (status, search, since-date filters)
- `get_order` (full detail)
- `update_order_status` (status + optional note in one round-trip)
- `add_order_note` (private or customer-visible)
- `find_customer_orders` (by email or name)

Custom statuses (like Gentleman's Empire's `wc-panaudotas`) are
auto-discovered from `wc_get_order_statuses()` — no per-site code.

Reports, content editing, media, and team-roster management are
explicitly out of scope for v0.

## License

MIT — see [LICENSE](./LICENSE).
