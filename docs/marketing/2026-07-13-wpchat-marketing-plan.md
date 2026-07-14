# ChatAdmin — 90-Day Marketing Plan

_Drafted 2026-07-13. Owner: Gintaras. Status: draft for review._

---

## 0. The decisions this plan is built on

| Lever | Decision |
|---|---|
| **North-star metric** | Active installs (sites with ChatAdmin installed **and used** in the last 30 days) |
| **Money framing** | Free tool. Install it, your clients self-serve, your support tickets drop. BYO Anthropic key = €0 to the developer. Monetize later via Cloud. |
| **Geo / language** | Global, English-first |
| **Budget** | Time + a small paid budget (~€200–400/mo) |
| **Brand** | **Standalone ChatAdmin** — its own handles and identity. "Loupe" stays separate. |
| **Primary channels** | LinkedIn + YouTube. Reddit + X/Bluesky organic. TikTok/IG = **repurpose only** (same vertical demo videos). |
| **Revenue track (parallel)** | ChatAdmin Cloud / Stripe billing — separate engineering workstream (see `TASKS.md`), does **not** gate the installs push. |

---

## 1. The angle (this is the whole plan; everything else is delivery)

Most WordPress-plugin marketing says "AI makes your site easier." That's fluent wallpaper — the reader already believes it and does nothing.

The angle that earns attention from **this** reader (a developer/agency drowning in client support) is a reframe they haven't heard:

> **You will never train your client out of these tickets — because the ticket isn't a knowledge gap, it's an interface mismatch.**

Walk it out, because the whole message hierarchy falls out of it:

- The client asks "can you just mark order 2833 as used?" **Therefore** you assume they need training. **But** you've sent the Loom three times and the ticket still comes back — because they don't want to *learn* Orders → filter → open → status dropdown → update. They want to *say the thing*.
- **Therefore** documentation is the wrong fix. You can't teach someone into wanting to be a junior WordPress admin. They never wanted the job.
- **Therefore** the fix is a different interface — one that speaks the client's language ("mark order 2833 used, they spent 30€ of 100€") instead of WordPress's.
- **And the part nobody prices in:** the helpdesk hours you resent are the hours you're *not* selling the next build. The support work isn't just annoying — it's the ceiling on your agency. You built a business that punishes you for shipping.

**ChatAdmin doesn't replace you. It fires you from the helpdesk job you hate, and lets you keep the retainer you like.**

### The one-liner (use everywhere)

> **Stop being your clients' WordPress helpdesk.**
> Install ChatAdmin once — your clients manage their own store and content by chat, in plain language, in any language. You keep the retainer. You lose the 5-minute tickets.

### The objection that kills 80% of deals — and the built-in answer

Every developer's first thought: _"I don't give clients admin access because they break things."_

ChatAdmin's product design **is** the answer, and we lead with it, not bury it:

- **Every change is preview → confirm.** The client sees exactly what will happen and taps Confirm. Nothing mutates silently.
- **One item at a time. No bulk-destructive anything.** There is literally no "delete all orders" button for a panicked client to find — it's an enforced design constraint, not a setting.
- **It hands off instead of dead-ending.** When a request is out of scope, ChatAdmin returns a deep link to the right admin page — it never invents a destructive workaround.
- **It's not "access." It's guardrailed access.** That's the phrase.

### Message pillars (every piece of content ladders to one)

1. **The helpdesk trap** — the resentment, the unpaid 5-minute tickets, the training that never sticks. (Emotional entry point.)
2. **Guardrailed, not risky** — preview/confirm, no bulk-destructive ops. (Objection killer.)
3. **In their language, literally** — LT/RU/PL/EN out of the box; "mark order used" works, "pažymėk kaip panaudotą" works. (Differentiator vs every English-only AI plugin.)
4. **You install once, you own nothing to run** — free, open source, BYO key, auto-updates from GitHub. No new SaaS bill, no vendor lock. (Trust + low-friction.)

---

## 2. Who the friend is (ICP)

