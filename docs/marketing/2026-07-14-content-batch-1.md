# ChatAdmin — content batch #1 (first 2 weeks)

_Drafted 2026-07-14. Founder-voice (Gintaras's personal LinkedIn — the #1 B2B channel, live now).
Ready to load into Buffer as Ideas once Buffer is re-authorized._

**Brand:** ChatAdmin. **Angle (locked):** you can't train the ticket away — it's an interface
mismatch, not a knowledge gap. ChatAdmin fires the developer from the helpdesk job while they
keep the retainer. **Objection-killer:** guardrailed access (preview → confirm, one item at a
time, no bulk-destructive button). **Differentiator:** works in any language. **Proof/trust:**
free, open source, BYO key; now in review on the WordPress.org plugin directory.

**Install line (use until the directory is live):** "Free & open source — GitHub now, landing
soon on the WordPress.org directory." Once live: `wordpress.org/plugins/chatadmin`.

---

## 2-week posting rhythm (LinkedIn, 3–4×/wk)

| Day | Post | Pillar |
|---|---|---|
| Mon | #1 The 9:14pm email | Helpdesk trap |
| Tue | **Demo** (hero video, script below) | Show, don't tell |
| Thu | #2 "But won't they break it?" | Guardrailed access |
| Mon | #3 Building in public: I submitted it to wp.org | Trust / build-in-public |
| Wed | #4 Your client thinks in their language | Multilingual |
| Fri | #5 The reframe (you can't train the ticket away) | Thesis |

Reddit (r/WordPress, r/ProWordPress): don't post these — *reply* into "clients keep asking me
to do trivial edits" threads with the insight, link the demo only if it answers the question.

---

## Post #1 — The 9:14pm email  (pillar: the helpdesk trap)

> A client emailed me "can you change the price on the sneakers to €59?" at 9:14pm.
>
> It's a 20-second job. I've shown her how. Twice. I sent a Loom.
>
> She's not lazy — she runs a business. She just doesn't want to learn WooCommerce's order
> screen, and honestly, why would she? That was never the deal.
>
> I used to think the fix was better documentation. It isn't. You can't document someone into
> wanting to be a junior WordPress admin.
>
> The fix is a different interface: let her *type what she wants* — "change the sneakers to €59"
> — and have it happen, with a confirm step so she can't break anything.
>
> That's why I built ChatAdmin. Not to replace the developer. To fire the developer from the
> helpdesk job.
>
> Free and open source. Link + a 30-second demo in the comments. 👇

_First comment: demo video + "Free, open source, BYO API key: github.com/gynciuz/wpchat"_

---

## Post #2 — "But won't they break it?"  (pillar: guardrailed access)

> Every developer's first reaction to "let your client manage their own store":
>
> "I'd never give a client admin access — they'll break something."
>
> Same. That fear is correct, and it's exactly what I designed around.
>
> In ChatAdmin a client can't break the site, because:
> → every change previews first, and nothing happens until they tap Confirm
> → it works one order (or page) at a time — there's no "delete everything" button to find
> → when a request is out of scope, it hands them a link to the right admin page instead of improvising
>
> It's not access. It's guardrailed access. That's the whole difference between handing someone
> the keys and handing them a loaded gun.
>
> Demo in the comments.

---

## Post #3 — Building in public: I just submitted it to WordPress.org  (pillar: trust)

> I just submitted ChatAdmin to the WordPress.org plugin directory. 😅
>
> Scary, honestly — public reviews, a guideline gauntlet, a rating anyone can tank.
>
> But if the whole pitch is "let non-technical clients run their own site safely," it can't
> live behind a developer-only GitHub install. It has to be one click away for everyone.
>
> A few things I learned getting a plugin review-ready that I wish I'd known sooner:
> → the bundled auto-updater has to go (WordPress.org serves updates itself)
> → "WP" and "WordPress" can't be in your plugin name or slug
> → your text domain must match your slug exactly, or the automated scan flags it
> → Plugin Check has to come back clean before a human ever looks
>
> Now it's in the queue. I'll post the moment it's live. Building in public — follow along.

---

## Post #4 — Your client thinks in their language  (pillar: multilingual)

> Most WordPress AI tools are English-only. Your clients aren't.
>
> A shop owner in Vilnius doesn't type "mark order 2833 as used." She types "pažymėk 2833 kaip
> panaudotą." A client in Kraków types "zmień cenę butów na 59 €."
>
> ChatAdmin works in the words your client actually uses — English, Spanish, French,
> Portuguese, Hindi, Mandarin, German, and more. Not translated menus. The request itself, in
> their language, doing the real thing.
>
> If your clients aren't native English speakers, this is the difference between a tool they use
> and a tool they never open.
>
> Demo (in four languages) in the comments.

---

## Post #5 — You can't train the ticket away  (pillar: the thesis)

> Unpopular opinion for agency owners: the "can you just…" tickets are not a training problem.
>
> You've made the Loom. You built the simplified edit screen. You wrote the PDF. They still
> email you. Because the ticket was never a knowledge gap — it's an interface mismatch. Your
> client doesn't want to *learn* the tool; they want to *say the thing* and have it happen.
>
> And here's the part nobody prices in: every hour you spend as your clients' helpdesk is an
> hour you're not selling the next build. Support isn't just annoying — it's the ceiling on your
> studio. You built a business that punishes you for shipping.
>
> The fix isn't more docs. It's an interface that speaks your client's language, with a confirm
> step so they can't break anything. Then you keep charging the monthly fee and stop doing the
> 20-second jobs by hand.
>
> That's the whole idea behind ChatAdmin. Free, open source, link below.

---

## Hero demo video — script (muted, captioned, 4:5, ~18s)

Per the demo-video rules: **feeling first, product last; every caption self-contained; show
destinations, not navigation; bookend on the landing page.** Record one slow continuous take of
the real `/chat-admin` page; hold each screen ~3.5s; cut the cursor travel; one slow auto-zoom
per shot; white captions on a dark strip.

| # | Screen (held ~3.5s) | Caption (self-contained, white on dark) |
|---|---|---|
| 1 | ChatAdmin landing hero (scroll its own headline out of frame first) | You built the WordPress site. Now you're their helpdesk. |
| 2 | Same hero, or a stack of the dreaded emails | "Can you just change the price?" — the email that never stops. |
| 3 | The chat: client types *"mark order 2833 as used — customer spent €30 of €100"*; the order card renders | Your client types what they want, in plain words. |
| 4 | The **preview → Confirm / Cancel** bar, held | Nothing changes until they tap Confirm. They can't break the site. |
| 5 | The "✓ Applied" state | Done. You didn't get the email. You kept the retainer. |
| 6 | Back on the landing hero / CTA button | ChatAdmin — your clients run their own store by chat. Free & open source. |

**Cold-viewer check:** watch muted, no context — does card 1 make an agency owner feel seen in
3 seconds? Does every caption stand alone (no "it/this/she" pointing at an unseen thing)? Is it
honest (the client does the typing; the confirm step is real)?

**Repurpose:** the same take → LinkedIn video, YouTube Short, and (low effort) TikTok/IG Reels.

---

## Notes / needs-you
- **Re-authorize Buffer** so these load as Ideas (org "My Organization"). The connected channels
  are still Loupe-branded — post from your **personal** LinkedIn for now, or create a ChatAdmin
  company page and connect it.
- **Handles to claim** (ChatAdmin brand): `chatadmin` on LinkedIn (page), YouTube, X/Bluesky;
  domain `chatadmin.app` or similar for the landing page (currently `gynciuz.github.io/wpchat/`).
- Swap the install line to `wordpress.org/plugins/chatadmin` the day the directory listing goes live.
