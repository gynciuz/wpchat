# WordPress.org review — remediation plan for v0.8.0

Source: two review emails on thread *"Review in Progress: ChatAdmin – AI chat admin"*.

| Review | Date | Package reviewed | Review ID |
|---|---|---|---|
| T1 (first pass) | 14 Jul 2026 | initial submission | `AUTO chatadmin/chatapp/14Jul26/T2` |
| T2 (latest) | 11 Sep 2026 | `chatadmin-v0.7.14-wporg.zip` | `AUTO chatadmin/chatapp/14Jul26/T2 11Sep26/4.2.1` |

T2 lists four hard blockers plus two "check everything" warnings. T1 raised six further
items, of which three were fixed in 0.7.14 and three are still open. Nothing is approved
until every item below is closed, because the team re-reviews the whole plugin each round
and explicitly warns that partial fixes get the submission rejected.

**Decision taken: the plugin is not being renamed.** The name `ChatAdmin` and the slug
`chatadmin` stay. Section 2 covers how that is defended to the review team; the rest of
the plan is written on that basis.

---

## 1. Status of every issue raised

### Already fixed in 0.7.14 — no further work

| Issue | Where it was fixed | Evidence |
|---|---|---|
| Telemetry not opt-in (T1) | Settings → Privacy & diagnostics | absent setting now reads as disabled |
| External services undocumented (T1) | `readme.txt:29-62` | all three AI providers named with endpoint, data sent, terms + privacy links |
| Contributor not the plugin owner (T1) | `readme.txt:2` | `Contributors: chatapp` |

Do not touch these. The team said it will re-check the terms and privacy links, so verify
the six URLs still resolve before packaging.

### Open blockers from T2

| # | Issue | Location | Severity |
|---|---|---|---|
| B1 | Arbitrary HTML/JS storable through chat content tools | `class-tools.php:1337`, `class-content-backends.php:513` | blocker |
| B2 | Core admin file loaded without using it | `class-upload.php:88` | blocker |
| B3 | Upload route does not require the media capability | `class-upload.php:42` | blocker |
| B4 | HEREDOC syntax | `class-frontend.php:117`, `class-rest.php:422`, `class-rest.php:528` | blocker |
| W1 | Inline `script`/`style`/`link` instead of enqueue | `class-frontend.php`, `class-admin.php` | warning, will be checked manually |
| W2 | Prefixing | project-wide | warning, false positive — see below |

### Open from T1, not repeated in T2 but not cleared

| # | Issue | Handling |
|---|---|---|
| N1 | Plugin name and slug flagged as repetitive, descriptive, possibly a trademark | answered with evidence, not a rename |
| N2 | Readme description inconsistent with actual feature set | rewrite the readme |
| N3 | Suggestion to adopt the WordPress 7.0 core AI Client | deferred, acknowledged in the reply |

T2 did not repeat N1 and N2 because T2 came from the automated pre-screen, which only runs
the code scanners. The naming and description findings came from the AI review pass in T1
and remain on the volunteer's checklist.

---

## 2. Keeping the name: the reply to the review team (N1)

The review objected on three grounds: the display name repeats itself, it is largely
descriptive, and `ChatAdmin` was flagged by an AI as a *potential* trademark that may not
belong to the submitter. Their suggested replacement was
`Gynciuz Order Assistant for WooCommerce`.

The name is being kept. That is a legitimate response, not a refusal to engage: the review
email states plainly that false positives are possible, and invites a clear, concise reply
with a specific example when the author disagrees. What it does not tolerate is silence.
So the reply must present evidence rather than assert a preference.

### Evidence to put in the reply

- **No conflicting plugin exists.** No plugin in the directory is named `ChatAdmin` or uses
  the slug `chatadmin`. The nearest neighbour is *Admin Chat Management*
  (`wordpress.org/plugins/admin-chat-box/`), which shares neither name nor slug and inverts
  the word order. Re-run this check immediately before replying so the claim is current.
- **No trademark found.** A search for a registered `ChatAdmin` trademark returns nothing.
  Chat-adjacent registrations exist (`CHATTER` by Salesforce, and a family of `CHAT*` marks
  by OnCom), but none is `ChatAdmin` and none is a lookalike in the relevant class.
- **The term is the author's own coinage**, not a borrowed project name. It is consistent
  across the WordPress.org account `chatapp`, the author field, and the plugin URI.
- **The flag was explicitly probabilistic.** The email says the AI "detected ✨ ChatAdmin as
  potential trademark(s)" and that a human may reach a different conclusion. Asking for that
  human judgement, with the above evidence attached, is the process working as intended.

### The one concession worth making

The *repetition* objection is separate from the trademark one and is much harder to defend.
`ChatAdmin – AI chat admin` does say the same thing twice. Fixing it costs nothing and does
not touch the slug, the brand, the text domain, the prefixes, the options, the database
table, or the REST namespace — it only replaces the descriptive half after the dash.

