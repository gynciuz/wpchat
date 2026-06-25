<?php
/**
 * Anthropic provider — the canonical adapter.
 *
 * The internal format IS Anthropic content blocks, so this adapter is
 * near-identity: build_request/parse_response barely transform. It deliberately
 * emits the exact same wire request as before so the `wpchat_anthropic_http_response`
 * test seam (tests/MockAnthropic.php) and every scenario test stay green.
 *
 * `Anthropic` (the old static class) is kept as a thin back-compat facade.
 *
 * @package WPChat
 */

namespace WPChat;

if (!defined('ABSPATH')) {
    exit;
}

class AnthropicProvider extends BaseLLMProvider {

    const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    const VERSION  = '2023-06-01';

    public function id(): string { return 'anthropic'; }
    public function label(): string { return 'Anthropic'; }
    public function default_model(): string { return 'claude-sonnet-4-6'; }

    public function models(): array {
        return [
            ['id' => 'claude-sonnet-4-6', 'label' => 'Sonnet 4.6 (recommended)'],
            ['id' => 'claude-opus-4-8',   'label' => 'Opus 4.8 (highest quality, slowest)'],
            ['id' => 'claude-haiku-4-5',  'label' => 'Haiku 4.5 (fastest, cheapest)'],
        ];
    }

    public function key_help(): array {
        return [
            'url'         => 'https://console.anthropic.com/settings/keys',
            'placeholder' => 'sk-ant-...',
            'regex'       => '^sk-[a-z0-9_\\-]+$',
        ];
    }

    public function matches_key(string $key): bool {
        return (bool) preg_match('/^sk-ant-/i', trim($key));
    }

    public function validate_key(string $key): array {
        return $this->check_key([
            'model'      => 'claude-haiku-4-5',
            'max_tokens' => 1,
            'messages'   => [['role' => 'user', 'content' => 'hi']],
        ], $key);
    }

    protected function endpoint(string $model): string { return self::ENDPOINT; }

    protected function headers(string $key): array {
        return [
            'x-api-key'         => $key,
            'anthropic-version' => self::VERSION,
            'content-type'      => 'application/json',
        ];
    }

    protected function seam_filter(): string { return 'wpchat_anthropic_http_response'; }

    protected function build_request(array $messages, array $tools, string $system, string $model): array {
        $request = [
            'model'      => $model,
            'max_tokens' => 4096,
            'messages'   => $messages,
        ];
        if ($system !== '') {
            $request['system'] = $system;
        }
        if (!empty($tools)) {
            $request['tools'] = $tools;
        }
        return $request;
    }

    protected function parse_response(array $data): array {
        return [
            'content'     => $data['content'] ?? [],
            'stop_reason' => $data['stop_reason'] ?? 'end_turn',
        ];
    }

    protected function error_message(array $data, int $code): string {
        return $data['error']['message'] ?? "HTTP $code";
    }
}

/**
 * Back-compat facade — existing call sites use `Anthropic::run_with_tools` /
 * `Anthropic::validate_key`. New code should use `LLM::run_with_tools` /
 * `LLM::active()`.
 */
class Anthropic {

    const ENDPOINT = AnthropicProvider::ENDPOINT;
    const VERSION  = AnthropicProvider::VERSION;
    const MAX_LOOP = BaseLLMProvider::MAX_LOOP;

    public static function run_with_tools(array $messages, array $tools, array $tool_impls, array $opts = []): array {
        return (new AnthropicProvider())->run_with_tools($messages, $tools, $tool_impls, $opts);
    }

    public static function validate_key(string $key): array {
        return (new AnthropicProvider())->validate_key($key);
    }
}
