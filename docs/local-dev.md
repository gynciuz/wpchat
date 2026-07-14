# Local testing rigs

Two ways to run ChatAdmin locally.

## 1. UI-only preview (fast, no backend)

Previews the React UI shell — layout, onboarding cards, provider tiles,
localization. **REST calls 404** (no WordPress), so chatting / saving don't run.

```sh
cd app
pnpm install
pnpm dev            # serves the dev harness (app/index.html, export-ignored)
```

Open the printed URL. Query params:
- `?mode=onboarding` — the onboarding wizard
- `?locale=lt|ru|pl|en` — localized UI

## 2. Full WordPress rig (real, works end-to-end) — `wp-env`

Spins up real WordPress + WooCommerce with the plugin mounted, so the chat,
onboarding, orders, and tools actually work. **Requires Docker Desktop running.**

```sh
# from the repo root
npx @wordpress/env start      # first run downloads images (a few minutes)
```

- Site:  http://localhost:8888/chat-admin   (the chat)
- Admin: http://localhost:8888/wp-admin (login: `admin` / `password`)
- ChatAdmin settings / Diagnostics live under the **ChatAdmin** menu in wp-admin.

First-run setup inside the rig:

```sh
# Activate WooCommerce + ChatAdmin (wp-env activates listed plugins automatically,
# but if needed):
npx @wordpress/env run cli wp plugin activate woocommerce wpchat

# Seed a few test orders so the order scenarios have data:
npx @wordpress/env run cli wp wc product create --name="Test Product" --regular_price=20 --user=admin
npx @wordpress/env run cli wp wc shop_order create --status=processing --user=admin
```

Then visit `/chat-admin`, paste an Anthropic/OpenAI/Gemini key in onboarding, and run
the scenarios in `test-plan.md`.

Useful commands:

```sh
npx @wordpress/env stop                       # stop containers
npx @wordpress/env start                       # restart
npx @wordpress/env clean all                   # wipe + reset the rig
npx @wordpress/env run cli wp option get chatadmin_settings   # inspect saved settings
npx @wordpress/env run cli wp eval 'var_dump(get_option("chatadmin_error_log"));'  # recent errors
```

After changing `app/src/`, run `pnpm build` (outputs to `build/`, which the rig
serves) — no rig restart needed.