Recommended display name: **`ChatAdmin for WooCommerce`**. It keeps the coined term at the
front, which is the position the team's own guidance says a distinguishing term should
occupy, and puts the WooCommerce reference last after "for", which is the pattern they
publish as acceptable for a trademark the author does not own. It also removes the
tautology in one edit.

Making this change materially strengthens the reply, because it shows the objection was
read and partly acted on rather than waved away. Declining it is supportable, but then the
reply has to defend the repetition too, on weaker ground.

### The risk, stated plainly

A volunteer may still insist on a rename. If that happens the fallback is the full rename
already scoped out, and it costs one extra review round. Holding the name is the right call
if the brand matters; it is not a free choice, and the reply is what determines how it goes.

### What this decision removes from the work

Everything identity-related stays as it is: the slug and text domain `chatadmin`, the main
file `chat-admin.php`, the namespace `ChatAdmin`, the `CHATADMIN_*` constants, every
`chatadmin_*` option, the `chatadmin_pending_` and `chatadmin_rl_` transient prefixes, the
`{prefix}chatadmin_messages` table, the `chatadmin/v1` REST namespace, the `/chatadmin`
route, and the `CHATADMIN_BOOT` browser global.

That also disposes of **W2**. The scanner's complaint was that the plugin uses "the common
word chat as a prefix", but nothing is actually prefixed `chat_`. Every option, constant,
transient and class sits behind `chatadmin_` / `CHATADMIN_` / `ChatAdmin` — nine characters,
distinctive, and well past the four-character minimum. The finding is a substring match on
`chat` inside `chatadmin`, not a real collision risk. Say so in the reply, in one sentence,
with one example. No code change.

With no rename and no prefix churn, there is no options migration, no table rename, and no
forced rebuild of the React bundle to chase a changed REST namespace. The remaining work is
the four blockers, the enqueue rework, and the readme.

---

## 3. Work plan

### Phase 1 — security blockers

**B1. Stop storing arbitrary HTML from chat.**

`Tools::build_post_content` keeps input verbatim whenever it contains a `<` character, and
`WPContentBackend::apply` writes the `content` field straight into `wp_update_post`. For an
administrator, who holds `unfiltered_html`, WordPress performs no filtering on either path,
so a `script` tag reaching either function is stored and later executed on the front end.
Because the content originates from a language model acting on untrusted chat text, this is
exactly the arbitrary-script-insertion pattern the guidelines forbid.

Fix: run every stored content value through `wp_kses_post()` regardless of the current
user's capabilities.

- `class-tools.php:1337` — apply `wp_kses_post()` to the HTML branch of
  `build_post_content`. The block-comment markers the function emits survive `wp_kses_post`
  because they are HTML comments.
- `class-content-backends.php:513` — apply `wp_kses_post()` when the target field is
  `content`, and `sanitize_text_field()` for `title` and `excerpt`.
- Add a scenario test asserting that a `script` tag submitted through `create_content`
  and through `apply_content_change` is absent from the stored `post_content`.
- Note the guarantee in the readme so the volunteer sees it without reading the diff.

**B2. Drop the unused core include.**

`class-upload.php:88` loads `wp-admin/includes/media.php` and then never calls anything from
it. The neighbouring `file.php` and `image.php` includes are genuinely used by
`wp_handle_upload` and `wp_generate_attachment_metadata`, so they stay. Delete the
`media.php` line only, then confirm the upload path still works end to end.

**B3. Require the media capability on the upload route.**

`Upload::check_permission` currently returns true for `manage_woocommerce` or
`edit_shop_orders`. The handler writes to disk and creates attachments, so it must also
require `upload_files`:

```php
public function check_permission(): bool {
    if (!current_user_can('upload_files')) {
        return false;
    }
    return current_user_can('manage_woocommerce') || current_user_can('edit_shop_orders');
}
```

Add a test for a user who can manage orders but cannot upload files, asserting a 403.

### Phase 2 — heredoc and enqueue

B4 and W1 have the same root cause and should be fixed together. Both come from
`Frontend::render`, which builds a complete HTML document by hand on `template_redirect`
and echoes it as a heredoc containing a `link` tag, a `style` block and two `script` tags.

**Recommended approach: render the route through the normal WordPress pipeline.**

Keep the `/chatadmin` route, but stop hand-rolling the document.

1. Register the virtual route with a rewrite rule and a query var instead of matching
   `REQUEST_URI` in `template_redirect`. This also removes the `is_404` and `status_header(200)`
   workaround at `class-frontend.php:55-59`, which exists only because the current approach
   fights the main query.
2. Serve a minimal plugin template through `template_include`, with `wp_head()` and
   `wp_footer()`.
