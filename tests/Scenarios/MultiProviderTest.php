<?php
/**
 * SCENARIO — the chat engine runs unchanged on OpenAI and Gemini.
 *
 * Each adapter translates the canonical Anthropic-shaped messages/tools to the
 * provider wire format and back. The proof: a tool-calling chat on each provider
 * yields the SAME neutral {name, input, output} captures the frontend consumes,
 * and the outgoing request carries that provider's tool envelope.
 *
 * @package WPChat\Tests
 */

namespace WPChat\Tests\Scenarios;

use WPChat\Tests\TestCase;
use WPChat\Tests\MockOpenAI;
use WPChat\Tests\MockGemini;

class MultiProviderTest extends TestCase {

    private function use_provider(string $id, string $key, string $model): void {
        \update_option('wpchat_settings', [
            'llm_provider'    => $id,
            $id . '_api_key'  => $key,
            'model'           => $model,
        ]);
    }

    public function test_openai_run_yields_canonical_captures_and_function_envelope(): void {
        $this->use_provider('openai', 'sk-openai-test', 'gpt-4o-mini');
        $mock = (new MockOpenAI())->register();
        $mock->enqueueToolCall('list_orders', ['limit' => 5])->enqueueEndTurn('Here are your orders.');

        $res = $this->postChat('show recent orders');

        $this->assertSame(200, $res['status']);
        $calls = $res['data']['tool_calls'] ?? [];
        $this->assertNotEmpty($calls, 'OpenAI run should capture the tool call.');
        $this->assertSame('list_orders', $calls[0]['name']);
        $this->assertArrayHasKey('input', $calls[0]);
        $this->assertArrayHasKey('output', $calls[0]);
        $this->assertSame('Here are your orders.', $res['data']['text']);

        // Outgoing request used OpenAI's function envelope + a system message.
        $first = $mock->recordedRequests()[0];
        $this->assertSame('function', $first['tools'][0]['type']);
        $this->assertSame('list_orders', $first['tools'][0]['function']['name']);
        $this->assertSame('system', $first['messages'][0]['role']);

        // The tool result was fed back as a role:tool message on the 2nd request.
        $last = $mock->lastRequest();
        $roles = array_column($last['messages'], 'role');
        $this->assertContains('tool', $roles);

        $mock->unregister();
    }

    public function test_gemini_run_yields_canonical_captures_and_function_declarations(): void {
        $this->use_provider('gemini', 'AIzaTESTKEY', 'gemini-2.5-flash');
        $mock = (new MockGemini())->register();
        $mock->enqueueToolCall('list_orders', ['limit' => 5])->enqueueEndTurn('Here are your orders.');

        $res = $this->postChat('show recent orders');

        $this->assertSame(200, $res['status']);
        $calls = $res['data']['tool_calls'] ?? [];
        $this->assertSame('list_orders', $calls[0]['name'] ?? null);
        $this->assertSame('Here are your orders.', $res['data']['text']);

        // Gemini envelope: function_declarations + systemInstruction.
        $first = $mock->recordedRequests()[0];
        $this->assertArrayHasKey('function_declarations', $first['tools'][0]);
        $this->assertSame('list_orders', $first['tools'][0]['function_declarations'][0]['name']);
        $this->assertArrayHasKey('systemInstruction', $first);

        // The tool result was fed back as a functionResponse part.
        $last      = $mock->lastRequest();
        $has_resp  = false;
        foreach ($last['contents'] as $c) {
            foreach ($c['parts'] as $p) {
                if (isset($p['functionResponse'])) {
                    $has_resp = true;
                }
            }
        }
        $this->assertTrue($has_resp, 'Gemini should feed the tool result back as functionResponse.');

        $mock->unregister();
    }

    public function test_gemini_strips_unsupported_schema_keys(): void {
        // additionalProperties must be stripped from function_declarations.
        $this->use_provider('gemini', 'AIzaTESTKEY', 'gemini-2.5-flash');
        $mock = (new MockGemini())->register();
        $mock->enqueueEndTurn('ok');
        $this->postChat('hi');

        $first = $mock->recordedRequests()[0];
        $json  = json_encode($first['tools'][0]['function_declarations']);
        $this->assertStringNotContainsString('additionalProperties', $json);

        $mock->unregister();
    }
}
