<?php
/**
 * Multi-provider LLM abstraction.
 *
 * The chat engine speaks one canonical format internally: **Anthropic content
 * blocks** (text / tool_use / tool_result). The whole codebase — system prompt,
 * History storage, and the React frontend (which only reads the neutral
 * {name,input,output} capture) — is built on it. Each provider adapter
 * translates that canonical format to/from its own wire format ONLY at the HTTP
 * boundary, so adding a provider never touches tools, prompts, or the UI.
 *
 * - `LLMProvider`        — the contract.
 * - `BaseLLMProvider`    — owns the canonical tool-use loop; subclasses supply
 *                          build_request / parse_response / endpoint / headers.
 * - `OpenAIProvider`     — Chat Completions.
 * - `GeminiProvider`     — generateContent.
 * - `AnthropicProvider`  — in class-anthropic.php (near-identity adapter).
 * - `LLM`                — registry/router; `apply_filters('wpchat_llm_providers')`.
 *
 * @package WPChat
 */

namespace WPChat;

if (!defined('ABSPATH')) {
    exit;
}

interface LLMProvider {
    /** Stable slug: 'anthropic' | 'openai' | 'gemini'. */
    public function id(): string;
    /** Human label, e.g. "Anthropic", "OpenAI", "Google Gemini". */
    public function label(): string;
    /** @return array<int, array{id: string, label: string}> selectable models. */
    public function models(): array;
    public function default_model(): string;
    /** UI hints for the key step. @return array{url: string, placeholder: string, regex: string} */
    public function key_help(): array;
    /** Does this API key look like it belongs to this provider? (prefix match) */
    public function matches_key(string $key): bool;
    /** Run the tool-use loop. @return array{messages: array, text: string, tool_calls: array} */
    public function run_with_tools(array $messages, array $tools, array $tool_impls, array $opts = []): array;
    /** Cheap auth check. @return array{ok: bool, error?: string, inconclusive?: bool} */
    public function validate_key(string $key): array;
}

abstract class BaseLLMProvider implements LLMProvider {

    const MAX_LOOP = 8; // tool-call loop guard

    // ---- subclass contract ----------------------------------------------

    /** Full request URL (model may be in the path for some providers). */
    abstract protected function endpoint(string $model): string;
    /** HTTP headers including auth. */
    abstract protected function headers(string $key): array;
    /** Filter name used as the test seam (also the back-compat point). */
    abstract protected function seam_filter(): string;
    /** Translate canonical messages/tools/system → this provider's request body. */
    abstract protected function build_request(array $messages, array $tools, string $system, string $model): array;
    /** Translate a 200 response body → ['content' => canonical blocks, 'stop_reason' => 'tool_use'|'end_turn']. */
    abstract protected function parse_response(array $data): array;
    /** Extract a human error string from a non-200 body. */
    abstract protected function error_message(array $data, int $code): string;

    // ---- canonical tool-use loop ----------------------------------------

