# The Complete WordPress SEO & AI‑SEO (AEO/GEO) Master Guide

*A practical, step‑by‑step playbook for ranking in Google **and** getting cited by ChatGPT, Claude, Perplexity, Gemini and Google AI Overviews.*

Synthesized from 8 source documents (Hostinger, Semrush, SEOPress, AIOSEO/Yoast/Rank Math guides, the LinkedIn "AI‑Ready WordPress" guide, the Savvy AEO Checklist, and the WordPress 7.0 Readiness Checklist).

---

## How to use this guide

This guide is organized in two halves:

1. **Classic SEO foundation** (Parts 1–11) — everything that makes you rank in traditional search. This is still the base layer; AEO/GEO is built *on top* of it.
2. **AI SEO: AEO & GEO** (Parts 12–14) — how to be the *answer* that AI engines quote, plus WordPress 7.0 readiness.

At the end you'll find a **prioritized 30‑day action plan** and a **one‑page checklist** you can work through.

**Key definitions (memorize these):**

| Term | Goal | Targets | Main signals | Timeline |
|---|---|---|---|---|
| **SEO** | Rank in the results list | Google, Bing | Keywords, backlinks, Core Web Vitals | 3–6 months |
| **AEO** (Answer Engine Optimization) | *Be* the answer (snippets, voice, AI Overviews) | AI Overviews, voice assistants | Structured data, Q&A format, direct answers | Weeks–months |
| **GEO** (Generative Engine Optimization) | Get *cited* by chatbots | ChatGPT, Perplexity, Claude, Gemini | E‑E‑A‑T, entity clarity, original data | ~2–4 weeks to start |

**Why this matters now:** AI Overviews appear in 13%+ of searches; AI referral traffic grew ~357% year‑over‑year; sites with structured data see **30–35% higher AI citation rates**, and pages with **FAQ schema are 3.2× more likely to appear in AI Overviews**. AI crawlers already make up nearly **30% of all web crawler traffic**.

---

# PART A — CLASSIC SEO FOUNDATION

## 1. Foundation: Hosting, SSL, Theme

**Hosting**
- Choose reliable, fast managed WordPress hosting — performance is a confirmed ranking factor and affects every other metric.
- **Avoid cheap shared hosting** if you can; "noisy neighbor" sites steal resources and hurt speed. Prefer a dedicated WordPress plan.

**SSL / HTTPS (non‑negotiable)**
- Install an SSL certificate so the site serves over HTTPS. Google favors secure sites and Chrome flags non‑SSL pages as "Not Secure."
- Use a free cert (most hosts include one) or buy from a CA. Easiest activation: install the **Really Simple SSL** plugin → Activate → "Activate SSL."

**Theme**
- Pick a **lightweight, fast, responsive, SEO‑friendly theme**. Good options: **Astra** (280+ starter templates, WooCommerce‑ready), **OceanWP** (built‑in schema, accessibility tools), **GeneratePress**. Check ratings/reviews on ThemeForest/Creative Market.
- The theme must be **mobile responsive** — ~57% of web time is mobile and Google uses **mobile‑first indexing**.

**Security & backups (foundation hygiene)**
- Security plugin with strong passwords, 2FA, brute‑force protection, malware scanning: **Wordfence**, **All‑In‑One Security**, or **Jetpack**.
- Backups: **UpdraftPlus** or **Duplicator**.
- Keep core, theme, and plugins updated. Most WP vulnerabilities come from plugins/themes, not core — install only well‑rated, actively maintained plugins, and don't stack overlapping tools.

---

## 2. Install & Configure an SEO Plugin

