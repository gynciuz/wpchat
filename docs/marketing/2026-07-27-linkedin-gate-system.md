# ChatAdmin — LinkedIn Gate System & First 3 Weeks

_Drafted 2026-07-27. Owner: Gintaras. Status: approved design → execution doc._

A test-and-scale engine: post situational stories from the ChatAdmin company page,
measure them cleanly, and let each €100 buy exactly one answer. An idea only earns
the next €100 by winning at the last one. Winners get influencers. Losers die cheap.

> **Relationship to the 90-day plan** (`2026-07-13-wpchat-marketing-plan.md`): this
> doc **deliberately reverses** that plan's "no cold ads, TikTok/IG repurpose-only"
> stance. We are now running paid tests at decision gates and treating TikTok/IG/Google
> as real ad channels, not repurpose surfaces. Everything else — the ICP, the
> "helpdesk trap" angle, the guardrail objection-killer — carries over unchanged.

---

## 0. The one idea everything ladders to

Most WordPress AI marketing says "AI makes your site easier." The reader already
believes that and does nothing.

The angle we own is a reframe they haven't heard:

> **The client's "quick edit" ticket isn't a training gap. It's an interface
> mismatch — and no amount of documentation closes it.**

You can't teach someone into *wanting* to be a junior WordPress admin. They never
wanted the job. So the fix isn't a better Loom. It's a different interface — one
that speaks the person's language instead of WordPress's.

Every post below dramatizes one painful moment, lets the reader feel it, then turns
on that reframe. That is the Tony Fadell move: sell the **why** (the pain, the
frustration, the villain) before the **what** (the plugin).

---

## 1. The gate ladder

Each gate is **one experiment, €100, one variable, a clear pass/fail.** The rule
that makes it work: **no gate spends until the prior gate passed.** Cash follows
proof; it never leads it.

| Gate | Spend | The one question it answers | Runs | PASS bar → escalate | FAIL → |
|---|---|---|---|---|---|
| **G0 · Organic** | €0 | Which *hook* do strangers-of-strangers react to? | 3 posts/wk from the company page (§4) | A post clears the **win bar** in §2 | Keep posting; no post is "owed" a boost |
| **G1 · Amplify** | €100 | Does that hook hold with a **cold** paid audience, or was it just your network being kind? | Boost the winning organic post on LinkedIn, targeted to agency owners / WooCommerce / WP admins | Cold engagement ≥ **2%** AND CPC ≤ **€6** | Hook was network-only. Back to G0. |
| **G2 · Cross-channel** | €100 | Does the proven hook convert as a **paid ad on one new channel**? | Rebuild the hook as native creative; test **one** channel (order: Meta/IG → TikTok → Google) | Cost-per-landing-click ≤ **€1.50** (social) / ≤ **€3** (Google) OR cost-per-install ≤ **€25** | Kill that channel/creative. Try the next channel with the same hook, or a new hook from G0. |
| **G3 · Scale / Influencer** | €100 | Does it hold at higher volume, or beat paid when a **trusted voice** says it? | Either +€100 on the winning channel, or hand the proven hook+creative to a nano/micro influencer (§6) | Cost holds within **+30%** at 2× spend, OR influencer post beats the channel's CPC | Cap the channel at its efficient volume; log the ceiling. |

**Why one channel per gate, never a €33 split:** €33 across Google + TikTok + IG at
once buys three unreadable samples and teaches you nothing. One channel per €100
gives every gate a clean answer. Google runs **last** on purpose — it's
search-intent, and almost nobody searches for a category they don't yet know
exists. We test it, but we expect it to lose to the interruption channels. (Keywords
to try in §5.4.)

### The gate scorecard (fill one per decision)

