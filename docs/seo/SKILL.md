---
name: wordpress-seo-and-ai-seo
description: Optimize a WordPress site for both classic search engines (SEO) and AI answer/generative engines (AEO/GEO — ChatGPT, Claude, Perplexity, Gemini, Google AI Overviews). Use when asked to audit, improve, or set up SEO/AI-SEO on a WordPress site, configure SEO plugins (Yoast/Rank Math/AIOSEO/SEOPress), add schema/structured data, set up llms.txt or AI-crawler access, improve Core Web Vitals, build topic clusters, or prepare a site for WordPress 7.0.
---

# WordPress SEO & AI-SEO (AEO/GEO) Optimization

## Purpose

Drive a WordPress site to high visibility in **both** traditional search results **and** AI answer engines. Classic SEO gets a page indexed and ranked; AEO/GEO gets it *quoted and cited* by AI. AEO/GEO is built on top of a solid SEO foundation — never skip the foundation.

## When to use this skill

Trigger on requests to: audit/improve WordPress SEO, set up an SEO plugin, add structured data, optimize for AI Overviews / ChatGPT / Perplexity citations, create `llms.txt`, manage AI crawlers, fix Core Web Vitals, build topic clusters, or prepare for WordPress 7.0.

## Core concepts (apply the right one)

| Term | Goal | Targets | Main signals | Timeline |
|---|---|---|---|---|
| **SEO** | Rank in results list | Google, Bing | Keywords, backlinks, Core Web Vitals | 3–6 months |
| **AEO** | *Be* the answer (snippets, voice, AI Overviews) | AI Overviews, voice | Structured data, Q&A, direct answers | Weeks–months |
| **GEO** | Get *cited* by chatbots | ChatGPT, Perplexity, Claude, Gemini | E-E-A-T, entity clarity, original data | ~2–4 weeks to start |

Key facts that justify priorities: FAQPage schema → **3.2× more likely** to appear in AI Overviews; structured data → **30–35% higher** AI citation rate; AI crawlers ≈ **30%** of crawler traffic; AI crawlers often **time out after 1–5s** and **don't execute JavaScript**.

## Recommended workflow

When optimizing a site, work in this order. Always **diagnose before changing**, and confirm which SEO plugin/host/theme is already in use before recommending tools.

### Step 1 — Diagnose (run an audit first)
- **15-minute AEO audit:** (1) Rich Results Test on homepage → check Article/Organization/FAQPage; (2) read `site.com/robots.txt` → are GPTBot/ClaudeBot/PerplexityBot allowed?; (3) PageSpeed Insights → Core Web Vitals; (4) ask ChatGPT/Perplexity a question the site should answer → cited?; (5) check `site.com/llms.txt` (404 = none).
- Classic checks: indexing allowed, sitemap present/submitted, permalinks, orphan pages, GSC/GA4 connected.

### Step 2 — Foundation
- Fast managed hosting (avoid cheap shared), **SSL/HTTPS** active, lightweight **responsive SEO-friendly theme** (Astra, OceanWP, GeneratePress).
- Security plugin (Wordfence/All-In-One Security), backups (UpdraftPlus/Duplicator), keep core+plugins+theme updated. Most vulnerabilities are plugin/theme, not core.

### Step 3 — Indexing & SEO plugin
- Install **one** SEO plugin only (duplicate plugins = duplicate meta tags): Yoast, Rank Math, AIOSEO, or SEOPress. Configure site representation, social profiles, title/meta templates.
- Settings → Reading: "Discourage search engines…" **UNCHECKED**.
- Pick **www OR non-www** consistently. Permalinks → **Post name**.
- Generate + submit XML sitemap to **Google Search Console** and **Bing Webmaster Tools**. Connect **GA4**.
- Noindex low-value pages (thank-you, legal, thin archives). Avoid orphan pages.

