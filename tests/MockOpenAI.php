<?php
/**
 * Scriptable OpenAI Chat Completions transport for tests.
 *
 * Mirrors MockAnthropic but on the `wpchat_openai_http_response` seam, emitting
 * OpenAI-shaped responses so the OpenAIProvider adapter can be exercised
 * end-to-end without a network call.
 *
 * @package WPChat\Tests
 */

namespace WPChat\Tests;

class MockOpenAI {

    private array $queue = [];
    private array $recorded = [];

    public function register(): self {
        \add_filter('wpchat_openai_http_response', [$this, 'handle'], 10, 2);
        return $this;
    }

    public function unregister(): void {
        \remove_filter('wpchat_openai_http_response', [$this, 'handle'], 10);
        $this->queue    = [];
        $this->recorded = [];
    }

    public function enqueue(array $body): self {
        $this->queue[] = $body;
        return $this;
    }

    public function enqueueToolCall(string $name, array $args, string $text = ''): self {
        $msg = ['role' => 'assistant', 'content' => $text !== '' ? $text : null];
        $msg['tool_calls'] = [[
            'id'       => 'call_' . uniqid(),
            'type'     => 'function',
            'function' => ['name' => $name, 'arguments' => json_encode(empty($args) ? new \stdClass() : $args)],
        ]];
        return $this->enqueue(['choices' => [['message' => $msg, 'finish_reason' => 'tool_calls']]]);
    }

    public function enqueueEndTurn(string $text): self {
        return $this->enqueue(['choices' => [[
            'message'       => ['role' => 'assistant', 'content' => $text],
            'finish_reason' => 'stop',
        ]]]);
    }

    public function handle($prev, array $request): array {
        $this->recorded[] = $request;
        $body = empty($this->queue)
            ? ['choices' => [['message' => ['role' => 'assistant', 'content' => '(mock)'], 'finish_reason' => 'stop']]]
            : array_shift($this->queue);
        return ['response' => ['code' => 200, 'message' => 'OK'], 'body' => json_encode($body), 'headers' => []];
    }

    public function recordedRequests(): array { return $this->recorded; }
    public function lastRequest(): ?array { return end($this->recorded) ?: null; }
}