```
GATE: G__   DATE: ____   HOOK/POST: __________________________  SPEND: €____
Channel: ______   Objective: ______
Impressions: ____   Clicks: ____   CTR: ____%   Engagement rate: ____%
Landing clicks (UTM): ____   Installs attributed: ____
Cost per landing-click: €____   Cost per install: €____
PASS bar met?  [ ] yes → escalate to G__   [ ] no → action: __________________
One sentence I learned: ________________________________________________
```

---

## 2. The win metric (what makes a post a "winner")

A **winner** — a post that earns the G1 €100 — clears **both** bars within ~5 days
of posting:

- **Engagement rate ≥ 4%** — that's `(reactions + comments + shares + saves) ÷ impressions`.
  A typical company page runs ~1–2%, so 4% is roughly 2× baseline: proof the *hook* works.
- **Click-through ≥ 1%** — `link clicks ÷ impressions`, measured with UTM (§7).
  Proof of *intent*, not just applause.

Both, never either. Engagement without clicks is entertainment; clicks without
engagement is usually a thumb-slip. We escalate messages that do both.

If **no** post clears the bar in a two-week window, that's a signal about the
*message*, not the budget — rework the hook before spending a cent.

---

## 3. Cadence

- **3 posts/week, working days: Monday / Wednesday / Friday.**
- **Protagonist alternates** buyer (the agency/developer) and user (the store owner),
  so we learn whose pain travels farther.
- **Formats rotate** — text, still-image, video — so we also learn which *format*
  carries, independent of the hook. Over 9 posts: 4 text, 3 still-image, 2 video.
- Post time: **08:00–09:30 local for the target region** (agency owners check LinkedIn
  before the day eats them). Tune from the data.

---

## 4. The first 3 weeks — 9 posts, written

Legend: **[B]** buyer protagonist · **[U]** user protagonist · format in brackets.
Voice throughout: a real person talking to one peer across a table — plain words a
12-year-old could follow, but never childish, never "as a kid…". Concrete nouns, no
vague "it/that/they". Guardrail (preview→confirm, one-at-a-time, no bulk-destructive)
is woven in, never bolted on.

> **CTA + link convention:** every post's link is the landing page with a per-post
> UTM (§7). "Demo in the comments" means the first comment carries the 40-second
> video so the post body stays link-clean (LinkedIn suppresses in-body links).

---

### Week 1

**Post 1 — Mon — [B] — text**
_Pillar: the helpdesk trap. Hook: the 9:14pm email._

> A client emailed me at 9:14pm: "can you change the price on the sneakers to €59?"
>
> Twenty seconds of work. I've shown her how to do it. Twice. I even sent a screen
> recording.
>
> She's not lazy — she runs a real business. She just doesn't want to learn
> WooCommerce's order screen, and honestly, why would she? That was never the deal
> she signed up for.
>
> I used to think the fix was better docs. It isn't. You cannot document someone into
> wanting to be a part-time WordPress admin.
>
> The fix is a different door in. Let her type what she wants — "change the sneakers
> to €59" — and let it happen, with a confirm step so she can't break anything.
>
> That's the whole reason I built ChatAdmin. Not to replace the developer. To fire
> the developer from the helpdesk shift.
>
> Free and open source. Demo in the comments 👇

_Beat check: email (but) → I trained her (but) → docs don't work (therefore) →
different interface (therefore) → the tool. No "and then."_

---

**Post 2 — Wed — [U] — still-image**
_Pillar: the store owner's side. Hook: it's my store and I can't touch it._

**Image brief:** a plain phone screenshot mock — a half-typed message to "my web
guy": _"hi sorry to bother you again, can you swap the front page photo when you get
a sec 🙏"_ — timestamp 21:47, a little "delivered" tick, no reply. Dark, honest,
not slick. One line of text baked in: **"Three days for a two-minute change."**

