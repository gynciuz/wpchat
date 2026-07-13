# WPChat Cloud — billing & hosted-proxy design

_Spec · 2026-07-13 · status: scope-and-discuss, awaiting sign-off_

## Summary

WPChat Cloud is the paid tier: €12/mo, no API key required, Sonnet 4.6 by default. A subscriber pastes a **license key** (exactly like today's API-key field) and the plugin routes chat through a **hosted proxy we control** (an extension of the existing Cloudflare Worker in `cloud/`). The proxy validates the license against the subscriber's Stripe subscription, enforces a ~150-chat/mo fair-use cap, injects **our** Anthropic master key server-side, forwards to `api.anthropic.com`, and meters usage. Neither the browser nor the WordPress site ever holds our master key.

The design leans hard on existing seams so the plugin change is tiny and offline-testable:
- A new `CloudProvider extends AnthropicProvider` overriding only `endpoint()` + `headers()` — `build_request`/`parse_response` are reused verbatim because the proxy speaks the native Anthropic `/v1/messages` contract.
- License storage reuses the `wpchat_settings` option + the `WPCHAT_{PROVIDER}_API_KEY` constant-precedence pattern (`WPCHAT_CLOUD_LICENSE`).
- A `wpchat_cloud_http_response` test seam + `tests/MockCloud.php` mirror the `wpchat_anthropic_http_response` pattern, so Cloud is deterministic and free in tests.

## Decisions (with rationale)

### D1 — Stripe: Checkout Session (`mode: 'subscription'`) + Customer Portal, not Payment Element
- **Checkout Session** is hosted by Stripe, handles SCA/3DS, VAT/Tax, dunning, and payment-method selection dynamically. We never render a card form, so PCI scope stays minimal and there's no React billing UI to build. Payment Element would only matter if we wanted an embedded in-app form — not worth it for a single €12 plan.
- **Customer Portal** gives self-serve cancel / update-card / view-invoices with zero code. The plugin's "Manage billing" button just deep-links to a Portal session the proxy mints.
- **Never pass `payment_method_types`** — omit it so Stripe shows dynamically-eligible methods (per Stripe best-practices). Use **Billing APIs + Prices** (not the deprecated `plan` object).
- One **Product** "WPChat Cloud" + one **recurring Price** €12/mo. Consider a `trial_period_days` (open question). Stripe account is live ("Loupe", `acct_1Ts8WKHRn8ZbPNdX`); build/test in a **sandbox** first, promote to live keys via Worker secrets.

### D2 — Entitlement: per-site **license key** (option a), not OAuth/account link
Chosen because it matches the plugin's BYO-key ethos and zero-backend distribution (GitHub-Release ZIP, no WP.org):
- The subscriber pastes an opaque token (`wpck_live_…`) into the **same single-field UX** that already exists for API keys. `LLM::detect()` gains a prefix rule, `Settings` stores it under the existing `{provider}_api_key`-style slot, and `WPCHAT_CLOUD_LICENSE` gives the constant-override path for free.
- OAuth/account-link would require a redirect/callback, a session store, and per-user identity on a plugin that deliberately has **no backend and no accounts** — a poor fit and a large surface. A license key is a bearer credential whose blast radius is limited (it only buys metered chat on our key, is **bound to the site URL** on first use, and is revocable).
- Trade-off accepted: a bearer token can be copied. Mitigation: site-URL binding at the proxy + revoke-on-abuse. This is strictly better than shipping our Anthropic key, and no worse than how the plugin already trusts a pasted API key.

### D3 — Proxy: extend the Cloudflare Worker; speak native Anthropic contract
The proxy exposes an **Anthropic-`/v1/messages`-compatible** endpoint so `CloudProvider` reuses all of `AnthropicProvider`'s translation code. The Worker is where the master key, Stripe secret, webhook secret, entitlement state, and usage counters live. Storage: **Workers KV** (or D1 if we want relational queries) for `license → {customer, subscription, status, site, period_usage}`.

### D4 — Overage policy: **throttle-to-Haiku** (policy a), not notify+top-up
Recommended because Cloud's entire value proposition is "€12 flat, no config, no surprises." A top-up (policy b) reintroduces exactly the billing friction Cloud exists to remove, and risks either an unexpected charge or a hard stop mid-task. Throttling to Haiku past the soft cap:
- keeps the user working (degraded, not blocked),
- bounds our token cost (Haiku is ~3× cheaper, protecting the heavy-user margin the pricing doc models at ~€5.5 gross),
- is reversible each billing cycle and needs no new payment flow.

The proxy overrides `body.model → claude-haiku-4-5` past the cap and returns `X-WPChat-Throttled: 1`; the plugin shows a gentle, dismissible notice. Revisit top-ups later if data shows demand.

## Architecture

### Components
- **Plugin (`CloudProvider`)** — resolves the license, points HTTP at the proxy, unchanged tool-use loop.
- **Cloud Proxy (Worker)** — auth + metering + key injection + Anthropic forwarding + Stripe Checkout/Portal/webhook endpoints.
- **Stripe** — product/price, Checkout, Customer Portal, subscription lifecycle webhooks.
- **KV/D1 store** — license↔subscription mapping, entitlement status, per-period usage, processed-webhook-event ids.

### Request flow — a Cloud chat
1. User sends a message; `Rest::handle_chat` runs as today. `Settings::get_provider()` returns `'cloud'` (license present), so `LLM::active()` is `CloudProvider`.
2. `BaseLLMProvider::run_with_tools` builds the **identical Anthropic request body** and POSTs to `CloudProvider::endpoint()` = `https://cloud.wpchat.app/v1/messages` with `CloudProvider::headers()` = `authorization: Bearer <license>`, `x-wpchat-site: <host>`, `x-wpchat-request-id: <uuid, one per user turn>`, `anthropic-version: 2023-06-01` — **no `x-api-key`**.
3. Proxy: verify license → subscription `active`/`past_due`? check site binding; look up current billing-period usage. If over the soft cap and policy=throttle, force `model=claude-haiku-4-5`. On the **first** turn of a `x-wpchat-request-id` (idempotent), increment the chat counter (loop turns 2-8 reuse the same id → counted once).
4. Proxy injects `x-api-key: <master>` and forwards to `api.anthropic.com`, returning the response **verbatim** plus `X-WPChat-Usage`, `X-WPChat-Limit`, `X-WPChat-Throttled`.
5. Plugin parses the response exactly as Anthropic's; the tool-use loop, History, and React cards are untouched.

### Request/response contract
```
POST https://cloud.wpchat.app/v1/messages
  authorization: Bearer wpck_live_…        # license (not the Anthropic key)
  x-wpchat-site: shop.example.com          # must match bound site
  x-wpchat-request-id: <uuid-per-user-turn># idempotent metering key
  anthropic-version: 2023-06-01
  content-type: application/json
  body: { model, max_tokens, system, messages, tools }   # native Anthropic body

200 → native Anthropic messages response
      + X-WPChat-Usage: 42  X-WPChat-Limit: 150  X-WPChat-Throttled: 0|1
402 wpchat_subscription_inactive   # past_due beyond grace / canceled
403 wpchat_site_mismatch | wpchat_license_invalid
429 wpchat_rate_limited            # upstream Anthropic backpressure
```
Errors are shaped so `AnthropicProvider::error_message()` still yields a readable string, with an extra `error.type=wpchat_*` the plugin can special-case (e.g. show "Manage billing").

Auxiliary proxy endpoints (Stripe glue): `POST /v1/checkout` → returns a Checkout Session URL for a site; `POST /v1/portal` → Customer Portal URL; `POST /v1/webhook` → Stripe events; `GET /v1/entitlement` (Bearer license) → `{status, usage, limit, period_end}` for the Settings meter.

## Component-by-component changes

### Plugin — `includes/`
- **`class-cloud-provider.php` (new)** — `class CloudProvider extends AnthropicProvider`:
  - `id() = 'cloud'`, `label() = 'WPChat Cloud'`, `default_model() = 'claude-sonnet-4-6'`, `models()` limited to Sonnet (+ Haiku as the throttle target, shown read-only).
  - override `endpoint()` → cloud base (const, filterable `wpchat_cloud_base_url`, overridable via `WPCHAT_CLOUD_BASE_URL` for staging).
  - override `headers($key)` → bearer license + site + request-id + `anthropic-version`.
  - `seam_filter() = 'wpchat_cloud_http_response'`.
  - `matches_key()` → `^wpck_(live|test)_`.
  - `validate_key()` → cheap `GET /v1/entitlement`.
- **`class-llm-providers.php`** — register `CloudProvider` in the default list (so `LLM::detect()` and `LLM::providers()` see it). Detection order: cloud prefix is distinct from `sk-`/`AIza`, no ambiguity.
- **`class-settings.php`** — `get_api_key('cloud')` returns the license from `wpchat_settings['cloud_api_key']` or the `WPCHAT_CLOUD_API_KEY` constant (reuses the existing `WPCHAT_{PROVIDER}_API_KEY` mechanism unchanged). Add a **Cloud** settings section: status line (Active / Past-due / Canceled), usage meter, "Manage billing" (Portal) + "Subscribe" (Checkout) buttons. Hide the model picker in Cloud mode (locked to Sonnet).
- **`class-onboarding.php`** — flip `provider_status().cloudAvailable → true`. `handle_set_provider` gains a `'cloud'` value that: creates a Checkout Session (via proxy) and returns its URL; on return, accepts/auto-fetches the license and sets `llm_provider='cloud'`. Keep `'cloud-waitlist'` for the pre-launch window; retire it once live.
- **`class-rest.php`** — no change to `handle_chat` logic; it already calls `LLM::active()` and reads `Settings::get_api_key()`. Optionally surface `X-WPChat-Throttled`/usage from the provider response into the chat payload so the UI can show the soft-cap notice.
- **`PRIVACY.md`** — add a Cloud paragraph: in Cloud mode, chat content transits **our proxy** (which forwards to Anthropic and does **not** persist message bodies) before reaching Anthropic; we store only license↔subscription mapping, billing email (held by Stripe), and per-period chat **counts**. BYO mode is unchanged.

### React — `app/src/Onboarding/`
- **`ProviderCard.tsx`** — when `cloudAvailable`, the Cloud tile becomes "Subscribe €12/mo" → opens Checkout in a new tab; a "I already have a license" path reveals a license field. Keep the 4-locale copy; drop "(coming soon)" at launch.
- New **Cloud status** row in the Summary card + a small usage meter component (reads `GET /v1/entitlement`).

### Proxy — `cloud/` (extend, don't replace the support Worker)
- `cloud/cloud-proxy.js` (new Worker, or new routes on a shared Worker): `/v1/messages`, `/v1/checkout`, `/v1/portal`, `/v1/webhook`, `/v1/entitlement`.
- Secrets (Wrangler): `ANTHROPIC_API_KEY` (master), `STRIPE_SECRET_KEY` (**restricted key**, `rk_`, minimal scopes: Checkout, Customer, Subscription, Billing Portal read/write), `STRIPE_WEBHOOK_SECRET`. KV binding for entitlement/usage.
- Reuse the existing HMAC/constant-time-compare style already in `support-worker.js` for any plugin-signed calls.

## Phased implementation plan

**Phase 0 — Stripe setup (manual, no code).** Create Product + €12 recurring Price in sandbox; configure Customer Portal; register the webhook endpoint; mint a **restricted** API key. Decide trial/tax. Record as a `[x](manual)` task.

**Phase 1 — Plugin Cloud mode + manual license issuance (smallest shippable slice).** Ship `CloudProvider`, the license field, `Settings`/onboarding Cloud path, the `wpchat_cloud_http_response` seam, and `tests/MockCloud.php` + a `tests/Scenarios/CloudChatTest.php` mirroring `MockAnthropic`. Licenses are issued **by hand** (you create a KV row after someone pays). Deliverable: a paying user pastes a license and chats through a minimal proxy. Fully offline-testable — no live Stripe/Anthropic needed for CI.

**Phase 2 — Proxy chat path: entitlement + metering + throttle.** Implement `/v1/messages`: license→subscription check, site binding, per-period counter keyed by `x-wpchat-request-id`, soft-cap → Haiku override, master-key injection, verbatim forwarding, usage headers. Add a Worker test harness (miniflare/vitest) with a mocked Anthropic upstream — the Worker-side analogue of the plugin's seam.

**Phase 3 — Automate billing.** `/v1/checkout` + `/v1/portal` + `/v1/webhook`: on `checkout.session.completed` mint + return/email the license; map `customer.subscription.*` and `invoice.payment_failed/paid` to entitlement status; verify signatures; idempotency via stored `event.id`. Onboarding "Subscribe" and Settings "Manage billing" go live.

**Phase 4 — Polish.** Usage meter UI, past-due/dunning UX (grace window before 402), license rotation/revocation, `X-WPChat-Throttled` in-chat notice, optional trial. Update `README.md`/`readme.txt` roadmap + `PRIVACY.md`, retire `cloud-waitlist`.

**Test seams (all phases):** `CloudProvider` → `wpchat_cloud_http_response` + `tests/MockCloud.php`; Worker → miniflare with mock Anthropic + mock Stripe. Neither CI nor local dev ever calls a paid API.

## Webhooks & security notes

- **Webhooks:** verify `Stripe-Signature` with `constructEvent` and `STRIPE_WEBHOOK_SECRET`; process `customer.subscription.created/updated/deleted`, `invoice.payment_failed`, `invoice.paid`, `checkout.session.completed`; **idempotency** by persisting `event.id`; ACK 2xx fast, do work async where possible; optionally allowlist Stripe IPs.
- **Security:** master Anthropic key and Stripe key exist **only** as Worker secrets — never in the plugin, ZIP, or browser. Prefer a **restricted** Stripe key (least privilege). License is a low-value, site-bound, revocable bearer token. Proxy does **not** log/persist chat bodies (keeps PRIVACY.md's "no background phone-home of content" true for Cloud). Rate-limit the Worker (Cloudflare rule) as with the support collector.

## Open questions for the user
1. **Overage:** confirm throttle-to-Haiku (recommended) vs notify+top-up — this shapes the proxy, the meter UI, and the Stripe config (a top-up needs a second Price / usage item).
2. **License delivery on `checkout.session.completed`:** email it, show it on a hosted return page, or have the plugin auto-fetch it via the Checkout `session_id` on redirect back to `/wpchat`? Affects the onboarding return flow.
3. **Hosting & domain:** confirm the Worker approach + a stable base URL (`cloud.wpchat.app`?), and whether entitlement state lives in **KV** (simple) or **D1** (queryable) — plus whether a free trial is offered.

## Critical files for implementation
- `includes/class-anthropic.php` (base to extend for `CloudProvider`)
- `includes/class-llm-providers.php` (register provider, `LLM::detect`/`active`, base loop + seam)
- `includes/class-settings.php` (`get_api_key`/`get_provider` precedence, Cloud settings UI)
- `includes/class-onboarding.php` (provider choice → Checkout/license flow)
- `cloud/support-worker.js` (proxy scaffold: HMAC verify pattern, Worker structure to extend)
