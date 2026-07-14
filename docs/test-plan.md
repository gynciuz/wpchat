# ChatAdmin manual test plan

Run on a staging WooCommerce site with a few test orders. Each scenario is
**action → expected**. ⭐ = new in 0.6/0.7. See also the priority-scored matrix
in `wp_ai_plugin_test_matrix.md`.

## A. Onboarding & provider choice ⭐
1. **First run** — visit `/chat-admin` as an admin on a fresh install → wizard appears (Welcome → billing Provider → **AI provider** → API key → Model → … → Summary).
2. **Pick a provider** ⭐ — on the "Which AI?" step choose **OpenAI** → the key step's link points to platform.openai.com, placeholder `sk-...`, and the Model step lists GPT models (not Claude). Repeat with **Gemini** → aistudio link, `AIza...`, Gemini models.
3. **Live key validation** ⭐ — paste a deliberately wrong key (e.g. `sk-ant-totallyfake123`) → rejected immediately at setup ("rejected this key…"), not at first chat. Paste a real key → accepted, advances.
4. **Malformed key** — paste `hello` → rejected with a format error before any network call.
5. **Constant override** — add `define('CHATADMIN_ANTHROPIC_API_KEY','sk-ant-…')` to wp-config → Settings shows the Anthropic key field locked; onboarding key step shows the locked/masked state.
6. **Cloud waitlist** — pick the "ChatAdmin Cloud" tile → labeled coming-soon (€12/mo, Stripe), captures an email, and **still** walks you through adding a real provider key (doesn't leave you with no provider).

## B. Multi-provider chat parity ⭐
7. Configure **Anthropic**, then run `show me the last 10 orders` → an interactive orders table renders.
8. Switch to **OpenAI** in ChatAdmin → Settings (save) → run the same prompt → **identical** orders table + behavior. Switch to **Gemini** → same again. *(Core 0.7 proof: same UI/tools regardless of provider.)*
9. **Tool call works on each** — `mark order <id> completed` on each provider → triggers the confirmation flow (below) and completes.

## C. Orders + confirmation gates ⭐
10. **Status change confirms** ⭐ — `mark order 1043 as completed` → assistant asks to confirm (Confirm/Cancel bar or "type yes"); nothing changes until you confirm. Confirm → status updates.
11. **Customer-visible note confirms** ⭐ — `email the customer of order 1043 a thank-you note` → confirm prompt before sending; a private note (`add a private note…`) runs without a prompt.
12. **Order action confirms** ⭐ — `resend the invoice for order 1043` → discovers the action, asks to confirm, then sends.
13. **3-dot menu stays one-click** ⭐ — in the orders table, use the row menu → "Change status" applies immediately (the click *is* the confirmation; no extra prompt) with zero API spend.
14. **Multilingual confirm** — do #10 in Lithuanian (`pažymėk užsakymą 1043 įvykdytą`) and confirm with `taip` → works. Confirm with `ne` → does **not** apply.
15. **Bulk is refused (safety)** — `cancel all pending orders` → refuses and hands you the wp-admin bulk-actions link instead.

## D. Content & SEO
16. **Draft-first create** — `write a 300-word post about summer sale and add a featured image` → creates a **draft** (not published), returns edit/preview links.
17. **Publish needs confirm** — `publish it` → asks to confirm, then publishes.
18. **Edit content** — `change the About page heading to "Our Story"` → preview diff → Confirm bar → applies.
19. **SEO meta** — `write an SEO title and meta description for the About page` → sets them via your SEO plugin (Yoast/Rank Math/SEOPress) through the preview→confirm flow.
20. **SEO audit** — `audit my SEO` → structured report; fixable items offer to change settings (indexing, llms.txt, AI crawlers) with confirmation.
21. **Traffic** — `how many visitors this week?` → reads your analytics plugin (or says none detected).

## E. Smart handoffs (no dead-ends) ⭐
22. `approve the pending comments` ⭐ → deep link to the comments moderation screen with the next step.
23. `find broken links` ⭐ / `what plugins need updating` ⭐ → hands you the Site Health / Plugins screen.
24. `set product ABC to 20% off` → explains products aren't editable here and links to WooCommerce → Products.
25. `make Jane an editor` → links to her user-edit page.

## F. Help, Report, Diagnostics, telemetry ⭐
26. **Help chat** ⭐ — click **Help** in the chat footer → ask "how do I get an API key?" → answers from the FAQ with **no tool calls**; it can't perform actions.
27. **Report a problem** ⭐ — click Report → submit → if `CHATADMIN_SUPPORT_ENDPOINT` is set, your collector/email receives it (recent chat + error + recent error log); otherwise it falls back to `wp_mail`.
28. **Error → telemetry** ⭐ — temporarily set a bad key and send a chat → red error banner shows a "Report a problem" button; **ChatAdmin → Diagnostics** lists the error.
29. **Diagnostics page** ⭐ — ChatAdmin → Diagnostics → recent errors table, "Copy diagnostics" copies JSON, "Send to developer" delivers.
30. **Telemetry opt-out** ⭐ — Settings → Privacy & diagnostics → uncheck → no phone-home (local log still records).
31. **Worker (if deployed)** ⭐ — follow `cloud/README.md`; a wrong `X-ChatAdmin-Signature` → 401, correct → `{ok:true}` and email arrives.

## G. Settings & security
32. **Provider switch persists** ⭐ — Settings → change provider + model → save → reload shows the new provider's model list; chat uses it.
33. **Write-only key** ⭐ — Settings shows a masked "current key" but the input is blank; saving with a blank field **keeps** the existing key (doesn't wipe it).
34. **Rate limit** ⭐ — fire ~31 chat messages in under a minute → the extra request returns a "too quickly, wait a moment" 429.
35. **Permissions** — log in as a Subscriber → `/chat-admin` is blocked; an Editor without WC caps can use content tools but not order tools.

## H. Distribution
36. **Packaging** ⭐ — run `bin/release.sh` → builds `chat-admin-v0.7.1.zip`; unzip → contains `includes/`, `build/`, `vendor-puc/` but **not** `tests/`, `app/src/`, `cloud/`.
37. **Auto-update** — install the prior version on a site, publish a GitHub release → within ~12h (or "Check Again") Plugins → Updates offers the update; one-click updates cleanly.

---

**Priority order if short on time:** A2-A3 (provider validation) → B8 (provider parity) → C10-C13 (confirmation gates) → F26-F28 (help/telemetry) → C15 (bulk safety).
