<?php
/**
 * SCENARIO — the /chat endpoint is per-user rate limited so a compromised
 * editor account or a looping client can't run up an unbounded Anthropic
 * bill. Direct-action endpoints (no LLM) are intentionally NOT limited.
 *
 * @package ChatAdmin\Tests
 */

namespace ChatAdmin\Tests\Scenarios;

use ChatAdmin\Rest;
use ChatAdmin\Tests\TestCase;

class RateLimitTest extends TestCase {

    public function test_chat_blocks_after_the_limit(): void {
        // The mock returns an end_turn for every call, so each request is a
        // full (cheap) round-trip. Exhaust the window, then expect a 429.
        for ($i = 0; $i < Rest::RATE_LIMIT_MAX; $i++) {
            $res = $this->postChat("msg $i");
            $this->assertSame(200, $res['status'], "request #$i should pass");
        }
        $blocked = $this->postChat('one too many');
        $this->assertSame(429, $blocked['status']);
        $this->assertArrayHasKey('error', $blocked['data']);
    }

    public function test_filter_can_disable_the_limit(): void {
        $off = static fn() => 0;
        \add_filter('chatadmin_rate_limit_max', $off);
        for ($i = 0; $i < Rest::RATE_LIMIT_MAX + 5; $i++) {
            $res = $this->postChat("msg $i");
            $this->assertSame(200, $res['status']);
        }
        \remove_filter('chatadmin_rate_limit_max', $off);
    }
}
