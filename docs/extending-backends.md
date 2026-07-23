# Extending ChatAdmin: custom content backends

ChatAdmin's core plugin is **universal** — it makes no assumptions about any
particular site. When a site stores content in a non-standard way (static HTML
blocks, page-builder data, a theme's custom "team member" records, an external
store), you teach ChatAdmin about it from a **separate site plugin** by
registering a *content backend*. ChatAdmin then detects it automatically — no
change to ChatAdmin itself.

This is the intended way to make ChatAdmin edit things like Gentleman's Empire's
static team pages: ship a small GE plugin that exposes that content as a backend.

## What "auto-detect" means

Once your backend is registered via the `chatadmin_content_backends` filter,
ChatAdmin, with zero further wiring:

1. **Advertises it to the assistant** — your `describe_kinds()` output is injected
   into the system prompt, filtered by the current user's role, so the assistant
   knows the kind exists and what fields it can change.
2. **Routes edits to it** — `ContentRouter` dispatches any `preview_content_change`
   / `apply_content_change` for your kind to your backend, through the mandatory
   two-step preview → confirm → apply flow.
3. **Gates it by capability** — the WP cap your `required_cap()` returns decides
   who may edit it (an admin can do more than an editor — power always comes from
   the user's real WordPress role, never granted by ChatAdmin).
4. **Makes it findable** — if you implement the optional `search()` method, the
   `find_text` tool locates your content alongside posts/meta/terms, so the
   assistant discovers it when the user reports wrong text without knowing where
   it lives.

## The contract

Implement `\ChatAdmin\ContentBackend`:

| Method | Required | Purpose |
|---|---|---|
| `handled_kinds(): array` | yes | The `kind` slugs you claim (e.g. `['team_member']`). |
| `describe_kinds(): array` | yes | `['<kind>' => ['description' => …, 'fields' => [...]]]` — shown to the assistant. |
| `list_items($kind, $args): array` | yes | Enumerate items so the user/assistant can pick one. |
| `preview($target, $field, $value): array` | yes | Read-only diff: `['matches' => [['location','field','old_value','new_value']]]`. |
| `apply($target, $field, $value, $confirmation): array` | yes | Write it — gate on `\ChatAdmin\ContentConfirmation::is_confirmed($confirmation)`. |
| `required_cap($kind, $target): string` | optional | WP cap needed to edit (defaults to `edit_posts`). |
| `search($query): string` → hits | optional | Return `find_text`-shaped hits so your content is discoverable. |

`search()` hit shape:

```php
[
  'where'    => 'team member “Jonas” (role)', // human location
  'kind'     => 'team_member',
  'target'   => ['kind' => 'team_member', 'id' => 'jonas'],
  'editable' => true,   // scope to what THIS user may edit
  'note'     => null,   // optional, e.g. "read-only static file"
]
```

## Register it

```php
add_filter('chatadmin_content_backends', function (array $backends) {
    $backends[] = new My_Backend();
    return $backends;
});
```

## Full working reference

See [`examples/example-content-backend.php`](examples/example-content-backend.php)
— a complete, drop-in site plugin exposing a `team_member` kind. It runs as-is
(storing to an option); replace the two storage methods (`read_all()` /
`write_all()`) with wherever your real content lives, and — if that's static
HTML — regenerate the HTML and purge any page cache inside `write_all()`.

## Tips

- Keep the core plugin universal: never hard-code a site's specifics into
  ChatAdmin; put them in the site backend.
- `describe_kinds()` text is the assistant's only knowledge of your content —
  name the fields clearly and say what a target looks like.
- Scope `list_items()`, `search()`, and `apply()` to the current user's
  capabilities; ChatAdmin also re-checks `required_cap()` on dispatch.
- For static HTML/files, do the write **and** the cache purge in `apply()` so the
  change is visible immediately.
