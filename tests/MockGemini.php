<?php
/**
 * Scriptable Gemini generateContent transport for tests.
 *
 * Mirrors MockAnthropic on the `wpchat_gemini_http_response` seam, emitting
 * Gemini-shaped responses so the GeminiProvider adapter can be exercised
 * end-to-end without a network call.
 *
 * @package WPChat\Tests
 */

namespace WPChat\Tests;

class MockGemini {

    private array $queue = [];
    private array $recorded = [];

    public function register(): self {
        \add_filter('wpchat_gemini_http_response', [$this, 'handle'], 10, 2);
        return $this;
    }

    public function unregister(): void {
        \remove_filter('wpchat_gemini_http_response', [$this, 'handle'], 10);
        $this->queue    = [];
        $this->recorded = [];
    }

    public function enqueue(array $body): self {
        $this->queue[] = $body;
        return $this;
    }

    public function enqueueToolCall(string $name, array $args, string $text = ''): self {
        $parts = [];
        if ($text !== '') {
            $parts[] = ['text' => $text];
        }
        $parts[] = ['functionCall' => ['name' => $name, 'args' => empty($args) ? new \stdClass() : $args]];
        return $this->enqueue(['candidates' => [['content' => ['role' => 'model', 'parts' => $parts]]]]);
    }

    public function enqueueEndTurn(string $text): self {
        return $this->enqueue(['candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => $text]]]]]]);
    }

    public function handle($prev, array $request): array {
        $this->recorded[] = $request;
        $body = empty($this->queue)
            ? ['candidates' => [['content' => ['parts' => [['text' => '(mock)']]]]]]
            : array_shift($this->queue);
        return ['response' => ['code' => 200, 'message' => 'OK'], 'body' => json_encode($body), 'headers' => []];
    }

    public function recordedRequests(): array { return $this->recorded; }
    public function lastRequest(): ?array { return end($this->recorded) ?: null; }
}