> It's my store. My name is on it. And I can't change a photo on the front page
> without texting a guy and waiting three days.
>
> I don't want his job. I don't want to learn the dashboard. I just want to swap one
> picture before the weekend sale — and instead I'm apologizing for bothering him.
> Again.
>
> Here's the part that stings: I'm paying for this. The monthly fee is so someone
> keeps the site alive. It was never supposed to mean I'm locked out of my own shop.
>
> Turns out the problem was never me being "non-technical." The problem was the only
> door in being built for a developer.
>
> ChatAdmin gives the store owner a second door: say what you want, see exactly what
> will change, tap confirm. Nothing else moves.
>
> Link in the comments.

---

**Post 3 — Fri — [B] — video (30–45s demo)**
_Pillar: show the magic + the guardrail. Hook: the ticket you've had 400 times._

**Video script (vertical, muted-friendly, captions baked in):**
1. **0–3s** — black screen, text: _"'Can you just mark order 2833 as used?' — the
   email you've gotten 400 times."_
2. **3–18s** — screen recording of `/chat-admin`. Type: _"mark order 2833 used,
   customer spent €30 of €100."_ The rich order card renders.
3. **18–28s** — the **preview → Confirm** step fills the screen. Caption: _"Nothing
   changes until they tap Confirm."_
4. **28–38s** — done state. Cut to text: _"You didn't train them. You didn't get the
   email. The retainer's intact."_
5. **End card** — logo, "Free · open source · install once", the landing URL.

**Post body:**
> The email is always the same. "Can you just…"
>
> So I stopped answering it and gave the client the thing that actually kills it: a
> chat box that speaks their language and a confirm step so they can't wreck the
> store.
>
> 38 seconds, real WooCommerce, no edits. Watch the order change hands 👇

---

### Week 2

**Post 4 — Mon — [U] — text**
_Pillar: guardrailed access, from the owner's chair. Hook: "won't you break it?" answered by the person accused._

> Every developer says the same thing about giving me access: "you'll break
> something."
>
> And you know what — fair. I *have* broken something before. I clicked the wrong
> button in a dashboard I didn't understand and took the shop offline for an evening.
>
> So I get the fear. But notice what it does to me, the owner: I end up afraid to
> touch my own business.
>
> The thing that changed it wasn't a promise to be careful. It was a tool that won't
> *let* the careless click happen.
>
> I type what I want. It shows me exactly what will change — this price, this photo,
> this one order. I confirm. There's no "delete everything" button for me to find by
> accident, because it works one thing at a time, on purpose.
>
> That's not access. It's guardrailed access. First time I've felt allowed in my own
> store.
>
> The tool is ChatAdmin — link in the comments.

---

**Post 5 — Wed — [B] — still-image**
_Pillar: the objection-killer, laid out. Hook: the fear is correct — I designed around it._

**Image brief:** a clean, dark "guardrail card" graphic — three short rows with tick
icons:
- ✓ **Every change previews first** — nothing moves until they tap Confirm
- ✓ **One order / one page at a time** — no bulk-delete button to find by accident
- ✓ **Out of scope? It hands over a link** — never improvises a destructive workaround

Header: **"'They'll break the site.' Correct. Here's what I built around it."**
Footer: the logo + landing URL.

> "I'd never give a client admin access — they'll break something."
>
> Same. That fear is correct. It's exactly what I designed the whole product around.
>
> A client using ChatAdmin can't break the store, for three concrete reasons — not
> promises, enforced rules:
>
> → every change previews first, and nothing happens until they tap Confirm
> → it works one order or one page at a time — there's no bulk-delete to stumble into
> → when a request is out of scope, it hands them a link to the right admin page
> instead of inventing a risky shortcut
>
> "Access" is what you're scared of. This is guardrailed access. Different thing.
>
> Link in the comments.

---

**Post 6 — Fri — [U] — video (30–40s, multilingual)**
_Pillar: in their language, literally. Hook: the same request, three languages, no translation._

**Video script:**
1. **0–3s** — text: _"Your client doesn't think in English. Your admin panel does."_
2. **3–13s** — type in Lithuanian: _"pažymėk užsakymą 2833 kaip panaudotą."_ Order
   card renders correctly.
