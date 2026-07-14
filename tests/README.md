# ChatAdmin tests

Three suites, run independently or together.

| Suite | Path | What it covers |
|---|---|---|
| Unit | `tests/Unit/` | Pure PHP, no WordPress boot. Confirmation-phrase whitelist, helper functions. |
| Integration | `tests/Integration/` | Real WP + MySQL via `WP_UnitTestCase`. DB migrations, REST routes, content-backend dispatch, History repo. |
| Scenarios | `tests/Scenarios/` | End-to-end user flows with a mocked Anthropic transport. Locks in behavioral rules (proactivity, no bulk destructive, DELETE-word, multilingual mapping). |

## Run

```sh
# One-time setup: install WP test scaffold + create DB
# Locally with the docker-compose stack already running:
bin/install-wp-tests.sh wpchat_tests wordpress wordpress 127.0.0.1:3307 latest
composer install

# Run all
composer test

# One suite
composer test:unit
composer test:integration
composer test:scenarios

# One file
vendor/bin/phpunit tests/Scenarios/ProactiveHandoffTest.php

# Single test method
vendor/bin/phpunit --filter=test_delete_order_request_triggers_get_admin_url
```

In CI everything runs on push/PR via `.github/workflows/test.yml` across PHP 8.1/8.2/8.3 × WP 6.6/latest. WooCommerce is installed before phpunit so order-tool tests can hit real `wc_get_orders`.

## How the Anthropic mock works

The production code in `ChatAdmin\Anthropic::run_with_tools` calls `wp_remote_post` once per turn of the tool-use loop. Each call passes through the `wpchat_anthropic_http_response` filter — if a filter returns a response array, the real HTTP is skipped.

`tests/MockAnthropic.php` registers that filter and pops scripted responses off a queue:

```php
$this->mockAnthropic
    ->enqueueToolUse('get_admin_url', ['resource' => 'order', 'id' => 2842])
    ->enqueueEndTurn('Opening the order in WP admin — click "Move to Trash" then refresh.');

$response = $this->postChat('Delete order 2842');

$calls = $this->mockAnthropic->scriptedToolCalls();
$this->assertSame('get_admin_url', $calls[0]['name']);
```

The real tool implementations still execute against real WP — only the LLM call is faked. That's the right fidelity: tool bugs surface, system-prompt content is asserted on the real wire format, but tests are deterministic and don't burn Anthropic credits.

## Adding a new scenario

1. Drop a file in `tests/Scenarios/` named `<Behavior>Test.php`. Extend `ChatAdmin\Tests\TestCase`.
2. Script the conversation with the mock:
   ```php
   $this->mockAnthropic
       ->enqueueToolUse('tool_name', ['arg' => 'val'])
       ->enqueueEndTurn('Final reply text.');
   ```
3. Drive the flow:
   ```php
   $response = $this->postChat('user message');
   ```
4. Assert on the response, on `scriptedToolCalls()`, on `lastRequest()['system']` (the system prompt sent to Anthropic), or on database state.

Scenario tests are the ones that lock in *behavior* (no bulk destructive ops, proactive handoff, etc.). When a behavioral rule lands in the system prompt, add a scenario test that asserts the rule's presence + verifies the assistant follows it on a representative user message.

## What still needs coverage

- WC-specific order tool tests (`list_orders`, `update_order_status`, `add_order_note`) — these require WC fixtures; safe to add once WC test factory helpers are in place.
- Frontend (React) component tests — currently not in scope; the backend has far more risk surface.
- The "telemetry opt-in" toggle (v0.4.2 backlog item) — once the telemetry endpoint lands.
- The DELETE-word confirmation pattern at the tool-implementation level — currently only enforced in the system prompt; structural enforcement comes when the first destructive tool ships.
