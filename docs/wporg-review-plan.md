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
| W2 | Prefixing | project-wide | warning, largely a false positive |

### Open from T1, not repeated in T2 but not cleared

| # | Issue | Severity |
|---|---|---|
| N1 | Plugin name and slug: repetitive, descriptive, flagged as a possible trademark | blocker |
| N2 | Readme description inconsistent with actual feature set | blocker |
| N3 | Suggestion to migrate to the WordPress 7.0 core AI Client | optional |

T2 did not repeat N1 and N2 because T2 came from the automated pre-screen, which only runs
the code scanners. The naming and description findings came from the AI review pass in T1
and remain on the volunteer's checklist.

---

## 2. The one decision needed before implementation starts

The plugin must be renamed. This is the only item in the plan that is not a
mechanical fix, and every other rename-dependent task blocks on it.

The reviewer's objection to `ChatAdmin – AI chat admin` has three parts: the name repeats
itself, it is largely descriptive, and `ChatAdmin` reads as a project-style term that does
not appear to belong to the submitter. Their own suggestion was
**Gynciuz Order Assistant for WooCommerce**, slug `gynciuz-order-assistant`.

**Recommendation: take the reviewer's suggestion verbatim.** It follows the pattern they
published as acceptable, a distinctive term at the front and the trademark last after
"for". Adopting it costs one round trip instead of two, and deviating invites a fresh
objection from the volunteer who reads it next. The name saying "Order" while the plugin
also edits content and runs SEO audits is fine. Plugins may do more than their name says.
What is not fine is the readme being vague about it, which is issue N2 below.

If a different name is preferred, it must put a coined or personal term first and the
WooCommerce reference last, and it must survive a search for lookalikes. Adding a generic
word such as Advanced or Simple will not clear the similarity objection.

Everything downstream assumes the slug `gynciuz-order-assistant` and the text domain of the
same name. Substitute throughout if the decision changes.

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
4. Move the boot object to `wp_add_inline_script( handle, 'window.GYNOA_BOOT = …', 'before' )`
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

### Phase 3 — rename

Scope, assuming `gynciuz-order-assistant`:

| Item | From | To |
|---|---|---|
| Display name | `ChatAdmin – AI chat admin` | `Gynciuz Order Assistant for WooCommerce` |
| Slug and text domain | `chatadmin` | `gynciuz-order-assistant` |
| Main file | `chat-admin.php` | `gynciuz-order-assistant.php` |
| Namespace | `ChatAdmin` | `GynciuzOrderAssistant` |
| Constants | `CHATADMIN_*` | `GYNOA_*` |
| Options | `chatadmin_*` | `gynoa_*` |
| Transient prefixes | `chatadmin_pending_`, `chatadmin_rl_` | `gynoa_pending_`, `gynoa_rl_` |
| History table | `{prefix}chatadmin_messages` | `{prefix}gynoa_messages` |
| REST namespace | `chatadmin/v1` | `gynoa/v1` |
| Front-end route | `/chatadmin` | `/gynciuz-order-assistant` |
| JS boot global | `CHATADMIN_BOOT` | `GYNOA_BOOT` |

`GYNOA_` is six characters, distinctive, and not a common word, which also closes W2. The
current `chatadmin_` prefixing was already correct and the scanner's complaint about the
common word "chat" was a false positive, but the rename makes the point moot.

Migration matters because existing installs came from GitHub releases, not wp.org. Add a
one-time upgrade routine keyed on the stored version option that renames the options,
renames the table with `ALTER TABLE … RENAME TO`, and no-ops when the new names already
exist. Keep `/chatadmin` as a redirect to the new route so bookmarks survive.

Sequencing note: the React app reads the REST namespace and the boot global, so
`app/src/main.tsx` and anything referencing `chatadmin/v1` must change in the same commit,
and `pnpm build` must run before the package is cut. `build/` is committed.

Reply to the review thread requesting the new slug reservation. Uploading before the
reservation lands is fine and the team expects a text-domain warning in the interim.

### Phase 4 — readme

**N2.** The reviewer found the readme describes a WooCommerce order assistant while the FAQ
claims content, SEO, image and admin-handoff features that appear nowhere else. Restructure
the description so every capability the plugin actually ships is stated once, in order:
orders, content creation and editing, SEO audit and metadata, traffic summary, image upload,
and the deep-link handoff for everything else. Then state plainly what it cannot do, which
is the bulk and delete operations withheld by design. Remove the Phase 1 (MVP) roadmap
framing, which reads as an unfinished plugin.

Also in the readme:

- Update the name heading and the `Tags` line, which currently carries `chat` and `ai`.
- Keep `Contributors: chatapp`.
- Re-verify the six terms and privacy URLs resolve.
- Bump `Stable tag` and add a matching `= 0.8.0 =` changelog entry.
- The changelog must stay under the 5000-character wp.org limit, which was already trimmed once.

**N3.** The suggestion to adopt the WordPress 7.0 core AI Client is phrased as
"please consider" and is not a blocker. Do not attempt it in this round. It would replace the
entire three-provider adapter layer and the test seams that every scenario test depends on.
Note in the reply that it is on the roadmap for a later release.

---

## 4. Release and resubmission

1. Bump the version in all four places: the `Version:` header, `GYNOA_VERSION`,
   `readme.txt` `Stable tag`, and the matching changelog heading. `bin/release.sh` asserts
   these agree.
2. `pnpm --dir app build`, then commit the regenerated `build/`.
3. `composer test` — unit, integration and scenario suites all green.
4. Run Plugin Check and PHPCS with WordPress-Extra. The team names both tools explicitly and
   checks whether the author ran them.
5. `bin/build-wporg.sh`. This already strips `vendor-puc/` and `includes/updater.php` and
   removes the `Update URI:` header, so the bundled-updater guideline stays satisfied.
   Update the hardcoded `WPORG_SLUG` default and the `chat-admin.php` filename inside the
   script when the rename lands.
6. Install the resulting ZIP on a clean WordPress with `WP_DEBUG` true and exercise the full
   path: onboarding, chat, an order status change, a content preview and apply, an image
   upload, and the diagnostics page. The team rejects submissions that fatal on activation.
7. Upload via "Add your plugin" and reply on the existing thread.

Keep the reply short. State that all listed issues are fixed, name the chosen slug and ask
for the reservation, and say the core AI Client migration is planned for a later release.
The team asks explicitly for brevity and does not want a change log in the reply.

---

## 5. Sequencing

Phases 1 and 2 are independent and can run in parallel. Phase 3 touches nearly every file
and should land after them to avoid rebasing the security fixes across a rename. Phase 4
depends on the name being settled.

The name decision gates Phase 3 and Phase 4. Phases 1 and 2 can start immediately.
