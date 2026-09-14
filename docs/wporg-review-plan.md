# WordPress.org review — remediation plan for v0.8.0

Source: two review emails on thread *"Review in Progress: ChatAdmin – AI chat admin"*.

| Review | Date | Package reviewed | Origin | Review ID |
|---|---|---|---|---|
| T1 | 14 Jul 2026 | initial submission | AI-assisted review pass | `AUTO chatadmin/chatapp/14Jul26/T2` |
| T2 | 11 Sep 2026 | `chatadmin-v0.7.14-wporg.zip` | automated code scanners only | `AUTO chatadmin/chatapp/14Jul26/T2 11Sep26/4.2.1` |

T2 is the one that gates the next step. It lists four hard blockers plus two "check
everything" warnings, all from code scanners, and says outright that no human has looked at
it yet. Clear those and a volunteer picks it up.

T1 never arrived as its own email. The author told the team so on 9 September. Its text
survives only as a quoted block at the bottom of the T2 thread, and T2 does not restate any
of it. Two of its points, the plugin name and the readme wording, are therefore in an odd
state: on the record, but never pressed, and not part of what is blocking the submission
right now. Section 2 says how to handle that.

**Decision taken: the plugin is not being renamed.** `ChatAdmin` and the slug `chatadmin`
stay.

---

## 1. Status of every issue raised

### Already fixed in 0.7.14 — no further work

| Issue | Raised in | Where it was fixed | Evidence |
|---|---|---|---|
| Telemetry not opt-in | T1 | Settings → Privacy & diagnostics | absent setting now reads as disabled |
| External services undocumented | T1 | `readme.txt:29-62` | all three AI providers named with endpoint, data sent, terms + privacy links |
| Contributor not the plugin owner | T1 | `readme.txt:2` | `Contributors: chatapp` |

Do not touch these. T1 said the team will re-check the terms and privacy links, so verify
the six URLs still resolve before packaging.

### Blocking now (T2)

| # | Issue | Location | Severity |
|---|---|---|---|
| B1 | Arbitrary HTML/JS storable through chat content tools | `class-tools.php:1337`, `class-content-backends.php:513` | blocker |
| B2 | Core admin file loaded without using it | `class-upload.php:88` | blocker |
| B3 | Upload route does not require the media capability | `class-upload.php:42` | blocker |
| B4 | HEREDOC syntax | `class-frontend.php:117`, `class-rest.php:422`, `class-rest.php:528` | blocker |
| W1 | Inline `script`/`style`/`link` instead of enqueue | `class-frontend.php`, `class-admin.php` | warning, will be checked manually |
| W2 | Prefixing | project-wide | warning, false positive — see section 2 |

### On the record from T1, not restated in T2

| # | Issue | Handling |
|---|---|---|
| N1 | Plugin name flagged as repetitive, descriptive, possibly a trademark | not a rename; see section 2 |
| N2 | Readme description inconsistent with the feature set | rewrite the readme; cheap, and the volunteer will read it |
| N3 | "Please consider" the WordPress 7.0 core AI Client | deferred; not answered in the reply |

---

## 2. The name (N1) and what the reply says about it

### Where the objection actually stands

The only place the name is questioned is the quoted T1 text. Counting terms across the two
halves of the thread:

| Term | T2 (11 Sep) | T1 (14 Jul, quoted) |
|---|---|---|
| trademark | 0 | 23 |
| display name | 0 | 7 |
| naming | 0 | 5 |

T1's objection had three parts: the display name is repetitive and largely descriptive,
`ChatAdmin` is "very close to existing plugin/project naming already in use", and it
"begins with a distinctive project-style term that does not appear to belong to the
submitter". Every sentence of it is marked as AI output, and T1 itself says a human "may
spot issues with the new suggested name".

### What checking the claims found

- **No conflicting plugin.** Nothing in the directory is named `ChatAdmin` or uses the slug
  `chatadmin`. The nearest is *Admin Chat Management* (`admin-chat-box`), a different name
  and slug with the words reversed. Re-run this search the day the reply goes out.
- **No trademark.** A search for a registered `ChatAdmin` mark returns nothing. Chat-adjacent
  marks exist (`CHATTER`, Salesforce; a `CHAT*` family owned by OnCom) and none is a lookalike.
- **The term is the author's coinage**, consistent across the `chatapp` account, the author
  field and the plugin URI.

Two of the three objections do not survive that. The third, repetition, is real:
`ChatAdmin – AI chat admin` says the same thing twice, and the suffix is what lets a reader
file the whole string as a generic description rather than a name. That undercuts the
distinctiveness claim the other two points depend on.

### How the reply handles it

