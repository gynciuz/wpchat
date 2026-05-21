<?php
/**
 * Minimal Anthropic Messages API client.
 *
 * @package WPChat
 */

namespace WPChat;

if (!defined('ABSPATH')) {
    exit;
}

class Anthropic {

    const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    const VERSION  = '2023-06-01';
    const MAX_LOOP = 8; // tool-call loop guard

    /**
     * Run a conversation with tools until the model returns end_turn.
     *
     * @param array $messages   Conversation history. Each: ['role' => 'user'|'assistant', 'content' => ...].
     * @param array $tools      Tool definitions (name, description, input_schema).
     * @param array $tool_impls Map of tool name → callable(array $args) returning array (will be JSON-encoded).
     * @param array $opts       ['system' => string, 'model' => string].
     * @return array            ['messages' => updated history, 'text' => final assistant text, 'tool_calls' => array of {name, input, output}]
     * @throws \RuntimeException on API or transport error.
     */
    public static function run_with_tools(array $messages, array $tools, array $tool_impls, array $opts = []): array {
        $api_key = Settings::get_api_key();
        if (!$api_key) {
            throw new \RuntimeException(__('Anthropic API key not configured. Set it in WPChat → Settings.', 'wpchat'));
        }

        $model    = $opts['model'] ?? Settings::get_model();
        $system   = $opts['system'] ?? '';
        $captured = []; // [{name, input, output}]
        $loops    = 0;
        $final_text = '';

        while ($loops++ < self::MAX_LOOP) {
            $request = [
                'model'      => $model,
                'max_tokens' => 4096,
                'messages'   => $messages,
            ];
            if ($system) {
                $request['system'] = $system;
            }
            if (!empty($tools)) {
                $request['tools'] = $tools;
            }

            $response = wp_remote_post(self::ENDPOINT, [
                'timeout' => 60,
                'headers' => [
                    'x-api-key'         => $api_key,
                    'anthropic-version' => self::VERSION,
                    'content-type'      => 'application/json',
                ],
                'body' => wp_json_encode($request),
            ]);

            if (is_wp_error($response)) {
                throw new \RuntimeException('HTTP error: ' . $response->get_error_message());
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if ($code !== 200) {
                $msg = $data['error']['message'] ?? "HTTP $code";
                throw new \RuntimeException('Anthropic API error: ' . $msg);
            }

            $stop_reason = $data['stop_reason'] ?? '';
            $content     = $data['content'] ?? [];

            // PHP's json_decode turns empty JSON objects into empty PHP arrays;
            // when we json_encode them back to send in the next request, they
            // serialize as `[]` instead of `{}`. Anthropic strictly validates
            // that tool_use.input is an object, not an array. Force any empty
            // input back to stdClass so it re-serializes as {}.
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

            // Append assistant turn verbatim to history.
            $messages[] = [
                'role'    => 'assistant',
                'content' => $content,
            ];

            // Collect text + tool_use blocks.
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

            // Execute tools and feed results back as a user turn.
            $tool_results = [];
            foreach ($tool_uses as $use) {
                $name = $use['name'] ?? '';
                $args = $use['input'] ?? [];
                // Tool implementations declare `array $args`. If we coerced
                // an empty-object input to stdClass above (for Anthropic's
                // sake), cast it back to an array here for PHP's type check.
                if ($args instanceof \stdClass) {
                    $args = (array) $args;
                }
                $impl = $tool_impls[$name] ?? null;
                if (!is_callable($impl)) {
                    $output = ['error' => "Unknown tool: $name"];
                } else {
                    try {
                        $output = call_user_func($impl, $args);
                    } catch (\Throwable $e) {
                        $output = ['error' => $e->getMessage()];
                    }
                }
                $captured[] = [
                    'name'   => $name,
                    'input'  => $args,
                    'output' => $output,
                ];
                $tool_results[] = [
                    'type'        => 'tool_result',
                    'tool_use_id' => $use['id'],
                    'content'     => wp_json_encode($output),
                ];
            }

            $messages[] = [
                'role'    => 'user',
                'content' => $tool_results,
            ];
        }

        return [
            'messages'   => $messages,
            'text'       => $final_text,
            'tool_calls' => $captured,
        ];
    }
}