### Step 4 — Keyword research & topic clusters
- Evaluate volume, difficulty (KD%), and **intent** (navigational/informational/commercial/transactional). Prefer **long-tail, low-competition**.
- Tools: Google Keyword Planner, AnswerThePublic, Ubersuggest (free); Semrush (Keyword Magic, Keyword Gap → "Untapped"), Ahrefs, Frase, Surfer.
- Build **pillar + cluster** structure (pillar links to all clusters, clusters link back). Aim for **8–12 interlinked posts per cluster**. Signals topical authority; prevents cannibalization.

### Step 5 — On-page optimization
- Title **< 60 chars**, primary keyword near the start.
- Meta description **~150–160 chars** with action verb + CTA (drives CTR, not ranking).
- **One H1**; logical H1→H2→H3 (never skip levels). **For AEO, phrase headings as the questions users ask** ("What is X?" not "Overview").
- Keyword in URL slug / H1 / first paragraph — no stuffing. URLs short, lowercase, hyphens not underscores, no dates/stop-words.

### Step 6 — Content quality & E-E-A-T
- Match search intent; cite primary sources; show author bios + credentials; use first-party data, case studies, testimonials.
- Write for humans: short paragraphs, white space, lists/tables, examples, visuals.
- Freshness: visible "last updated" dates (WP Last Modified Info), **quarterly content audits**, keep `dateModified` current.
- AI assists the repetitive 80%; humans own the 20% (accuracy, original examples, voice). Never publish unedited AI drafts — Google penalizes unhelpful content, not AI-assisted content.

### Step 7 — Structured data / schema
- Most plugins auto-add **Article + Organization**. Add the AEO-priority types:
  - **FAQPage** — highest leverage (3.2× AI Overview boost); mark up *real* questions. Use Rank Math/Yoast FAQ block or manual JSON-LD.
  - **Article** — author + datePublished + dateModified (verify accurate).
  - **HowTo** — procedural content.
  - **Organization** — brand entity (name, logo, social).
  - **Speakable** — voice (beta).
- Advanced/entity: WordLift, Schema Pro. **Validate with Google Rich Results Test.**

### Step 8 — Images
- Compress (TinyPNG/ShortPixel/Imagify/Smush) to **< 200 KB**; serve **WebP/AVIF**.
- Descriptive alt text (specific, no "image of", keyword only if natural); descriptive hyphenated file names.
- Lazy load from **one** source only.

### Step 9 — Site structure & internal linking
- Hierarchical: Home → Category → Subcategory → Post; ~4–5 parent categories; optimize category pages.
- Enable breadcrumbs (Yoast/theme; WP 7.0 has a native block).
- Internal links: descriptive varied anchors, in main content, high→important pages, **no orphan pages**. Tools: AIOSEO Link Assistant, Link Whisper.

### Step 10 — Performance & Core Web Vitals (also gates AI crawling)
- Targets: **LCP < 2.5s, INP < 200ms, CLS ≤ 0.1**; total load **< 2s** (AI crawlers time out at 1–5s).
- Caching: WP Rocket / W3 Total Cache / LiteSpeed. CDN: Cloudflare. Minify: Autoptimize. DB cleanup: WP-Optimize.
- ⚠️ **One job, one tool** — never enable lazy load or minify in multiple plugins; test in PageSpeed Insights/GTmetrix.

### Step 11 — Off-page & local
- Backlinks: link-worthy content + outreach; reclaim unlinked mentions (Google Alerts/Brand24); track referring domains.
- Local: LocalBusiness schema, Google Business Profile + Bing Places, local keywords, reputable directory citations.

### Step 12 — AI-specific (AEO/GEO) layer
- **Server-rendered, clean semantic HTML** (`<article>`, `<section>`) — AI crawlers ignore heavy JS; a JS-only page reads as empty.
- **Answer blocks:** direct quotable answer in the **first 1–3 sentences** after each heading; tables for comparisons, lists for steps/options; define entities plainly; verifiable cited claims.
- **robots.txt — allow AI crawlers** (required to be cited):
  ```
  User-agent: GPTBot
  Allow: /
  User-agent: ClaudeBot
  Allow: /
  User-agent: PerplexityBot
  Allow: /
  ```
  Throttle rather than block if load is heavy; optionally block training-only bots while allowing citation bots.