3. **13–23s** — clear it, type in Polish: _"zmień cenę butów na 59 zł."_ Preview
   renders.
4. **23–33s** — confirm step. Caption: _"Same confirm step. Any language. Nothing
   breaks."_
5. **End card** — "LT · PL · RU · EN out of the box", logo, URL.

**Post body:**
> Almost every "AI for WordPress" tool assumes your client thinks in English.
>
> Mine don't. They run shops in Lithuanian and Polish, and the WooCommerce admin
> screen doesn't meet them halfway.
>
> So ChatAdmin does. "Pažymėk kaip panaudotą" works. "Zmień cenę" works. Same preview,
> same confirm, nothing lost in translation. 33 seconds 👇

---

### Week 3

**Post 7 — Mon — [B] — text**
_Pillar: the real cost, build-in-public honesty. Hook: the hours you resent are the ceiling on your business._

> I added it up once and wished I hadn't.
>
> Last year I spent roughly six hours a week on client edits I could have taught a
> teenager in an afternoon. Price changes. Photo swaps. "Mark this order paid."
>
> Six hours a week is about 40 minutes every working day answering the same four
> questions. But here's the part nobody prices in: those aren't just annoying hours.
> They're the hours I *wasn't* scoping the next build.
>
> So the helpdesk work isn't only a tax on my evenings. It's the ceiling on the whole
> agency. I built a business that punishes me for shipping.
>
> That math is why ChatAdmin exists. Not to look clever — to hand the five-minute
> tickets back to the people asking, safely, so I can go sell the ten-thousand-euro
> project instead.
>
> Building it in the open. Link in the comments if you want to watch.

---

**Post 8 — Wed — [U] — still-image (before/after)**
_Pillar: the outcome, shown. Hook: the queue that emptied itself._

**Image brief:** a two-panel before/after.
- **Before** — a phone "Messages to Dev" thread, four grey bubbles stacked: _"change
  price?", "swap photo?", "is order 2833 paid?", "update opening hours?"_ — all
  one-sided, no replies.
- **After** — the same shop owner's screen, a single ChatAdmin chat: _"changed the
  price to €59 ✓ · swapped the photo ✓ · marked 2833 paid ✓"_ — with tidy confirm
  ticks.
Header across the top: **"Same four jobs. One of these needed a developer."**

> Four little jobs. Every one used to start with "sorry to bother you."
>
> Change a price. Swap a photo. Check if an order's paid. Fix the opening hours.
>
> None of them are hard. All of them used to wait in a developer's inbox behind
> real, paid project work — because the only way in was through a dashboard built for
> him, not for me.
>
> Now the owner does the four jobs herself, in plain words, with a confirm step on
> each one. The developer never sees the ticket. The owner never waits three days.
>
> Nobody lost here. That's the point. Link in the comments.

---

**Post 9 — Fri — [B] — text (trust close, the handoff)**
_Pillar: trust — it knows its limits. Hook: the most reassuring thing it does is refuse._

> The feature that sold me on my own product is the one where it says no.
>
> When a client asks ChatAdmin for something outside what it can safely do, it
> doesn't guess. It doesn't invent a workaround. It hands them a link straight to the
> right admin page and steps back.
>
> That sounds small. It's the whole trust model.
>
> Every horror story about "AI touching production" is really a story about software
> that improvised when it should have stopped. A tool you hand to a non-technical
> client has to know its own edges better than a clever one knows its tricks.
>
> So ChatAdmin does the boring, trustworthy thing: it acts only where it's sure, it
> previews everything first, and where it isn't sure, it hands off instead of
> dead-ending.
>
> That's how you give someone the keys without lying awake about it.
>
> Free, open source, install once. Link in the comments.

---

### After the 9: how the batch feeds the gates