Not "an audience." One person you're writing to:

**"Marcus," freelance WP developer / 1–5 person agency.** Builds and maintains 10–40 WooCommerce/WordPress sites for SMB clients. Bills a monthly care plan or hourly. His inbox is 60% "can you just…". He's tried Looms, PDFs, custom dashboards, ACF-simplified edit screens — they rot. He guards admin access because he's been burned. He reads r/WordPress and r/ProWordPress, follows a few WP devs on X/LinkedIn, half-watches WP dev YouTube, lurks in an agency Slack/Discord or two. He is skeptical of "AI" hype and allergic to anything that looks like it'll break a client site or lock him into a subscription.

**Watering holes (where the plan actually shows up):**
- **LinkedIn** — where agency owners perform professionally and buy tools. #1.
- **YouTube** — "how I stopped doing client support" / demo searches; durable, compounding.
- **Reddit** — r/WordPress, r/ProWordPress, r/woocommerce. High-intent, allergic to spam — contribute, don't advertise.
- **X / Bluesky** — the WP dev crowd (`#WordPress`, WooCommerce devs, Post Status orbit).
- **WordPress.org plugin directory** — not social, but the biggest organic install engine in the ecosystem (see §4).
- Post Status (WP industry community), relevant agency Slacks/Discords.

---

## 3. Funnel & metrics

```
Awareness            Consideration           Activation                 Expansion (later)
LinkedIn/YT/Reddit → gynciuz.github.io/chat-admin/ + WP.org  →  Install + first real chat → Cloud (€12/mo)
demo video + post    listing + demo          on a live client site       when BYO-key friction bites
```

**North-star:** 30-day active installs.

**Supporting metrics (weekly):**
- Installs (WP.org active installs count once listed; GitHub release downloads until then)
- Landing-page → install conversion
- Content: LinkedIn impressions/saves, YouTube views/retention, Reddit upvotes-not-removed
- **Leading indicator of activation:** design-partner "first successful client-run chat" count
- WP.org rating + review count (social proof compounds installs)

**Explicitly _not_ a metric right now:** revenue. Cloud is a parallel build; the 90 days are about proving the free tool creates active, retained installs.

---

## 4. The gating prerequisite: ship the WordPress.org listing

**Blunt truth: with an installs north-star, no WP.org listing is the single biggest hole in the strategy.** Today ChatAdmin installs via a GitHub ZIP + auto-update — fine for developers who already trust you, useless for organic discovery. The WP.org directory is where WordPress users search "AI" / "WooCommerce assistant" and one-click install.

The README already flags WP.org submission as planned "once the plugin has more public users." That's backwards for this goal — the listing is *how* you get public users.

**Action (Weeks 1–3, before the content push peaks):**
- Prepare the WP.org submission: `readme.txt` is already in-format (good). Add: 5–7 screenshots, a demo GIF/short video, keyword-considered title + short description ("AI chat admin for WooCommerce — your clients manage orders & content by chat").
- Confirm guideline compliance: the plugin makes external API calls (Anthropic) — WP.org requires clear disclosure + opt-in; `PRIVACY.md` + onboarding key entry already cover this, but the readme must state it plainly.
- Keep the GitHub auto-update channel for existing/pre-release users; the `Update URI` header already prevents a same-slug hijack.
- Submit; review can take days-to-weeks. **Start this now** so the listing is live when content drives traffic.

**If WP.org review slips:** the landing page (gynciuz.github.io/chat-admin/) + GitHub release is the fallback install path — the content still works, conversion is just lower. Don't let the listing block the content calendar; run them in parallel.

---

## 5. Channel plan (roles, not a laundry list)

Each channel has one job. No channel gets content just to have a presence.