- **`llms.txt`** at site root — curated Markdown index of key pages + descriptions (use a generator). Optional **`llms-full.txt`** = full content as one Markdown file.
- **Track AI traffic:** create a GA4 custom **"AI" channel group** with regex for chat.openai.com / claude.ai / perplexity.ai. Monitor citations via Ahrefs / Perplexity Pages.

### Step 13 — WordPress 7.0 readiness (release May 20, 2026)
- **Do not auto-update production on launch day**; test on staging, wait for **7.0.1 (~2 weeks)**, keep a restorable full snapshot (files + DB).
- Requirements: **PHP 7.4+** (8.3+ recommended), **MySQL 8.0+ / MariaDB 10.6+**.
- Biggest change: **DataViews replaces `WP_List_Table`** on Posts/Pages/Media → highest risk is any plugin customizing wp-admin lists (custom columns, bulk actions, page builders with admin hooks). Verify WooCommerce, ACF, Yoast, Rank Math, Elementor, Divi, WP Rocket are 7.0-compatible.
- **WP AI Client + Abilities API** ship (providers: Anthropic/OpenAI/Google under Settings → Connectors) but are **off by default** — write a provider policy (default "none") before upgrading in regulated stacks.
- Grep custom code for breaking surfaces: `manage_posts_columns`, `manage_posts_custom_column`, `bulk_actions-`, `WP_List_Table`, `groupByField`, `effect(` (→ `watch()`), `state.navigation.hasStarted/hasFinished`, `add_theme_support('html5', ['script'])`, block `apiVersion: 2` (→ 3).

## Output checklist (verify before declaring done)

**Foundation:** fast host + SSL ✓ · responsive SEO theme ✓ · security + backups + updates ✓
**Indexing:** indexing allowed ✓ · www/non-www consistent ✓ · permalinks=Post name ✓ · sitemap in GSC+Bing ✓ · GA4 ✓ · no orphans ✓
**On-page:** title <60 ✓ · meta ~150–160 ✓ · one H1, question-framed headings ✓ · keyword in URL/H1/intro ✓ · alt text + WebP/AVIF <200KB ✓
**Content:** intent match + citations ✓ · author E-E-A-T ✓ · pillar+8–12 clusters ✓ · last-updated dates + quarterly refresh ✓
**Performance:** caching+CDN+minify (one tool/job) ✓ · CWV pass ✓ · load <2s ✓
**AI (AEO/GEO):** server-rendered semantic HTML ✓ · FAQPage schema on key posts ✓ · Article+Organization validated ✓ · first-paragraph answers + tables/lists ✓ · robots.txt allows GPTBot/ClaudeBot/PerplexityBot ✓ · llms.txt at root ✓ · GA4 AI channel group ✓ · citation monitoring ✓
**WP 7.0:** PHP 7.4+ & MySQL 8.0+ ✓ · staged + waited for 7.0.1 ✓ · admin plugins DataViews-compatible ✓ · AI Connectors policy ✓

## Tool stack (start lean: one plugin + one SaaS; add only for a clear gap)

- **All-in-one SEO:** Yoast / Rank Math / AIOSEO / SEOPress
- **Keyword & content:** Semrush, Ahrefs, Frase, Surfer, AnswerThePublic
- **Schema/entities:** plugin generators, Schema Pro, WordLift
- **Internal links:** AIOSEO Link Assistant, Link Whisper
- **Performance:** WP Rocket, Cloudflare, Autoptimize, ShortPixel/Imagify, GTmetrix, PageSpeed Insights
- **Monitoring:** Google Search Console, GA4, Ahrefs, Perplexity Pages
- **AI access:** llms.txt generator, robots.txt directives

## Guardrails

- Never run two SEO plugins or two lazy-load/minify sources simultaneously.
- Diagnose with real tools (Rich Results Test, PageSpeed Insights, GSC) before recommending changes; don't assume.
- Confirm the current host/theme/plugin and PHP/MySQL versions before suggesting upgrades.
- AEO/GEO without the SEO + speed + clean-HTML foundation will not work — do the foundation first.
- Keep humans in the loop for E-E-A-T; AI tools accelerate work but don't replace expertise.
