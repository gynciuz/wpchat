# Server-enforced confirmation for mutations (audit finding #2)

_Spec · 2026-07-14 · status: **IMPLEMENTED** (approach B) — `PendingConfirmation` in
`class-content-backends.php`, gating in `Tools`, context in `Rest::handle_chat`,
turn index via `History::user_message_count`. Tests: `PendingConfirmationTest` +
`ConfirmationTurnGuardTest`. Approach A (model-echoed token) kept below as the
rejected alternative and its rationale._

## Problem

Every write gate (`apply_content_change`, `publish_content`, order mutations,
SEO settings) proceeds when the `confirmation` string is a whitelisted
affirmative (`ContentConfirmation::is_confirmed`). **The model supplies that
string.** Nothing server-side proves (a) a `preview_*` / `needs_confirmation`
actually ran first, or (b) a human — not injected content steering the model —
consented. A prompt-injection payload in tool-returned content (an order note,
page HTML) can make the model call `apply_*` with `confirmation: "yes"` and skip
the preview.

Capability gating (audited: holds) caps the blast radius to the acting user's
own privileges, and finding #1 (`_confirmed` bypass) is already fixed. This spec
closes the residual "model asserts consent" gap.

## Two approaches

### A. Single-use preview→apply token (model-echoed)
`preview_*` / the first `needs_confirmation` mints a random token stored in a
short-TTL transient bound to the target; `apply_*` must pass it back and it is
consumed on use.

- **Pros:** small, local change; directly enforces "preview preceded apply";
  context-bound (a token for target X can't apply target Y).
- **Cons:** the model must **echo a runtime value** on the confirm turn. Two
  consequences: (1) a new **fail-closed** path — if the model omits the token,
  a legitimate apply is refused (it self-heals by re-previewing, but it's a new
  failure mode on the core flow); (2) it does **not** by itself prove a human
  consented — the model can mint (preview) and echo (apply) within the *same*
  response unless combined with a turn boundary.
- **Testing friction:** `MockAnthropic` scripts fixed tool_use inputs. The token
  is minted at runtime, so a scripted `apply` can't know it. Verifying this
  needs a harness change (let the mock/test read the preview tool_result and
  thread its `confirm_token` into the next scripted call).

### B. Conversation-scoped pending-confirmation (server state, recommended)
On a `needs_confirmation` / `preview_*` result, store a server record
`transient chatadmin_pending_{conversation_id}` = `{target, minted_at_turn}`. The
mutating call is allowed only if a matching pending record exists **and was
created in an earlier user turn** than the current request; then it's consumed.

- **Pros:** binds consent to a **real, later user message** (defeats same-turn
  injection — the strongest property, which A lacks); the model echoes nothing,
  so no new fail-closed-on-missing-token brittleness.
- **Cons:** requires threading `conversation_id` + a per-request turn index from
  `Rest::handle_chat` into the tool implementations (today tools receive only
  `$args`). That's a `Tools` dispatch-context refactor.
- **Testing:** naturally exercised by the existing multi-`postChat` +
  `conversation_id` scenario pattern — no runtime-token threading needed.

**Recommendation: B** (optionally + A as defense-in-depth). B is the one that
actually enforces "a human consented in a later turn," which is the substance of
the finding; A only enforces "a preview happened."

## Design (approach B)

### Tool dispatch context
Give tool implementations access to the request context. Minimal change:
`BaseLLMProvider::run_with_tools` already knows the conversation; pass a
`context` array (`['conversation_id' => ..., 'turn' => <user-message count>]`)
into the tool callables — either as a second arg (widen the `implementations()`
callable signature) or via a request-scoped static `Tools::$context` set by
`Rest::handle_chat` before the loop and cleared after. The static-context
approach touches the fewest signatures.

### Pending store
```
key   = 'chatadmin_pending_' . $conversation_id
value = ['target' => $target_key, 'turn' => $turn, 'ts' => time()]   // TTL 900s
```
`$target_key`: `content:md5(json([$kind,$target,$field]))`,
`order:{id}:status|note|{action}`, `publish:{post_id}`, `seo:{field}`.

### Gate helper
```php
// returns null to proceed, or a needs-confirmation payload to return
private static function confirm_gate(array $args, string $target_key, array $ctx): ?array {
    if (!empty($args['_confirmed'])) return null;                    // direct-action click
    $phrase_ok = ContentConfirmation::is_confirmed((string)($args['confirmation'] ?? ''));
    $pending   = get_transient('chatadmin_pending_' . $ctx['conversation_id']);
    $matches   = is_array($pending)
        && $pending['target'] === $target_key
        && $pending['turn']   <  $ctx['turn'];                       // minted in an EARLIER turn
    if ($phrase_ok && $matches) {
        delete_transient('chatadmin_pending_' . $ctx['conversation_id']);
        return null;                                                 // consent proven → proceed
    }
    set_transient('chatadmin_pending_' . $ctx['conversation_id'],
        ['target' => $target_key, 'turn' => $ctx['turn'], 'ts' => time()], 900);
    return ['needs_confirmation' => true];                           // caller merges details
}
```

### Wiring
- `preview_content_change`: record pending for its `content:` target (no phrase
  needed — preview is the mint point).
- `apply_content_change`, `update_order_status`, customer-visible
  `add_order_note`, `trigger_order_action`, `publish_content`, SEO
  `apply_content_change` on `seo_setting`: replace the inline `needs_confirmation`
  check with `confirm_gate($args, $target_key, $ctx)`.
- System prompt: unchanged in spirit — "call once → relay needs_confirmation →
  the user confirms in a new turn → call again with their phrase." No token to
  thread.

### Tests (scenario)
1. Inject-and-apply in one turn → refused (no pending from an earlier turn).
2. Normal two-turn confirm (`postChat` trigger, then `postChat("yes")` with the
   same `conversation_id`) → applies.
3. Confirm for target X can't apply target Y (target_key mismatch).
4. Direct-action REST route (`_confirmed`) still applies with no pending record.
5. Expired/absent pending → refused.

## Open questions
1. **Context threading:** request-scoped `Tools::$context` (fewest changes) vs
   widening the tool-callable signature (cleaner, larger diff)?
2. **Turn index source:** the History user-message count for the conversation,
   or a monotonic counter stamped by `Rest::handle_chat`?
3. **Scope now:** content edits + order mutations only, or also
   `publish_content` and `seo_setting` in the same pass?