3. Move the built CSS to `wp_enqueue_style` and the module bundle to `wp_enqueue_script`
   on `wp_enqueue_scripts`, gated to that query var. Set the `type="module"` attribute via
   the `script_loader_tag` filter, or pass it through the `$args` array supported since
   WordPress 6.3.
4. Move the boot object to `wp_add_inline_script( handle, 'window.CHATADMIN_BOOT = …', 'before' )`
   and the dark-surface CSS to `wp_add_inline_style`.
5. Dequeue the active theme's stylesheets on that route. The app is an always-dark full-screen
   surface and theme CSS will fight it. This replaces what the bare document gave for free.

The admin page at `class-admin.php:110-140` already enqueues correctly. Three inline blocks
there still need moving:

- `class-admin.php:51` — the new-tab tagging script, to `wp_add_inline_script` on an admin handle.
- `class-admin.php:172` — the `#chatadmin-shell` style block, to `wp_add_inline_style`.
- `class-admin.php:283` — the diagnostics page script, to a small registered file plus
  `wp_localize_script` for the REST URL, nonce and the three translated status strings.

That leaves the two prompt heredocs in `class-rest.php` at lines 422 and 528. These are long
multi-line strings with interpolated values and no output, so they are not a security risk,
but the guideline is absolute and arguing it costs a round trip. Move both prompt bodies to
plain PHP files under `includes/prompts/` and load them with a small helper that reads the
file and does `strtr()` on the placeholders. Concatenation across several hundred lines would
be unreadable; a template file keeps the prompt legible and diffable, which matters because
the system prompt is where most product behaviour lives.

After this phase, `grep -rn '<<<' includes/` and `grep -rn '<script\|<style\|<link rel' includes/`
must both come back empty.

### Phase 3 — readme (N2)

The reviewer found the readme describes a WooCommerce order assistant while the FAQ claims
content, SEO, image and admin-handoff features that appear nowhere else. Restructure the
description so every capability the plugin actually ships is stated once, in order: orders,
content creation and editing, SEO audit and metadata, traffic summary, image upload, and the
deep-link handoff for everything else. Then state plainly what it cannot do, which is the
bulk and delete operations withheld by design. Remove the Phase 1 (MVP) roadmap framing,
which reads as an unfinished plugin.

Also in the readme:

- Update the name heading if the display name is shortened per section 2. The `Contributors`,
  `Stable tag` and slug lines are unaffected.
- Re-verify the six terms and privacy URLs resolve.
- Bump `Stable tag` and add a matching `= 0.8.0 =` changelog entry.
- The changelog must stay under the 5000-character wp.org limit, which was already trimmed once.

**N3.** The suggestion to adopt the WordPress 7.0 core AI Client is phrased as
"please consider" and is not a blocker. Do not attempt it in this round. It would replace the
entire three-provider adapter layer and the test seams that every scenario test depends on.
Note in the reply that it is on the roadmap for a later release.

---

## 4. Release and resubmission

1. Bump the version in all four places: the `Version:` header, `CHATADMIN_VERSION`,
   `readme.txt` `Stable tag`, and the matching changelog heading. `bin/release.sh` asserts
   these agree.
2. `pnpm --dir app build`, then commit the regenerated `build/`.
3. `composer test` — unit, integration and scenario suites all green.
4. Run Plugin Check and PHPCS with WordPress-Extra. The team names both tools explicitly and
   checks whether the author ran them.
5. `bin/build-wporg.sh`. This already strips `vendor-puc/` and `includes/updater.php` and
   removes the `Update URI:` header, so the bundled-updater guideline stays satisfied. With
   no rename, its `WPORG_SLUG` default and the `chat-admin.php` filename inside it need no
   changes.
6. Install the resulting ZIP on a clean WordPress with `WP_DEBUG` true and exercise the full
   path: onboarding, chat, an order status change, a content preview and apply, an image
   upload, and the diagnostics page. The team rejects submissions that fatal on activation.
7. Upload via "Add your plugin" and reply on the existing thread.

### The reply itself

Keep it short. The team asks explicitly for brevity and does not want a change log. Four
points, in this order:

1. All code issues are fixed and the plugin was tested on a clean install with `WP_DEBUG`.
2. On the name: state the evidence from section 2 in two or three sentences — no plugin in
   the directory holds the name or slug, no trademark registration exists, the term is the
   author's own. Say the display name was shortened if it was. Ask for a human look rather
   than asserting the matter closed.
3. On prefixing: one sentence noting that everything is prefixed `chatadmin_`, with one
   example, and that the flag appears to be a substring match on `chat`.
4. The core AI Client migration is planned for a later release.

---

## 5. Sequencing

Phases 1 and 2 are independent and can run in parallel, and neither depends on anything in
section 2. Phase 3 depends only on whether the display name is shortened, which is a
one-line decision.

There is no longer a decision gating the start of work. The whole plan can begin now.
