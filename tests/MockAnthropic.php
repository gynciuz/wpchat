<?php
/**
 * Scriptable Anthropic transport for tests.
 *
 * The production code in WPChat\Anthropic::run_with_tools calls
 * wp_remote_post once per turn of the tool-use loop. Each call passes
 * through the `wpchat_anthropic_http_response` filter — if a filter
 * returns a wp_remote response array, the real HTTP is skipped.
 *
 * MockAnthropic registers a filter that pops scripted responses off a
 * queue and records every outgoing request (the wpchat→anthropic
 * payload) so tests can assert what tools the assistant chose to call.
 *
 * Usage:
 *
 *   $mock = new MockAnthropic();
 *   $mock->enqueueToolUse('list_orders', ['limit' => 5])
 *        ->enqueueEndTurn('Found 5 orders.')
 *        ->register();
 *
 *   // ... call REST endpoint ...
 *
 *   $this->assertSame('list_orders', $mock->requestedToolCalls()[0]['name']);
 *
 * @package WPChat\Tests
 */

namespace WPChat\Tests;

class MockAnthropic {

    /** @var array<int, array> Queue of responses to return. */
    private array $queue = [];

    /** @var array<int, array> Every Anthropic request the plugin emitted. */
    private array $recorded = [];

    /** @var array<int, array> Responses we've actually returned. */
    private array $dispatched = [];

    /** Tool-use blocks the scripted assistant emitted, in order. */
    public function scriptedToolCalls(): array {
        $calls = [];
        foreach ($this->dispatched as $resp) {
            foreach ($resp['content'] ?? [] as $block) {
                if (($block['type'] ?? '') === 'tool_use') {
                    $calls[] = ['name' => $block['name'], 'input' => $block['input']];
                }
            }
        }
        return $calls;
    }

    /** Register the filter and reset state. */
    public function register(): self {
        \add_filter('wpchat_anthropic_http_response', [$this, 'handle'], 10, 2);
        return $this;
    }

    public function unregister(): void {
        \remove_filter('wpchat_anthropic_http_response', [$this, 'handle'], 10);
        $this->queue     = [];
        $this->recorded  = [];
        $this->dispatched = [];
    }

    /** Append a raw Anthropic API response (the JSON they'd send back). */
    public function enqueue(array $api_response): self {
        $this->queue[] = $api_response;
        return $this;
    }

    /** Convenience: assistant turn that calls one tool and stops for tool_result. */
    public function enqueueToolUse(string $name, array $input, string $text = ''): self {
        $content = [];
        if ($text !== '') {
            $content[] = ['type' => 'text', 'text' => $text];
        }
        $content[] = [
            'type'  => 'tool_use',
            'id'    => 'toolu_' . uniqid(),
            'name'  => $name,
            'input' => empty($input) ? new \stdClass() : $input,
        ];
        return $this->enqueue([
            'id'           => 'msg_' . uniqid(),
            'type'         => 'message',
            'role'         => 'assistant',
            'model'        => 'mock-claude',
            'stop_reason'  => 'tool_use',
            'content'      => $content,
            'usage'        => ['input_tokens' => 1, 'output_tokens' => 1],
        ]);
    }

    /** Convenience: assistant turn that ends the conversation with text. */
    public function enqueueEndTurn(string $text): self {
        return $this->enqueue([
            'id'           => 'msg_' . uniqid(),
            'type'         => 'message',
            'role'         => 'assistant',
            'model'        => 'mock-claude',
            'stop_reason'  => 'end_turn',
            'content'      => [['type' => 'text', 'text' => $text]],
            'usage'        => ['input_tokens' => 1, 'output_tokens' => 1],
        ]);
    }

    /** Filter handler — returns a fake wp_remote_post response from the queue. */
    public function handle($prev, array $request): array {
        $this->recorded[] = $request;
        if (empty($this->queue)) {
            // No more scripted responses — return an end_turn so the loop exits.
            $body = [
                'stop_reason' => 'end_turn',
                'content'     => [['type' => 'text', 'text' => '(mock: queue empty)']],
                'usage'       => ['input_tokens' => 0, 'output_tokens' => 0],
            ];
        } else {
            $body = array_shift($this->queue);
        }
        $this->dispatched[] = $body;

        return [
            'response' => ['code' => 200, 'message' => 'OK'],
            'body'     => json_encode($body),
            'headers'  => [],
        ];
    }

    /** Every outgoing request the plugin sent to (fake) Anthropic. */
    public function recordedRequests(): array {
        return $this->recorded;
    }

    /** Most recent request. */
    public function lastRequest(): ?array {
        return end($this->recorded) ?: null;
    }

    /** Extract every system prompt sent (one per turn). */
    public function systemPrompts(): array {
        return array_map(static fn($r) => $r['system'] ?? '', $this->recorded);
    }
}