**LinkedIn — the engine.** Founder-led (Gintaras's personal profile out-performs any company page for reach) + a ChatAdmin company page for legitimacy/retargeting. Job: reach agency owners with the helpdesk-trap narrative + demo videos. 3–4 posts/week. Personal voice, not brand voice.

**YouTube — the compounding asset.** 60–90s Shorts (the vertical demos) + occasional 3–6 min "watch me hand a client the keys safely" walkthroughs. Job: rank for intent searches, be the thing you link in every Reddit/forum reply. Evergreen; keeps paying out.

**Reddit — high-intent, zero tolerance for ads.** Job: be genuinely useful in threads where devs vent about client support, link a demo *only when it answers the actual question*. One self-promotional misstep gets you banned; budget real participation time, not drops.

**X / Bluesky — the WP-dev water cooler.** Job: build-in-public, short demo clips, reply into WooCommerce/WP-dev conversations. Lower priority than LinkedIn but cheap to repurpose into.

**TikTok / Instagram Reels — repurpose only.** The B2B buyer doesn't live here, so no bespoke cadence. Every vertical demo video we shoot for LinkedIn/YouTube also posts here. Zero-marginal-cost reach; occasionally a demo breaks out. **Note:** Buffer's TikTok support can be reminder/push-based rather than fully auto — treat TikTok as semi-manual.

**WordPress.org + landing page — the conversion floor** (see §4). Every piece of content points here.

---

## 6. The content engine (one repeatable format → every channel)

The trap is trying to be original weekly. The escape: **one repeatable format, produced in a batch, cut for every surface.**

### The hero format: the 30–45s "watch the client do it" demo

Show, don't tell. The product's magic is visual — a sentence in, a real WooCommerce change out, with a confirm step. One screen recording carries more than any paragraph.

**Template (each video):**
1. **0–3s hook (text on screen):** the ticket. _"'Can you just mark order 2833 as used?' — the email you've gotten 400 times."_
2. **3–20s:** type the plain-language request into `/chat-admin`. Show the rich order card render.
3. **20–30s:** the **preview → Confirm** step. Land the guardrail: _"Nothing changes until they tap confirm."_
4. **30–40s:** done. Cut to the reframe: _"You didn't train them. You didn't get the email. Retainer's intact."_
5. **End card:** gynciuz.github.io/chat-admin/ · free · open source.

Batch-shoot 6–8 of these against the real dev site (`localhost:8080` rig already exists) covering: mark order used, change a price, swap a product photo, edit opening hours (content), add a customer note, "in Lithuanian/Polish" multilingual flavor, the **guardrail close-up** (preview/confirm), the **handoff** (deep link instead of dead-ending).

### Content pillars (rotate; each ladders to a §1 message pillar)

1. **The helpdesk trap** — vent-with-them posts. _"You didn't become a developer to reset someone's homepage banner at 9pm."_ (Pillar 1)
2. **Demo Tuesdays** — one hero video. (Pillars 1+3)
3. **"But won't they break it?"** — the guardrail explainer; preview/confirm, no bulk-destructive ops. (Pillar 2)
4. **Build-in-public** — shipping WP.org, Cloud, a new tool; honest, personal. (Trust)
5. **Multilingual proof** — the same request in LT/RU/PL/EN; almost no competitor does this. (Pillar 3)

### Sample LinkedIn posts (voice: peer developer, not brand)

**Post A — the trap (pillar 1):**

> A client emailed me "can you change the price on the sneakers to €59" at 9:14pm.
>
> It's a 20-second job. I've shown her how to do it. Twice. I've sent a Loom.
>
> She's not stupid — she runs a business. She just doesn't want to learn WooCommerce's order screen, and honestly, why would she? That was never the deal.
>
> I used to think the fix was better documentation. It isn't. You can't document someone into wanting to be a junior WordPress admin.
>
> The fix is a different interface. Let her *type what she wants* — "change the sneakers to €59" — and have it happen, with a confirm step so she can't nuke anything.
>
> That's the whole reason I built ChatAdmin. Not to replace the developer. To fire the developer from the helpdesk job.
>
> Free, open source, install once → [link]. First comment: 30-second demo.

**Post B — the objection (pillar 2):**

> "I'd never give a client admin access — they'll break something."
>
> Same. That fear is correct, and it's exactly what I designed around.
>
> In ChatAdmin, a client can't break the site because:
> → every change previews first, and nothing happens until they tap Confirm
> → it works one order/page at a time — there's no bulk-delete button to find by accident
> → when something's out of scope, it hands them a link to the right admin page instead of improvising
>
> It's not access. It's guardrailed access. [demo link]

**Post C — build-in-public (trust):**

> ChatAdmin just went from "install our GitHub zip" to submitting on the WordPress.org directory.
>
> Scary, honestly — public reviews, the guideline gauntlet, a rating anyone can tank.
>
> But if the pitch is "let non-technical clients run their own site safely," it can't live behind a developer-only install flow. Building in public → [link]

### Sample Reddit reply (r/WordPress, in a "clients keep asking me to do trivial edits" thread)

> The thing that finally moved the needle for me wasn't another tutorial — clients don't watch them. It was giving them an interface that matches how they think. They type "mark order 2833 used" instead of navigating the orders screen. I ended up building an open-source plugin for exactly this (ChatAdmin) — every change is preview-then-confirm so they can't break anything. Happy to share a link if useful, not trying to spam the thread.

_(Only post this where it genuinely answers the question. Lead with the insight; the tool is a footnote.)_

---

## 7. Brand & social setup kit (what needs _you_, and what I'll do)

**I can't create the accounts** — signup, email/phone verification, and ToS need you. Here's the kit; you create, I'll wire to Buffer + load content.

**Handles to claim (check availability; keep consistent):**
- Domain: **gynciuz.github.io/chat-admin/** for now (custom domain TBD) — landing + link-in-bio
- LinkedIn company page: `/company/chat-admin`
- YouTube: `@wpchat`
- X: `@wpchat` / Bluesky: `@wpchat` (handles TBD)
- TikTok: `@wpchat` · Instagram: `@wpchat.app`
- GitHub: already `gynciuz/wpchat` ✓

**Bio (short, one voice) — reuse across platforms:**
> Stop being your clients' WordPress helpdesk. ChatAdmin lets your clients run their own WooCommerce store & content by chat — in plain language, any language, with a confirm step so nothing breaks. Free & open source.

**Avatar/banner spec:** logo mark on dark (the app is dark-mode); banner = the one-liner + a phone-frame screenshot of a chat turn. (Can be produced with the design tooling if you want.)

**Buffer:**
- Current org is on the **free plan: 3 channels / 10 scheduled posts**, and its 2 connected channels are the *Loupe* LinkedIn profile + page — **wrong brand** for ChatAdmin content.
- **You:** create the ChatAdmin channels above, connect them to Buffer. Since brand = standalone ChatAdmin, don't post ChatAdmin content to the Loupe channels. Your **personal** LinkedIn is fair game (founder posting his own product is the strongest B2B reach).
- Free Buffer caps you at 3 channels / 10 scheduled posts — too tight for LinkedIn + YouTube + X + TikTok + IG. A **Buffer paid plan** (or just staging posts as Buffer _Ideas_, which are channel-agnostic and unlimited-ish) unblocks it. Given Cloud/Stripe is the priority paid account, I'd stage the calendar as Buffer **Ideas** now (free) and only upgrade Buffer when the channels actually exist.
- **On approval, I'll load the first ~10 posts + video captions into Buffer as Ideas** so they're queued and ready the moment channels connect.

---

## 8. Proof engine — recruit design partners (starts Week 1)

Testimonials from real agencies are worth more than any ad. Don't wait for them to appear — recruit.

- **Goal:** 5–10 developers running ChatAdmin on a real client site within 30 days.
- **Where:** DM warm contacts, the Reddit/LinkedIn people who engage, WP agency Slacks.
- **The ask:** "Install it on one client site, tell me where it breaks, and if it saves you tickets let me quote you." White-glove them personally.
- **Extract:** one hard number each ("trivial tickets down ~X/week"), one quote, ideally a screen recording. These become case-study posts and the "wall of proof" on the landing page.
- **Case-study template:** client type → the recurring ticket that vanished → hours saved/month → the quote. Keep it one screen.

---

## 9. Small budget allocation (~€300/mo baseline)

Organic is the engine; cash only amplifies what's already working. Don't spend on cold ads for a category people don't know exists.

| Spend | ~€/mo | Why |
|---|---|---|
| One rotating WP-dev newsletter/podcast sponsorship | €150–200 | Post Status, WP Minute, WP Builds, WPTavern-adjacent — precisely the ICP, trusted context |
| Boost the 1 best-performing organic LinkedIn post/mo | €50–80 | Amplify proven winners only; never boost unproven content |
| Video tooling (Descript/CapCut Pro) | €15–25 | Batch-cut the hero videos for every surface |
| Reserve / experiments | ~€50 | A small Reddit or YT ad test _only_ once a demo video proves organic pull |

**Rule:** nothing gets paid distribution until it's earned organic traction. Cash follows proof, never leads it.

---

## 10. 90-day timeline

**Phase 1 — Foundation (Weeks 1–3)**
- Ship: the landing page — gynciuz.github.io/chat-admin/ (hero = the one-liner + a demo video + "install free"), WP.org submission prepared & sent (§4).
- Claim all handles; create ChatAdmin social accounts; connect to Buffer.
- Batch-shoot 6–8 hero demo videos on the dev rig.
- Recruit first 3 design partners.
- Load first 2 weeks of content into Buffer Ideas.
- _Parallel: Cloud/Stripe design lands from the worktree session; review & sign off (separate track)._

**Phase 2 — Launch the narrative (Weeks 4–8)**
- Content cadence live: LinkedIn 3–4×/wk, 1 YouTube video/wk, repurpose to X/TikTok/IG, 2–3 genuine Reddit contributions/wk.
- Publish first 2 design-partner case studies.
- First newsletter/podcast sponsorship runs.
- If WP.org is live: push for first 10 reviews from design partners.
- Weekly metric review; double down on the format that's converting.

**Phase 3 — Scale what works (Weeks 9–12)**
- Boost proven posts; second sponsorship in the winning channel.
- Turn the best content into a small "cornerstone" (a proper YouTube walkthrough + a written guide: _"How to give clients WordPress access without the 9pm emails"_).
- 5+ published case studies; wall-of-proof on the landing page.
- Decision gate: is BYO-key friction the top install blocker? If yes, that's the signal to prioritize Cloud launch. If installs stall pre-listing, escalate WP.org.
- Retro: which channel earned its keep, kill the rest.

---

## 11. Cadence & review

- **Weekly (30 min):** installs, top-performing post, one thing to double down on, one to drop.
- **Bi-weekly:** design-partner check-ins → new proof.
- **End of each phase:** kill/keep on channels and formats against the north-star.

---

## 12. Immediate next actions

**Needs you (I can't do these):**
1. Create ChatAdmin accounts: LinkedIn page, YouTube, X/Bluesky, TikTok, Instagram (bios/handles in §7). Connect them to Buffer.
2. Confirm the domain (using **gynciuz.github.io/chat-admin/** for now).
3. Approve/adjust this plan.

**I'll do on your go:**
1. Draft the landing-page copy + structure (angle from §1).
2. Write the full first-2-weeks content calendar (posts + video scripts + captions) and **load it into Buffer as Ideas**.
3. Write the WP.org listing copy (title, descriptions, screenshot captions) and a submission checklist.
4. Write the design-partner outreach DM + case-study template.
5. Fold in the Cloud/Stripe design (running now in a worktree) once it lands, and sequence its launch against the installs data.

---

_Cross-references: pricing model in `docs/chat-admin-cloud-pricing.md`; Cloud billing engineering in `TASKS.md` (top task) + `docs/superpowers/specs/2026-07-13-wpchat-cloud-billing-design.md` (being drafted)._
