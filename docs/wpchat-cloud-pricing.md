# ChatAdmin Cloud — pricing calculation (refresh 2026-06-22)

Internal note. ChatAdmin Cloud is the future hosted tier (Stripe subscription;
the backend runs on our own Anthropic key — **not** user-facing). This refreshes
the earlier "€10/mo — €5 of tokens" estimate against **current** Anthropic
pricing.

## Token cost basis (per the claude-api reference, 2026-06)

| Model | Input $/MTok | Output $/MTok | Cache read |
|-------|:---:|:---:|:---:|
| Haiku 4.5 | $1 | $5 | ~0.1× input |
| **Sonnet 4.6** (Cloud default) | **$3** | **$15** | ~0.1× input |
| Opus 4.8 | $5 | $25 | ~0.1× input |

Cloud should default to **Sonnet 4.6** — the best balance of cost and tool-use
quality. (Haiku would ~3× the margin but is weaker at the agentic loop; Opus is
~1.7× costlier.)

## Cost per resolved chat (one user request → answer)

A resolved chat runs the tool-use loop ~2–3 turns. Per turn ≈ a large stable
prefix (system prompt + 16 tool schemas ≈ 8K tokens) + ~2K volatile (history)
input, and ~400 output tokens.

- **With prompt caching** (the 8K prefix caches after turn 1): ≈ **$0.035 / chat**.
- **Without caching (worst case):** ≈ $0.09 / chat.
- **Blended planning number: ~$0.05 / chat.**

Note: the order-table 3-dot menus are **direct REST calls — $0 API** — so only
genuine chat questions cost tokens.

## Monthly cost per shop

| Usage | Chats/mo | Token cost (~$0.05) | ≈ EUR |
|-------|:---:|:---:|:---:|
| Light | 30 | $1.5 | ~€1.4 |
| Moderate | 100 | $5 | ~€4.6 |
| Heavy | 200 | $10 | ~€9 |

So **~€5 of tokens ≈ 100–110 chats/month** at current Sonnet 4.6 pricing — the
old "€5 of tokens" line still holds.

## Stripe fees

On a €12 charge: EU cards ≈ 1.5% + €0.25 ≈ **€0.43**; international ≈ 2.9% +
€0.25 ≈ €0.60. Budget ~€0.50/subscriber/month.

## Recommended launch price

**€12 / month**, with a fair-use soft cap of **~150 chats/month** (≈ €5–6 of
tokens). Rationale:

| | Light user | Heavy user (cap) |
|---|:---:|:---:|
| Revenue | €12 | €12 |
| − Stripe | €0.50 | €0.50 |
| − Tokens | ~€1.4 | ~€5–6 |
| **Gross** | **~€10** | **~€5.5** |

That leaves room for the proxy infra + support, most users sit well under the
cap (improving blended margin), and no single user is unprofitable. Bumping
€10 → €12 absorbs current pricing + Stripe + overhead while staying a clean,
memorable number.

**Overage policy (decide before Cloud ships):** when a subscriber passes the
cap, either (a) soft-throttle to a slower/cheaper model (Haiku) for the rest of
the cycle, or (b) notify and offer a top-up. Do **not** silently eat unbounded
overage.

## Surfaced price

ProviderCard `cloudPrice` (all 4 locales) shows **"€12/mo"**; the body frames it
as a Stripe subscription, coming soon. Revisit this number whenever Anthropic
pricing or the default model changes.