    public function run_with_tools(array $messages, array $tools, array $tool_impls, array $opts = []): array {
        $key = Settings::get_api_key();
        if (!$key) {
            throw new \RuntimeException(sprintf(
                /* translators: %s = provider label */
                __('%s API key not configured. Set it in WPChat → Settings.', 'wpchat'),
                $this->label()
            ));
        }

        $model      = $opts['model'] ?? Settings::get_model();
        $system     = $opts['system'] ?? '';
        $captured   = [];
        $final_text = '';
        $loops      = 0;

        while ($loops++ < self::MAX_LOOP) {
            $request = $this->build_request($messages, $tools, $system, $model);

            // Test seam: a registered filter short-circuits the real HTTP call.
            $response = apply_filters($this->seam_filter(), null, $request);
            if ($response === null) {
                $response = wp_remote_post($this->endpoint($model), [
                    'timeout' => 60,
                    'headers' => $this->headers($key),
                    'body'    => wp_json_encode($request),
                ]);
            }

            if (is_wp_error($response)) {
                throw new \RuntimeException('HTTP error: ' . $response->get_error_message());
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($data)) {
                $data = [];
            }
            if ($code !== 200) {
                throw new \RuntimeException($this->label() . ' API error: ' . $this->error_message($data, $code));
            }

            $parsed      = $this->parse_response($data);
            $content     = $this->normalize_empty_inputs($parsed['content'] ?? []);
            $stop_reason = $parsed['stop_reason'] ?? 'end_turn';

            // Append the assistant turn to canonical history.
            $messages[] = ['role' => 'assistant', 'content' => $content];

            $tool_uses = [];
            foreach ($content as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $final_text .= ($final_text ? "\n" : '') . ($block['text'] ?? '');
                } elseif (($block['type'] ?? '') === 'tool_use') {
                    $tool_uses[] = $block;
                }
            }

            if ($stop_reason !== 'tool_use' || empty($tool_uses)) {
                break;
            }

            // Execute tools and feed results back as a canonical user turn.
            $tool_results = [];
            foreach ($tool_uses as $use) {
                $name = $use['name'] ?? '';
                $args = $use['input'] ?? [];
                if ($args instanceof \stdClass) {
                    $args = (array) $args;
                }
                $impl   = $tool_impls[$name] ?? null;
                $output = is_callable($impl) ? $this->safe_call($impl, $args) : ['error' => "Unknown tool: $name"];

                $captured[]     = ['name' => $name, 'input' => $args, 'output' => $output];
                $tool_results[] = [
                    'type'        => 'tool_result',
                    'tool_use_id' => $use['id'] ?? '',
                    'content'     => wp_json_encode($output),
                ];
            }

            $messages[] = ['role' => 'user', 'content' => $tool_results];
        }