- Rank all 9 by the §2 win bar. The clear winner (or top 2) enters **G1**.
- Note the pattern: did **buyer** or **user** posts win? **text, image, or video**?
  That pattern sets the next batch's mix — double down, don't diversify.
- Losing hooks aren't failures; they're answers. Log the one-sentence lesson each.

---

## 5. Ad-scenario templates (built only from a G0/G1 winner)

Rule: **never invent ad creative cold.** An ad is a *proven organic hook* rebuilt
native to a channel. Below is the template per channel — fill the `{winning hook}`.

### 5.1 Meta / Instagram (G2 first channel — cheapest reads)
- **Objective:** Traffic → landing page (switch to Conversions once install tracking fires).
- **Audience:** interests = WooCommerce, WordPress, Shopify, small-business owner,
  web design agency; + a lookalike of landing-page visitors once the pixel has data.
- **Creatives to ship (test 3, one variable each):**
  - **Still** — the winning post's image (e.g. the guardrail card or the before/after),
    ≤ 20% text, hook as the first line.
  - **Text/feed** — the winning post body cut to ~125 words, painful-moment first line.
  - **Video** — the 30s demo, captions baked, hook in the first 2 seconds.
- **PASS bar:** cost-per-landing-click ≤ €1.50, or cost-per-install ≤ €25.

### 5.2 TikTok (G2 second channel)
- **Format:** the demo video only, but re-cut TikTok-native — faster, a spoken or
  bold-caption hook in the first 1.5s, trending-sound-friendly.
- **Angle that fits TikTok:** the *user* pain ("it's my store and I can't touch it")
  travels better here than the B2B agency math.
- **PASS bar:** cost-per-landing-click ≤ €1.00 (TikTok clicks run cheap; watch for
  junk traffic — check landing dwell time, not just clicks).

### 5.3 Google Search (G2 last — intent test, low expectations)
- **Why last:** interruption channels create demand; Google only harvests existing
  search demand, and this category barely has any yet. Test small, expect it to lose.
- **Keywords to test (exact/phrase):** `give client wordpress access safely`,
  `let client edit woocommerce orders`, `woocommerce client dashboard`, `stop client
  wordpress support requests`, `simplify wordpress admin for clients`.
- **Ad:** headline = the reframe ("Give clients access without the 9pm emails"),
  description = guardrail + free/open-source.
- **PASS bar:** cost-per-install ≤ €30, or ≥ 1 install per €30 spent. If zero
  conversions at €100, kill Google and reallocate — that's a valid, cheap finding.

### 5.4 Creative production note
At each G2 gate I'll write the exact ad copy + the image/video brief from the winning
hook. Stills and text I can spec fully; video needs a screen-recording pass on the
dev rig (the `localhost` demo setup already exists).

---

## 6. Influencer stage (G3 — winners only)

Only a hook that won G1 **and** a G2 channel gets an influencer. You're not buying
reach; you're buying a trusted voice repeating a message you already proved converts.

**Who qualifies (nano/micro, not celebrity):**
- WordPress / WooCommerce / web-agency creators, **2k–25k** followers.
- Their audience = agency owners and freelancers (buyer) OR small e-commerce owners
  (user) — match the influencer to the *winning protagonist* from §4.
- Engagement rate > 3% on their own posts (a small engaged account beats a big dead one).

**The €100 model:** at this size, €100 is a realistic flat rate for one post/short,
or a gifted-setup + affiliate combo (offer a tracked link; ChatAdmin is free, so the
"deal" is a genuinely useful tool, not a discount code).