Pick **one** SEO plugin (don't run several — they create duplicate meta tags):

- **Yoast SEO** — most popular; auto schema, readability analysis, templates.
- **Rank Math** — real‑time Content AI scoring, built‑in FAQ/schema blocks.
- **All in One SEO (AIOSEO)** — AI writing assistant, Link Assistant, FAQ schema, noindex controls.
- **SEOPress** — lightweight; OpenAI metadata generation; advanced sitemaps.

**Initial setup (Yoast example):**
1. Plugins → Add New → search "Yoast SEO" → Install → Activate.
2. Run first‑time configuration → "Start SEO data optimization."
3. **Site representation:** choose Organization or Person; add name + upload logo.
4. **Social profiles:** add your social URLs.
5. **Templates:** Yoast SEO → Settings → Content types → set title & meta‑description templates for Posts, Homepage, Categories, Tags.

---

## 3. Crawling & Indexing

- **Allow indexing:** Settings → Reading → ensure **"Discourage search engines from indexing this site" is UNCHECKED**.
- **Site address consistency:** pick **www OR non‑www** (Google treats them as separate sites) and set both fields under Settings → General.
- **robots.txt** (`yoursite.com/robots.txt`): block low‑value paths (admin, plugin dirs) to save crawl budget; **but explicitly allow AI crawlers** — see Part 13.
- **XML sitemap:** your SEO plugin generates one (Yoast: `/sitemap_index.xml`). Verify it loads.
- **Submit your sitemap:**
  - **Google Search Console** → Sitemaps → paste sitemap URL → Submit.
  - **Bing Webmaster Tools** → Sitemaps → Submit.
- **Set up analytics:** install **Google Search Console** (impressions, clicks, position, crawl errors, URL Inspection tool) and **Google Analytics 4** (traffic sources, behavior, conversions).
- **Noindex low‑value pages:** thank‑you pages, legal pages, thin archives. In AIOSEO: page → Advanced → Robots Settings → turn off "Use Default" → check "No Index."
- **Prevent thin archive indexing:** in Yoast, disable search‑results display for tag/date/format archives you don't want indexed.

---

## 4. Permalinks & URLs

- Settings → Permalinks → select **"Post name"** → Save. (Never use `?p=123` or date‑based structures.)
- URL best practices:
  - Include the **focus keyword** as the slug.
  - Keep it **short, descriptive, lowercase**.
  - Use **hyphens (-)**, never underscores (_).
  - No dates, numbers, stop‑words, or non‑ASCII characters.
  - Example: `yoursite.com/wordpress-seo-checklist` ✔ vs `yoursite.com/blog?id=101` �’
- WordPress auto‑redirects old URLs after a permalink change, but for major slug changes set up a **301 redirect** anyway.

---

## 5. Keyword Research

**Metrics that matter:** search volume, keyword difficulty (KD%), and **search intent** (Navigational, Informational, Commercial, Transactional). Target **high‑volume, low‑competition** terms.

**Prioritize long‑tail keywords** — longer, specific phrases convert better and are easier to rank (e.g., "best dog food for senior labradors" > "dog food").

**Tools:**
- Free: **Google Keyword Planner**, **Google autocomplete**, **Ubersuggest**, **AnswerThePublic** (question‑based), forums/Reddit/Quora.
- Paid: **Semrush** (Keyword Magic Tool, Keyword Gap, Organic Research), **Ahrefs**, **Moz Pro**, **Surfer SEO**, **Frase**.

**Workflows:**
- **Keyword Magic Tool:** enter seed keyword → filter by volume, KD%, intent.
- **Keyword Gap:** enter your domain + up to 4 competitors → focus on the **"Untapped"** and "Missing" lists.
- **Content gap analysis:** compare your coverage to top‑ranking SERP pages; fill the missing subtopics/questions.

**Topic clusters (critical for both SEO and AI):**
- Build a **pillar page** (broad, in‑depth) + multiple **cluster pages** (one subtopic each).
- Pillar links to every cluster; each cluster links back to the pillar.
- This signals **topical authority** and prevents keyword cannibalization. Aim for **8–12 interlinked posts per cluster**.

---

## 6. On‑Page SEO

**Title tags / meta titles**
- The clickable SERP headline. Keep **under ~60 characters**.
- Put the primary keyword near the **beginning**. Make it descriptive and compelling.

**Meta descriptions**
- Not a direct ranking factor but drives **CTR**. Keep **~150–160 characters**.
- Use action verbs + a call to action ("Discover…", "Read our guide").

**Headings**
- **One H1 per page**; logical hierarchy H1 → H2 → H3 (never skip levels).
- Primary keyword in the H1; variations/secondary keywords in H2/H3 — no stuffing.
- **For AEO: phrase headings as the questions people actually ask** ("What is AEO?" not "Overview").

**Keyword placement**
- Focus keyword in: URL slug, H1, first paragraph, naturally throughout, and the meta title. Avoid keyword stuffing — treat plugin "optimization scores" as a guide, not a target.

---

## 7. Content Quality & E‑E‑A‑T

Google (and AI engines) reward **Experience, Expertise, Authoritativeness, Trust**.

- **Match search intent** — directly answer the query the page targets.
- **Be reliable & cite sources** — link to studies/primary data; keep facts accurate and current.
- **Author authority:** detailed author bios with credentials and social proof; first‑party data, case studies, testimonials, verified reviews.
- **Write for humans:** simple language, short paragraphs, white space, lists/tables, examples, and visuals.
- **Freshness:** schedule **quarterly content audits**; show a visible "last updated" date (plugin: **WP Last Modified Info**) and keep `dateModified` current. Refreshing a strong page often beats writing a new one.
- **AI as assistant, not author:** use AI for the repetitive 80% (research, briefs, drafts, alt text) and human judgment for the 20% (accuracy, original examples, brand voice). Google does **not** penalize AI‑assisted content per se — it penalizes *unhelpful* content. Never publish unedited AI drafts.

---

## 8. Structured Data / Schema

Schema helps engines understand content, unlocks **rich results**, and is the single biggest lever for AI citation.

- Most SEO plugins (Yoast, Rank Math, AIOSEO) add **Article + Organization** schema automatically and offer generators for more types.
- In Yoast per post: Schema tab → set Page type + Article type.
- **Advanced/entity schema:** **WordLift** builds a knowledge graph via NLP; **Schema Pro** for advanced types.
- **Priority schema types for AEO** (see Part 12 for detail): **FAQPage**, **Article**, **HowTo**, **Organization**, **Speakable**.
- Validate with **Google's Rich Results Test**.

---

## 9. Image Optimization

- **Compress before/after upload:** **TinyPNG**, **ShortPixel**, **Imagify**, or the **Smush** plugin. Keep images **under ~200 KB**.
- **Modern formats:** serve **WebP / AVIF** instead of JPEG/PNG (use JPEG for photos, PNG only for transparency).
- **Lazy load** images — but configure **only one** lazy‑load source (theme *or* core *or* caching plugin) to avoid conflicts.
- **Alt text:** describe what's actually shown; be specific ("monarch butterfly on a purple coneflower" > "butterfly"); include a keyword only if natural; skip "image of." AI plugins can bulk‑generate alt text — spot‑check it.
- **File names:** descriptive, keyword‑relevant, hyphenated.

---

## 10. Site Structure & Internal Linking

- **Hierarchical structure:** Homepage → Category → Subcategory → Post. Keep the top menu simple with clear labels.
- **Categories vs tags:** categories = broad, hierarchical topics (keep to ~4–5 parents); tags = specific cross‑cutting keywords. Optimize category pages (keyword in name, description, clean slug).
- **Breadcrumbs:** enable via Yoast (Settings → Advanced → Breadcrumbs) or theme support — aids navigation, crawlability, and shows in SERPs. (WordPress 7.0 adds a native Breadcrumbs block.)
- **Internal linking:**
  - Link to relevant content with **descriptive, varied anchor text** (never "click here").
  - Put links in the main content, not header/footer.
  - Link from high‑authority pages to important target pages.
  - **Eliminate orphan pages** (pages nothing links to) — they barely get indexed.
  - Tools to automate/audit: **AIOSEO Link Assistant**, **Link Whisper**, **Rank Math** suggestions, **Semrush Site Audit** (Internal Linking report). Always review suggested anchors.

---

## 11. Performance & Core Web Vitals

Speed is a ranking factor **and** AI crawlers time out on slow sites (often after **1–5 seconds**). **Target full load under 2 seconds.**

**Core Web Vitals thresholds:**

| Metric | Good | Needs work | Poor |
|---|---|---|---|
| **LCP** (loading) | < 2.5 s | 2.5–4 s | > 4 s |
| **INP** (responsiveness, replaced FID in 2024) | < 200 ms | 200–500 ms | > 500 ms |
| **CLS** (visual stability) | ≤ 0.1 | 0.1–0.25 | > 0.25 |

**Levers:**
- **Caching:** **WP Rocket** (premium, recommended across sources), **W3 Total Cache**, **WP Super Cache**, **LiteSpeed Cache**.
- **CDN:** **Cloudflare** (free tier), **KeyCDN**, Fastly, your host's CDN.
- **Minify** CSS/JS/HTML: **Autoptimize**, WP Rocket, Fast Velocity Minify. Clear cache and test before/after.
- **GZIP compression** + **browser/object caching**.
- **Database cleanup:** **WP‑Optimize**, **Advanced Database Cleaner**, **WP‑Sweep** (remove revisions, transients, spam).
- **Server‑side rendering / clean semantic HTML** — see Part 12; this matters more than ever for AI.

**Test with:** **Google PageSpeed Insights**, **GTmetrix**, **Pingdom**, and Search Console's Core Web Vitals report.

⚠️ **One job, one tool:** don't enable lazy loading or minification in multiple plugins simultaneously — pick a single source of truth and test in PageSpeed Insights.

---

## 11b. Off‑Page SEO, Backlinks & Local SEO

**Backlinks** (still a top ranking factor):
- Create genuinely link‑worthy content, then do outreach to relevant bloggers/site owners.
- Monitor brand mentions (**Google Alerts**, **Brand24**, **BrandMentions**) and ask for links where you're mentioned unlinked.
- Guest posting / digital PR; track total backlinks, referring domains, anchor distribution, and lost/new links with Ahrefs/Semrush.
- Enable pingbacks/trackbacks under Settings → Discussion; require manual comment approval to fight spam.

**Local SEO** (if you serve a location):
- Add **LocalBusiness schema** (hours, address, description) via your SEO plugin.
- Claim and optimize **Google Business Profile** and **Bing Places**.
- Target local keywords ("car repairs in [city]").
- Build **local citations** in reputable directories (Yell + industry‑specific).

---

# PART B — AI SEO: AEO & GEO

> Everything above is the prerequisite. AEO/GEO requires a clean, fast, mobile‑friendly, server‑rendered site with solid schema. Now layer these on top.

## 12. The 7 Pillars of WordPress AEO

### Pillar 1 — Content structure AI can extract
- Put a **direct, quotable answer in the first 1–3 sentences** after every H2.
- Use **question‑framed headings** that mirror natural‑language queries.
- **Short paragraphs** (1–2 sentences), one idea each ("answer blocks").
- **Tables** for comparisons; **ordered lists** for procedures, **unordered** for options. AI parses structured info far better than dense prose.
- Define **entities plainly** (what a thing is, does, and how it differs).
- Keep every claim **verifiable and cited**.

### Pillar 2 — Schema markup (JSON‑LD) — highest leverage

| Schema | Why it matters |
|---|---|
| **FAQPage** | **3.2× more likely to appear in AI Overviews** — top priority. Mark up *real* questions. |
| **Article** | Author + datePublished + dateModified → credibility. Verify present and accurate. |
| **HowTo** | Step‑by‑step content gets extracted for procedural queries. |
| **Organization** | Establishes your brand as an entity in AI knowledge graphs (name, logo, social profiles). |
| **Speakable** | Marks TTS‑friendly sections for voice assistants (beta). |

Yoast/Rank Math add Article + Organization automatically. Add **FAQPage** via Rank Math's FAQ block, the Yoast FAQ block, or manual JSON‑LD. Schema lifts AI citation probability ~30–35%.

### Pillar 3 — `llms.txt` and AI content control
- **`llms.txt`** = a curated Markdown index of your most important pages with short descriptions, placed at site **root** (`yoursite.com/llms.txt`). Think "robots.txt for AI," giving crawlers a roadmap.
- **`llms-full.txt`** = your full content as a single Markdown file for direct ingestion (no per‑page crawling).
- Use a free **llms.txt generator**. Not every AI reads it yet, but it's low effort and adoption is rising.

Example `llms.txt`:
```
# My Brand — AI Access File
> We welcome AI crawlers that adhere to our usage guidelines.

## Core Guides
- LLM SEO Playbook: In-depth optimization tactics
- Performance Checklist: Speed optimization best practices
- Industry Reports: Latest market research and insights
```

### Pillar 4 — AI crawler management (robots.txt)
To **be cited**, you must let AI crawlers in. Named bots: **GPTBot** (OpenAI), **ClaudeBot** (Anthropic), **PerplexityBot**, plus Google‑Extended, Bingbot, etc.

```
User-agent: GPTBot
Allow: /

User-agent: ClaudeBot
Allow: /

User-agent: PerplexityBot
Allow: /
```

- If crawler load is overwhelming, **throttle respectfully rather than block**.
- You *can* choose to block training‑only bots while allowing search/citation bots — decide per your strategy.
- Confirm crawling by checking server logs for these user‑agents.

### Pillar 5 — AI traffic tracking
- AI referrals (chat.openai.com, claude.ai, perplexity.ai) land in **GA4 as generic referral/direct** traffic.
- Create a custom **"AI" channel group in GA4 with regex** matching known AI referrers (~10 min setup).
- Monitor brand mentions/citations with **Ahrefs** and **Perplexity Pages**.

### Pillar 6 — E‑E‑A‑T & topical authority
- Build comprehensive, interlinked **content clusters** (pillar + 8–12 cluster posts) using your category structure.
- Strong internal linking + author credentials + original data = what gets you quoted.

### Pillar 7 — Technical foundation for AI
- **Server‑rendered HTML** — AI crawlers largely **don't execute JavaScript**; a JS‑rendered page can look empty to them. Use SSR and HTML5 semantic tags (`<article>`, `<section>`) with proper heading hierarchy.
- **Fast loading** (crawlers have time budgets — under 2s).
- **Clean canonical URLs** — one indexable URL per piece of content.
- **Accurate XML sitemap** (used for discovery).
- **Mobile‑friendly rendering** (crawlers often use mobile user‑agents).

---

## 13. 15‑Minute AEO Audit

Run this on any existing site:
1. **Schema:** run Google's **Rich Results Test** on your homepage — check for Article, Organization, FAQPage.
2. **robots.txt:** open `yoursite.com/robots.txt` — are GPTBot/ClaudeBot/PerplexityBot allowed? (No directives = allowed by default.)
3. **Speed:** run **PageSpeed Insights** — AI crawlers skip slow sites.
4. **AI visibility:** ask ChatGPT/Perplexity a question your site should answer — are you cited?
5. **llms.txt:** check `yoursite.com/llms.txt` (404 = create one).

**Expected timelines:** schema can surface in AI Overviews within days–weeks; GPTBot/PerplexityBot re‑index in **2–4 weeks**; topical authority/E‑E‑A‑T takes **3–6 months**.

---

## 14. WordPress 7.0 Readiness (Release: May 20, 2026)

WordPress 7.0 brings native AI infrastructure but also breaking changes. **Don't auto‑update production on launch day** — test on staging and wait for **7.0.1 (~2 weeks)**.

**What's shipping:**
- **WP AI Client + Abilities API** — a provider‑agnostic core API (`wp_ai_client_prompt()`) with official provider plugins for **Anthropic, OpenAI, Google**; API keys managed under **Settings → Connectors**. *AI features are NOT on by default* — only the infrastructure ships.
- **DataViews** replaces `WP_List_Table` on Posts/Pages/Media — the biggest dev‑facing change.
- New core blocks: **Breadcrumbs, Icons**; Block API v3 (iframed editor); native per‑device block visibility.
- **PHP 7.4 minimum** (8.3+ recommended); **MySQL 8.0+ / MariaDB 10.6+** recommended.
- Real‑time collaboration was **pulled** (deferred to 7.1).

**Readiness checklist:**
- Confirm **PHP 7.4+** and **MySQL 8.0+/MariaDB 10.6+**.
- Take a full **server snapshot** (files + DB) and verify the backup restores.
- Update plugins/themes; verify changelogs mention "WordPress 7.0"/"DataViews compatibility" — especially **WooCommerce, ACF, Yoast, Rank Math, Elementor, Divi, WP Rocket, Astra/GeneratePress**.
- **Highest risk:** any plugin that customizes the wp‑admin post list (custom columns, bulk actions, page builders with admin hooks) — DataViews replaces that surface.
- Grep custom code for breaking surfaces: `manage_posts_columns`, `manage_posts_custom_column`, `bulk_actions-`, `WP_List_Table`, `groupByField`, `effect(` (→ `watch()`), `state.navigation.hasStarted/hasFinished`, `add_theme_support('html5', ['script'])`, and any block `apiVersion: 2` (→ 3).
- **Compliance/enterprise:** the AI Client is the first sanctioned core path to call OpenAI/Anthropic/Google — write a policy for which providers (if any) are allowed under Settings → Connectors *before* upgrading; default to "none" until a DPA is signed.
- **Day‑of:** test on a clean staging clone, smoke‑test rendered HTML (not just 200s), roll out to a canary cohort for 48h, keep a tested rollback, and flush caches in order: opcache → object → page → CDN.

---

# PART C — ACTION PLANS & CHECKLISTS

## 30‑Day Implementation Plan

**Week 1 — Technical foundation**
- Confirm fast hosting + SSL + responsive theme.
- Install caching (WP Rocket) + CDN (Cloudflare); compress images; minify CSS/JS.
- Hit **< 2s load** / pass Core Web Vitals (verify in GTmetrix + PageSpeed Insights).
- Ensure robots.txt **welcomes AI crawlers**; submit sitemap to GSC + Bing.

**Week 2 — Authority & structure**
- Install/configure one SEO plugin; set title/meta templates.
- Implement Article + Organization schema; add detailed author bios + credentials.
- Set permalinks to Post name; fix orphan pages; enable breadcrumbs.
- Publish/upgrade an expert pillar page with first‑party data.

**Week 3 — Content optimization**
- Keyword + intent research (Semrush/Frase/AnswerThePublic); build topic clusters (8–12 posts each).
- Rewrite top posts with **question‑framed headings, direct first‑paragraph answers, short paragraphs, tables/lists**.
- Add **FAQ sections + FAQPage schema** to your most important posts.
- Generate descriptive alt text; serve WebP/AVIF.

**Week 4 — AI exposure & monitoring**
- Deploy **llms.txt** (and optionally llms-full.txt).
- Set up the **GA4 "AI" channel group**; monitor mentions in Ahrefs/Perplexity.
- Run the 15‑minute AEO audit; test if ChatGPT/Perplexity cite you.
- Schedule **quarterly content refreshes**; turn on visible "last updated" dates.

---

## One‑Page Master Checklist

**Foundation**
- [ ] Fast managed hosting + SSL/HTTPS active
- [ ] Lightweight responsive, SEO‑friendly theme
- [ ] Security plugin + automated backups + everything updated

**Indexing**
- [ ] Indexing allowed (Reading setting unchecked)
- [ ] www/non‑www chosen consistently
- [ ] Permalinks = Post name
- [ ] XML sitemap submitted to GSC + Bing
- [ ] GSC + GA4 connected
- [ ] Low‑value pages noindexed; no orphan pages

**On‑page**
- [ ] Title < 60 chars, keyword first
- [ ] Meta description ~150–160 chars with CTA
- [ ] One H1; logical, question‑framed headings
- [ ] Keyword in URL/H1/first paragraph (no stuffing)
- [ ] Descriptive alt text; WebP/AVIF; images < 200 KB

**Content & authority**
- [ ] Matches search intent; cites sources
- [ ] Author bios + credentials (E‑E‑A‑T)
- [ ] Topic clusters (pillar + 8–12 clusters, interlinked)
- [ ] Visible "last updated" dates; quarterly refresh schedule

**Performance**
- [ ] Caching + CDN + minification (one tool per job)
- [ ] Core Web Vitals pass (LCP < 2.5s, INP < 200ms, CLS ≤ 0.1)
- [ ] Total load < 2s

**AI SEO (AEO/GEO)**
- [ ] Server‑rendered, clean semantic HTML
- [ ] FAQPage schema on key posts (3.2× AI Overview boost)
- [ ] Article + Organization schema validated in Rich Results Test
- [ ] Direct answers in first 1–3 sentences; tables/lists
- [ ] robots.txt allows GPTBot / ClaudeBot / PerplexityBot
- [ ] llms.txt published at root
- [ ] GA4 "AI" channel group tracking referrals
- [ ] Monitoring AI citations (Ahrefs / Perplexity)

**WordPress 7.0**
- [ ] PHP 7.4+ (8.3+) and MySQL 8.0+/MariaDB 10.6+
- [ ] Tested on staging; waited for 7.0.1; backup restorable
- [ ] Admin‑customizing plugins verified DataViews‑compatible
- [ ] AI Connectors policy defined (default: none)

---

## Recommended Tool Stack (start lean — one plugin + one SaaS, add only for a clear gap)

| Job | Tools |
|---|---|
| All‑in‑one SEO | Yoast / Rank Math / AIOSEO / SEOPress |
| Keyword & content | Semrush, Ahrefs, Frase, Surfer SEO, AnswerThePublic |
| Schema/entities | Rank Math/AIOSEO generators, Schema Pro, WordLift |
| Internal links | AIOSEO Link Assistant, Link Whisper |
| Performance | WP Rocket, Cloudflare, Autoptimize, ShortPixel/Imagify, GTmetrix |
| Images | TinyPNG, ShortPixel, Smush |
| Monitoring | Google Search Console, GA4, Ahrefs, Perplexity Pages |
| AI access | llms.txt generator, robots.txt directives |

---

### The bottom line
Classic SEO gets you into the index; AEO/GEO gets you *quoted*. The winning formula is the same foundation done well — **fast, secure, server‑rendered, well‑structured, authoritative content** — plus the AI layer: **schema (especially FAQPage), direct answers, clean HTML, open AI crawlers, and llms.txt.** Use AI tools to do the work faster, but keep human expertise in the loop — that's the part no model can fake, and it's exactly what both Google and AI engines reward.
