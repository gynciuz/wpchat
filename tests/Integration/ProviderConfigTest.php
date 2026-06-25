<?php
/**
 * Provider-aware config + onboarding: provider/key resolution, the
 * /onboarding/llm-provider route, per-provider key validation, and the
 * model list scoping to the active provider.
 *
 * @package WPChat\Tests
 */

namespace WPChat\Tests\Integration;

use WPChat\Settings;
use WPChat\LLM;
use WPChat\Tests\TestCase;

class ProviderConfigTest extends TestCase {

    public function test_detect_provider_from_key_prefix(): void {
        $this->assertSame('anthropic', LLM::detect('sk-ant-abc123'));
        $this->assertSame('openai', LLM::detect('sk-proj-abc123'));
        $this->assertSame('openai', LLM::detect('sk-abc123'));
        $this->assertSame('gemini', LLM::detect('AIzaSyAbc123'));
        $this->assertNull(LLM::detect('not-a-real-key'));
    }

    public function test_api_key_save_autodetects_provider_and_sets_active(): void {
        $ok = function () {
            return ['response' => ['code' => 200], 'body' => json_encode(['candidates' => [['content' => ['parts' => [['text' => 'ok']]]]]]), 'headers' => []];
        };
        \add_filter('wpchat_gemini_http_response', $ok, 99, 2);

        $request = new \WP_REST_Request('POST', '/wpchat/v1/onboarding/api-key');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode(['key' => 'AIzaSyTESTKEY123'])); // no provider param
        $response = \rest_get_server()->dispatch($request);

        \remove_filter('wpchat_gemini_http_response', $ok, 99);

        $this->assertSame(200, $response->get_status());
        $options = \get_option('wpchat_settings');
        $this->assertSame('gemini', $options['llm_provider']);
        $this->assertSame('AIzaSyTESTKEY123', $options['gemini_api_key']);
        $this->assertStringStartsWith('gemini-', $options['model']); // model reset to gemini default
        $this->assertSame('gemini', $response->get_data()['apiKey']['provider']);
    }

    public function test_api_key_save_rejects_unrecognized_key(): void {
        $request = new \WP_REST_Request('POST', '/wpchat/v1/onboarding/api-key');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode(['key' => 'random-string-no-prefix']));
        $response = \rest_get_server()->dispatch($request);
        $this->assertSame(400, $response->get_status());
    }

    public function test_provider_and_per_provider_keys_resolve(): void {
        \update_option('wpchat_settings', [
            'llm_provider'      => 'openai',
            'openai_api_key'    => 'sk-oai',
            'anthropic_api_key' => 'sk-ant',
        ]);
        $this->assertSame('openai', Settings::get_provider());
        $this->assertSame('sk-oai', Settings::get_api_key());  // active provider's key
        // (anthropic key is constant-shadowed in some suite orderings, so we
        // don't assert its option value here — that path is covered elsewhere.)
        $this->assertSame('option', Settings::key_source('openai'));
        $this->assertSame('none', Settings::key_source('gemini'));
    }

    public function test_set_llm_provider_switches_and_resets_model(): void {
        $request = new \WP_REST_Request('POST', '/wpchat/v1/onboarding/llm-provider');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode(['provider' => 'gemini']));
        $response = \rest_get_server()->dispatch($request);

        $this->assertSame(200, $response->get_status());
        $this->assertSame('gemini', Settings::get_provider());
        // The seeded 'mock-claude' model isn't a Gemini model → reset to default.
        $this->assertStringStartsWith('gemini-', Settings::get_model());
        $this->assertSame('gemini', $response->get_data()['llmProvider']['current']);
    }

    public function test_set_llm_provider_rejects_unknown(): void {
        $request = new \WP_REST_Request('POST', '/wpchat/v1/onboarding/llm-provider');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode(['provider' => 'bogus']));
        $response = \rest_get_server()->dispatch($request);
        $this->assertSame(400, $response->get_status());
    }

    public function test_status_lists_active_provider_models(): void {
        \update_option('wpchat_settings', ['llm_provider' => 'openai']);
        $request  = new \WP_REST_Request('GET', '/wpchat/v1/onboarding/status');
        $response = \rest_get_server()->dispatch($request);
        $data     = $response->get_data();

        $ids = array_column($data['model']['options'], 'id');
        $this->assertContains('gpt-4o', $ids);
        $this->assertSame('openai', $data['llmProvider']['current']);
        $this->assertSame('openai', $data['apiKey']['provider']);
        $this->assertArrayHasKey('keyHelp', $data['apiKey']);
    }

    public function test_api_key_save_validates_against_chosen_provider(): void {
        \update_option('wpchat_settings', ['llm_provider' => 'openai']);
        // Simulate OpenAI rejecting the key on its own seam.
        $reject = function () {
            return ['response' => ['code' => 401], 'body' => json_encode(['error' => ['message' => 'bad key']]), 'headers' => []];
        };
        \add_filter('wpchat_openai_http_response', $reject, 99, 2);

        $request = new \WP_REST_Request('POST', '/wpchat/v1/onboarding/api-key');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode(['provider' => 'openai', 'key' => 'sk-looks-ok-but-bad']));
        $response = \rest_get_server()->dispatch($request);

        \remove_filter('wpchat_openai_http_response', $reject, 99);

        $this->assertSame(400, $response->get_status());
        $options = \get_option('wpchat_settings');
        $this->assertArrayNotHasKey('openai_api_key', $options);
    }

    public function test_api_key_save_stores_under_provider_key(): void {
        \update_option('wpchat_settings', ['llm_provider' => 'openai']);
        // MockOpenAI not registered → no seam → but validate fails open only on
        // WP_Error; here we register a 200 so validation passes deterministically.
        $ok = function () {
            return ['response' => ['code' => 200], 'body' => json_encode(['choices' => [['message' => ['content' => 'ok']]]]), 'headers' => []];
        };
        \add_filter('wpchat_openai_http_response', $ok, 99, 2);

        $request = new \WP_REST_Request('POST', '/wpchat/v1/onboarding/api-key');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(json_encode(['provider' => 'openai', 'key' => 'sk-good-key']));
        $response = \rest_get_server()->dispatch($request);

        \remove_filter('wpchat_openai_http_response', $ok, 99);

        $this->assertSame(200, $response->get_status());
        $options = \get_option('wpchat_settings');
        $this->assertSame('sk-good-key', $options['openai_api_key']);
    }
}