Do not open with a defence. Nobody has pressed the point since July, and a paragraph of
argument invites the discussion it is trying to avoid. But do not leave it unmentioned
either: T1's own text says failing to address a raised issue gets the submission rejected,
and the volunteer will read T1.

The recommended middle: **shorten the display name to `ChatAdmin for WooCommerce`** and
give the name one sentence in the reply. Shortening touches only the header and readme
title. The slug, text domain, brand, namespace, constants, options, table, REST namespace
and route are untouched. It removes the repetition, puts the coined term first and the
WooCommerce reference last after "for", which is the pattern T1 publishes as acceptable,
and it converts "ignored" into "addressed" for the cost of one line.

If the display name is to stay exactly as it is, the reply instead carries two sentences
of evidence (no plugin or mark holds the name, the term is the author's own) and asks for a
human look. That is defensible but it opens the argument.

### The risk, stated plainly

A volunteer may still ask for a rename. If so, the full rename is scoped in git history
(commit `bf18f67`) and costs one extra round. Holding the name is right if the brand
matters; it is not free.

### W2, prefixing

The scanner's note about "the common word chat as a prefix" is a substring match. Nothing
is prefixed `chat_`. Every option, constant, transient and class sits behind `chatadmin_`,
`CHATADMIN_` or the `ChatAdmin` namespace, nine characters and past the four-character
floor. No code change. One clause in the reply.

---

## 3. Work plan

### Phase 1 — security blockers

**B1. Stop storing arbitrary HTML from chat.**

`Tools::build_post_content` keeps input verbatim whenever it contains a `<`, and
`WPContentBackend::apply` writes `content` straight into `wp_update_post`. For an
administrator, who holds `unfiltered_html`, WordPress filters neither path, so a `script`
tag reaching either function is stored and later executed on the front end. Because the
content comes from a language model acting on untrusted chat text, this is the
arbitrary-script-insertion pattern the guidelines forbid.

Fix: filter every stored value regardless of the current user's capabilities.

- `class-tools.php:1337` — `wp_kses_post()` on the HTML branch of `build_post_content`.
  Block-comment markers survive: `wp_kses` preserves HTML comments, and core relies on
  that for non-privileged editors saving block content.
- `class-content-backends.php:513` — `wp_kses_post()` for `content`, `sanitize_text_field()`
  for `title` and `excerpt`.
- The same file's `wp_post_meta` and `wp_term` branches write raw values too. The scanner
  did not name them, but the volunteer reads the whole file. `sanitize_text_field()` on
  meta values; `wp_kses_post()` on a term description; term names are sanitised by core.
- Scenario test: a `script` tag submitted through `create_content` and through
  `apply_content_change` is absent from the stored `post_content`.
- One sentence in the readme stating that chat-authored content is filtered.

**B2. Drop the unused core include.**

`class-upload.php:88` loads `wp-admin/includes/media.php` and never uses it. `file.php`
(`wp_handle_upload`) and `image.php` (`wp_generate_attachment_metadata`) stay. Delete the
one line, then confirm upload still works end to end.

**B3. Require the media capability on the upload route.**

```php
public function check_permission(): bool {
    if (!current_user_can('upload_files')) {
        return false;
    }
    return current_user_can('manage_woocommerce') || current_user_can('edit_shop_orders');
}
```

Test: a user who can manage orders but lacks `upload_files` gets a 403.

### Phase 2 — heredoc and enqueue

B4 and W1 share one root cause. `Frontend::render` builds a whole HTML document by hand
on `template_redirect` and echoes it as a heredoc holding a `link` tag, a `style` block
and two `script` tags.

**A fact that simplifies this: the bundle does not need `type="module"`.** The built
entry (`build/assets/main-*.js`) contains no `import` or `export` statement and no
`import.meta`. It runs as a classic script, which is exactly how the admin page already
loads it with plain `wp_enqueue_script`. The `type="module"` in the front-end heredoc is
vestigial. So there is no module-loader work here and the admin page's existing enqueue
code is the template.

**Approach: render the route through the WordPress pipeline, reusing the admin enqueue.**

1. Register the route with a rewrite rule and a query var instead of matching
   `REQUEST_URI` on `template_redirect`. That also removes the `is_404` reset and
   `status_header(200)` at `class-frontend.php:55-59`, which exist only because the current
   approach fights the main query.
2. Factor `Admin::enqueue_assets` into a shared method that reads the manifest and
   enqueues the CSS handles and the `chatadmin-app` script, and call it from both the admin
   hook and a `wp_enqueue_scripts` hook gated on the query var. The boot object moves to
   `wp_add_inline_script('chatadmin-app', …, 'before')`, as the admin page already does.
   The dark-surface CSS moves to `wp_add_inline_style` on the first CSS handle.
3. On that route only, at a late priority on `wp_enqueue_scripts`, reset the queues to the
   plugin's own handles: `wp_styles()->queue` and `wp_scripts()->queue`. Dequeuing "the
   theme" is not enough; every active plugin enqueues on the front end, and the app is a
   full-screen dark surface that none of it should touch. Also `show_admin_bar(false)`.
4. Serve a minimal template through `template_include`. Prefer `wp_print_styles()`,
   `wp_print_head_scripts()` and `wp_print_footer_scripts()` in that template over full
   `wp_head()` / `wp_footer()`: the enqueue API is still what emits the assets, which is what
   the guideline asks for, but third-party `wp_head` output (analytics snippets, meta
   injections) stays out of the app document. The template contains no literal `script`,
   `style` or `link` element.
5. Delete the `phpcs:disable` line at `class-frontend.php:76` and its matching `enable`.
   A suppression naming the exact rules the reviewer cited is worse than the finding.

The admin page still has three inline blocks to move:

- `class-admin.php:51` — the new-tab tagging script, to `wp_add_inline_script` on an
  admin handle.
- `class-admin.php:172` — the `#chatadmin-shell` style block, to `wp_add_inline_style`.
- `class-admin.php:283` — the diagnostics page script, to a small registered file plus
  `wp_localize_script` for the REST URL, nonce and the three translated status strings.
  Remove the `phpcs:disable` around it.

The two prompt heredocs in `class-rest.php` (lines 422 and 528) are not output and not a
security risk, but the rule is absolute. Move each prompt body to a plain text file under
`includes/prompts/` and load it with `file_get_contents()` + `strtr()` on named
placeholders. Text files rather than PHP: nothing for a PHP scanner to see, and the prompt
stays legible and diffable, which matters because the system prompt is where most product
behaviour lives.

After this phase, these must both come back empty:

```
grep -rn '<<<' includes/
grep -rn '<script\|<style\|<link rel' includes/
```

### Phase 3 — readme (N2)

T1 found the readme describes an order assistant while the FAQ claims content, SEO, image
and admin-handoff features documented nowhere else. Restate every shipped capability once,
in order: orders, content creation and editing, SEO audit and metadata, traffic summary,
image upload, and the deep-link handoff for everything else. Then say plainly what it will
not do: bulk and delete operations, withheld by design. Drop the "Phase 1 (MVP)" framing,
which reads as unfinished.

Also:

- Title line follows the display-name decision in section 2. `Contributors`, `Stable tag`
  and slug are unaffected.
- Re-verify the six terms and privacy URLs resolve.
- Bump `Stable tag`; add a matching `= 0.8.0 =` changelog entry; stay under the
  5000-character changelog limit that was already trimmed once.

### A watch item, not a task

`Seo::maybe_serve_llms_txt` (`class-seo.php:94`) echoes an option value raw under a
`phpcs:ignore`, on a `REQUEST_URI`-matched virtual route. It is `text/plain`,
admin-authored, and the comment says why escaping would corrupt it. Neither review flagged
it. Leave it, but expect the volunteer to ask; the justification is already in the code.

---

## 4. Release and resubmission

1. Bump the version in all four places: `Version:` header, `CHATADMIN_VERSION`,
   `readme.txt` `Stable tag`, and the matching changelog heading. `bin/release.sh` asserts
   they agree.
2. `pnpm --dir app build`, then commit the regenerated `build/`.
3. `composer test` — unit, integration and scenario suites green.
4. Plugin Check and PHPCS with the WordPress ruleset. T2 names Plugin Check explicitly.
5. `bin/build-wporg.sh`. It already strips `vendor-puc/` and `includes/updater.php` and
   removes the `Update URI:` header. No changes needed with the slug unchanged.
6. Install the ZIP on a clean WordPress with `WP_DEBUG` on and exercise: onboarding, chat,
   an order status change, a content preview and apply, an image upload, the diagnostics
   page, and the `/chatadmin` route on both a block theme and a classic theme.
7. Upload via "Add your plugin" and reply on the thread.

### The reply

Short. The team asks for brevity and says not to list changes. Three points:

1. All issues from the 11 September scan are fixed and the plugin was tested on a clean
   install with `WP_DEBUG` on.
2. One sentence on the name. If shortened: the display name is now `ChatAdmin for
   WooCommerce`; the slug is unchanged, no other plugin or mark holds the name. If not
   shortened: two sentences of evidence and a request for a human look.
3. One clause confirming everything is prefixed `chatadmin_`, with one example.

Nothing on the core AI Client. T1 phrased it as "please consider" under "other details",
and T2 does not mention it. Answering a suggestion nobody repeated only lengthens the reply.

---

## 5. Sequencing

Phases 1 and 2 are independent and can run in parallel. Phase 3 waits only on the
one-line display-name decision. Nothing gates the start of work.