**The brief you hand them (don't let them freestyle the hook):**
> "Here's a 30-second story that's already proven with my audience: `{winning hook}`.
> Tell it in your own voice to *your* people. Show the confirm step — that's the part
> that makes it trustworthy. Link: `{tracked URL}`."

**PASS bar:** the influencer post beats the channel's paid cost-per-landing-click, or
drives ≥ N installs on the tracked link (set N from G2 economics). If yes → recruit
2–3 more like them. If no → influencers aren't the lever; put the €100 back on the
winning paid channel.

---

## 7. Tracking (so the gates aren't guesses)

- **Per-post UTM** on every landing link:
  `?utm_source=linkedin&utm_medium=organic&utm_campaign=gate&utm_content=w1p1`
  (`w1p1` = week1 post1). Paid gates use `utm_medium=paid` and
  `utm_source=meta|tiktok|google`, `utm_content={creative}`.
- **Landing page:** confirm it records visits (GitHub Pages + a lightweight analytics
  that respects the UTM — Plausible/Umami or GA4). Without this, "clicks" are
  unmeasurable and the gates collapse into vibes.
- **Install attribution:** GitHub release-download count (pre-WP.org) or WP.org active
  installs (once listed) as the north-star; UTM landing-clicks as the leading
  indicator between them.
- **One weekly 20-minute review:** fill any gate scorecards, pick the week's winner,
  note one thing to double down on and one to drop.

---

## 8. LinkedIn company-page setup kit

I can't create the page — signup, admin role, and verification need you. Here's the
copy-paste-ready kit; you create, I load content.

**Page basics**
- **Name:** ChatAdmin
- **Tagline (255 char):** _Stop being your clients' WordPress helpdesk. ChatAdmin lets
  your clients run their own WooCommerce store & content by chat — plain language, any
  language, with a confirm step so nothing breaks. Free & open source._
- **Industry:** Software Development
- **Company size / type:** 1 employee · Self-employed / Privately Held
- **Website:** the landing URL (with `?utm_source=linkedin&utm_medium=profile`)
- **Cover image:** use the existing asset `site/social/chatadmin-linkedin-cover.png`.
- **Logo:** the ChatAdmin mark on dark (from `site/social/` / the rebranded wp.org assets).

**About section (paste as-is):**
> Your clients don't want to learn WordPress. They never did — that was never the deal.
>
> ChatAdmin gives them a different way in: a chat box where they say what they want —
> "mark order 2833 used," "change the price to €59," "swap the front-page photo" — in
> plain language, in any language. Every change previews first and only happens when
> they tap Confirm. It works one thing at a time. There's no bulk-delete to find by
> accident, and when a request is out of scope it hands over a link instead of
> improvising.
>
> The result: your clients self-serve the five-minute jobs, and the 9pm "can you
> just…" tickets stop landing in your inbox. You keep the retainer. You lose the
> helpdesk shift.
>
> Free, open source, self-hosted. Bring your own AI key. Install once →

**Specialties / keywords:** WordPress, WooCommerce, client management, AI assistant,
web agencies, freelance developers, no-code, plain-language admin, guardrailed access.

**CTA button:** "Learn more" → landing URL (UTM tagged).

**Checklist (needs you):**
1. Create the page, set name/tagline/industry/logo/cover from above.
2. Add Gintaras as page admin.
3. Connect the page to Buffer (or grant me access) so I can queue the 9 posts.
4. Confirm the landing URL + analytics is live so UTM clicks are counted (§7).

**Then I:** load the 9 posts on the Mon/Wed/Fri schedule, produce the 3 still images
and 2 video scripts into shootable form, and stand by for the first weekly review.

---

## 9. First actions

**You:**
1. Create + brand the LinkedIn company page (§8), add Gintaras as admin, connect Buffer.
2. Confirm landing analytics counts UTM clicks (§7) — this is load-bearing; the gates
   die without it.

**Me, on your go:**
1. Load the 9 posts into Buffer against the Mon/Wed/Fri calendar.
2. Turn the 3 image briefs into finished stills and the 2 video scripts into a
   shot-ready recording checklist.
3. Run the weekly review, fill the first gate scorecard when a winner appears, and
   write the G2 ad creative from that winner.