        return ['messages' => $messages, 'text' => $final_text, 'tool_calls' => $captured];
    }

    // ---- shared helpers --------------------------------------------------

    protected function safe_call($impl, array $args): array {
        try {
            $out = call_user_func($impl, $args);
            return is_array($out) ? $out : ['result' => $out];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Empty tool_use.input decodes to PHP `[]` which re-encodes as `[]`, but
     * providers expect an object `{}`. Force any empty input back to stdClass.
     */
    protected function normalize_empty_inputs(array $content): array {
        foreach ($content as &$block) {
            if (($block['type'] ?? '') === 'tool_use'
                && isset($block['input'])
                && is_array($block['input'])
                && empty($block['input'])
            ) {
                $block['input'] = new \stdClass();
            }
        }
        unset($block);
        return $content;
    }

    /** Run a tiny request through the seam + HTTP and classify the auth result. */
    protected function check_key(array $request, string $key): array {
        $response = apply_filters($this->seam_filter(), null, $request);
        if ($response === null) {
            $response = wp_remote_post($this->endpoint($request['model'] ?? $this->default_model()), [
                'timeout' => 15,
                'headers' => $this->headers($key),
                'body'    => wp_json_encode($request),
            ]);
        }
        if (is_wp_error($response)) {
            return ['ok' => true, 'inconclusive' => true];
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code === 401 || $code === 403) {
            return ['ok' => false, 'error' => sprintf(
                /* translators: %s = provider label */
                __('%s rejected this key (invalid or revoked).', 'wpchat'),
                $this->label()
            )];
        }
        // 200 = fine; 400 = key authenticated but request quirk; 429/5xx = inconclusive.
        return ['ok' => true];
    }

    /** Input is JSON for tool encoding: empty array → {} object. */
    protected static function json_obj($input) {
        if ($input instanceof \stdClass) {
            return $input;
        }
        return empty($input) ? new \stdClass() : (object) $input;
    }
}

/**
 * OpenAI — Chat Completions API.
 */
class OpenAIProvider extends BaseLLMProvider {

    public function id(): string { return 'openai'; }
    public function label(): string { return 'OpenAI'; }
    public function default_model(): string { return 'gpt-4.1'; }

    public function models(): array {
        // NOTE: verify against platform.openai.com/docs/models at release time.
        return [
            ['id' => 'gpt-4.1',      'label' => 'GPT-4.1 (recommended)'],
            ['id' => 'gpt-4o',       'label' => 'GPT-4o (fast, capable)'],
            ['id' => 'gpt-4o-mini',  'label' => 'GPT-4o mini (cheapest)'],
        ];
    }

    public function key_help(): array {
        return [
            'url'         => 'https://platform.openai.com/api-keys',
            'placeholder' => 'sk-...',
            'regex'       => '^sk-[A-Za-z0-9_\\-]+$',
        ];
    }

    public function matches_key(string $key): bool {
        // OpenAI keys start with sk- (incl. sk-proj-). Anthropic (sk-ant-) is
        // claimed first in the detection loop, so a plain sk- lands here.
        return (bool) preg_match('/^sk-/i', trim($key));
    }

    public function validate_key(string $key): array {
        return $this->check_key([
            'model'      => 'gpt-4o-mini',
            'max_tokens' => 1,
            'messages'   => [['role' => 'user', 'content' => 'hi']],
        ], $key);
    }

    protected function endpoint(string $model): string {
        return 'https://api.openai.com/v1/chat/completions';
    }

    protected function headers(string $key): array {
        return [
            'Authorization' => 'Bearer ' . $key,
            'content-type'  => 'application/json',
        ];
    }

    protected function seam_filter(): string { return 'wpchat_openai_http_response'; }

    protected function build_request(array $messages, array $tools, string $system, string $model): array {
        $out = ['model' => $model, 'max_tokens' => 4096, 'messages' => []];

        if ($system !== '') {
            $out['messages'][] = ['role' => 'system', 'content' => $system];
        }

        foreach ($messages as $m) {
            $role    = $m['role'] ?? 'user';
            $content = $m['content'] ?? '';

            if (is_string($content)) {
                $out['messages'][] = ['role' => $role, 'content' => $content];
                continue;
            }

            if ($role === 'assistant') {
                $text       = '';
                $tool_calls = [];
                foreach ($content as $b) {
                    $t = $b['type'] ?? '';
                    if ($t === 'text') {
                        $text .= $b['text'] ?? '';
                    } elseif ($t === 'tool_use') {
                        $tool_calls[] = [
                            'id'       => $b['id'] ?? '',
                            'type'     => 'function',
                            'function' => [
                                'name'      => $b['name'] ?? '',
                                'arguments' => wp_json_encode(self::json_obj($b['input'] ?? [])),
                            ],
                        ];
                    }
                }
                $am = ['role' => 'assistant', 'content' => $text !== '' ? $text : null];
                if ($tool_calls) {
                    $am['tool_calls'] = $tool_calls;
                }
                $out['messages'][] = $am;
            } else { // user turn — either tool_result blocks or text
                $has_results = false;
                foreach ($content as $b) {
                    if (($b['type'] ?? '') === 'tool_result') {
                        $has_results = true;
                        $out['messages'][] = [
                            'role'         => 'tool',
                            'tool_call_id' => $b['tool_use_id'] ?? '',
                            'content'      => is_string($b['content'] ?? '') ? $b['content'] : wp_json_encode($b['content']),
                        ];
                    }
                }
                if (!$has_results) {
                    $text = '';
                    foreach ($content as $b) {
                        if (($b['type'] ?? '') === 'text') {
                            $text .= $b['text'] ?? '';
                        }
                    }
                    $out['messages'][] = ['role' => 'user', 'content' => $text];
                }
            }
        }

        if (!empty($tools)) {
            $out['tools'] = array_map(static function ($t) {
                return [
                    'type'     => 'function',
                    'function' => [
                        'name'        => $t['name'],
                        'description' => $t['description'] ?? '',
                        'parameters'  => $t['input_schema'] ?? ['type' => 'object', 'properties' => new \stdClass()],
                    ],
                ];
            }, $tools);
        }

        return $out;
    }

    protected function parse_response(array $data): array {
        $choice = $data['choices'][0] ?? [];
        $msg    = $choice['message'] ?? [];
        $finish = $choice['finish_reason'] ?? '';

        $content = [];
        if (!empty($msg['content'])) {
            $content[] = ['type' => 'text', 'text' => (string) $msg['content']];
        }
        foreach ($msg['tool_calls'] ?? [] as $tc) {
            $args = json_decode($tc['function']['arguments'] ?? '{}', true);
            $content[] = [
                'type'  => 'tool_use',
                'id'    => $tc['id'] ?? ('call_' . wp_generate_uuid4()),
                'name'  => $tc['function']['name'] ?? '',
                'input' => is_array($args) ? $args : [],
            ];
        }

        return [
            'content'     => $content,
            'stop_reason' => $finish === 'tool_calls' ? 'tool_use' : 'end_turn',
        ];
    }

    protected function error_message(array $data, int $code): string {
        return $data['error']['message'] ?? "HTTP $code";
    }
}

/**
 * Google Gemini — generateContent API.
 */
class GeminiProvider extends BaseLLMProvider {

    public function id(): string { return 'gemini'; }
    public function label(): string { return 'Google Gemini'; }
    public function default_model(): string { return 'gemini-2.5-flash'; }

    public function models(): array {
        // NOTE: verify against ai.google.dev/gemini-api/docs/models at release time.
        return [
            ['id' => 'gemini-2.5-flash', 'label' => 'Gemini 2.5 Flash (recommended)'],
            ['id' => 'gemini-2.5-pro',   'label' => 'Gemini 2.5 Pro (highest quality)'],
            ['id' => 'gemini-2.0-flash', 'label' => 'Gemini 2.0 Flash (fastest)'],
        ];
    }

    public function key_help(): array {
        return [
            'url'         => 'https://aistudio.google.com/app/apikey',
            'placeholder' => 'AIza...',
            'regex'       => '^[A-Za-z0-9_\\-]+$',
        ];
    }

    public function matches_key(string $key): bool {
        // Google API keys start with AIza.
        return (bool) preg_match('/^AIza/', trim($key));
    }

    public function validate_key(string $key): array {
        return $this->check_key([
            'model'    => $this->default_model(),
            'contents' => [['role' => 'user', 'parts' => [['text' => 'hi']]]],
            'generationConfig' => ['maxOutputTokens' => 1],
        ], $key);
    }

    protected function endpoint(string $model): string {
        return 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';
    }

    protected function headers(string $key): array {
        return [
            'x-goog-api-key' => $key,
            'content-type'   => 'application/json',
        ];
    }

    protected function seam_filter(): string { return 'wpchat_gemini_http_response'; }

    protected function build_request(array $messages, array $tools, string $system, string $model): array {
        // Gemini has no tool-call ids; functionResponse is matched by name.
        // Build an id→name map from assistant tool_use blocks.
        $id_to_name = [];
        foreach ($messages as $m) {
            if (($m['role'] ?? '') === 'assistant' && is_array($m['content'] ?? null)) {
                foreach ($m['content'] as $b) {
                    if (($b['type'] ?? '') === 'tool_use') {
                        $id_to_name[$b['id'] ?? ''] = $b['name'] ?? '';
                    }
                }
            }
        }

        $contents = [];
        foreach ($messages as $m) {
            $role    = $m['role'] ?? 'user';
            $content = $m['content'] ?? '';
            $g_role  = $role === 'assistant' ? 'model' : 'user';
            $parts   = [];

            if (is_string($content)) {
                if ($content !== '') {
                    $parts[] = ['text' => $content];
                }
            } else {
                foreach ($content as $b) {
                    $t = $b['type'] ?? '';
                    if ($t === 'text') {
                        $parts[] = ['text' => $b['text'] ?? ''];
                    } elseif ($t === 'tool_use') {
                        $parts[] = ['functionCall' => [
                            'name' => $b['name'] ?? '',
                            'args' => self::json_obj($b['input'] ?? []),
                        ]];
                    } elseif ($t === 'tool_result') {
                        $decoded = json_decode(is_string($b['content'] ?? '') ? $b['content'] : wp_json_encode($b['content']), true);
                        $parts[] = ['functionResponse' => [
                            'name'     => $id_to_name[$b['tool_use_id'] ?? ''] ?? '',
                            'response' => is_array($decoded) ? $decoded : ['result' => $decoded],
                        ]];
                    }
                }
            }

            if ($parts) {
                $contents[] = ['role' => $g_role, 'parts' => $parts];
            }
        }

        $req = ['contents' => $contents, 'generationConfig' => ['maxOutputTokens' => 4096]];
        if ($system !== '') {
            $req['systemInstruction'] = ['parts' => [['text' => $system]]];
        }
        if (!empty($tools)) {
            $decls = array_map(function ($t) {
                return [
                    'name'        => $t['name'],
                    'description' => $t['description'] ?? '',
                    'parameters'  => $this->sanitize_schema($t['input_schema'] ?? ['type' => 'object', 'properties' => new \stdClass()]),
                ];
            }, $tools);
            $req['tools'] = [['function_declarations' => $decls]];
        }

        return $req;
    }

    protected function parse_response(array $data): array {
        $parts    = $data['candidates'][0]['content']['parts'] ?? [];
        $content  = [];
        $has_call = false;
        foreach ($parts as $p) {
            if (isset($p['text'])) {
                $content[] = ['type' => 'text', 'text' => (string) $p['text']];
            } elseif (isset($p['functionCall'])) {
                $has_call = true;
                $fc   = $p['functionCall'];
                $args = $fc['args'] ?? [];
                if ($args instanceof \stdClass) {
                    $args = (array) $args;
                }
                $content[] = [
                    'type'  => 'tool_use',
                    'id'    => 'call_' . wp_generate_uuid4(),
                    'name'  => $fc['name'] ?? '',
                    'input' => is_array($args) ? $args : [],
                ];
            }
        }
        return [
            'content'     => $content,
            'stop_reason' => $has_call ? 'tool_use' : 'end_turn',
        ];
    }

    protected function error_message(array $data, int $code): string {
        return $data['error']['message'] ?? "HTTP $code";
    }

    /**
     * Gemini's function-declaration schema accepts a subset of JSON Schema and
     * rejects extras like `additionalProperties`. Strip unsupported keys.
     */
    private function sanitize_schema($schema) {
        if ($schema instanceof \stdClass) {
            $schema = (array) $schema;
        }
        if (!is_array($schema)) {
            return $schema;
        }
        unset($schema['additionalProperties'], $schema['$schema'], $schema['$id']);
        foreach ($schema as $k => $v) {
            if (is_array($v) || $v instanceof \stdClass) {
                $schema[$k] = $this->sanitize_schema($v);
            }
        }
        return $schema;
    }
}

/**
 * Provider registry + router. Active provider comes from Settings::get_provider().
 */
class LLM {

    /** @return array<string, LLMProvider> keyed by id. */
    public static function providers(): array {
        $defaults = [
            new AnthropicProvider(),
            new OpenAIProvider(),
            new GeminiProvider(),
        ];
        /** Sites can register custom providers (e.g. a self-hosted proxy). */
        $list = apply_filters('wpchat_llm_providers', $defaults);

        $out = [];
        foreach ($list as $p) {
            if ($p instanceof LLMProvider) {
                $out[$p->id()] = $p;
            }
        }
        return $out;
    }

    public static function get(string $id): ?LLMProvider {
        return self::providers()[$id] ?? null;
    }

    /**
     * Detect the provider from an API key's prefix. Order matters: Anthropic
     * (sk-ant-) is checked before OpenAI (sk-). Returns the provider id or null.
     */
    public static function detect(string $key): ?string {
        foreach (self::providers() as $id => $provider) {
            if ($provider->matches_key($key)) {
                return $id;
            }
        }
        return null;
    }

    /** The configured provider, falling back to Anthropic. */
    public static function active(): LLMProvider {
        $providers = self::providers();
        $id        = Settings::get_provider();
        return $providers[$id] ?? $providers['anthropic'];
    }

    public static function run_with_tools(array $messages, array $tools, array $tool_impls, array $opts = []): array {
        return self::active()->run_with_tools($messages, $tools, $tool_impls, $opts);
    }
}
