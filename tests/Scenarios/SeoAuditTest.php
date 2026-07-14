<?php
/**
 * SCENARIO — ChatAdmin can audit a site's SEO/AEO state via the read-only
 * `seo_audit` tool, and the system prompt steers it to audit-first + fix
 * via the seo_setting / seo_meta content kinds.
 *
 * @package ChatAdmin\Tests
 */

namespace ChatAdmin\Tests\Scenarios;

use ChatAdmin\Tools;
use ChatAdmin\Tests\TestCase;

class SeoAuditTest extends TestCase {

    public function test_tool_is_defined_and_mapped(): void {
        $names = array_column(Tools::definitions(), 'name');
        $this->assertContains('seo_audit', $names);

        $impls = Tools::implementations();
        $this->assertTrue(is_callable($impls['seo_audit'] ?? null));
    }

    public function test_audit_returns_structured_report(): void {
        $report = Tools::seo_audit([]);
        $this->assertArrayHasKey('checks', $report);
        $this->assertArrayHasKey('summary', $report);
        foreach (['search_engine_visibility', 'permalinks', 'https', 'seo_plugin', 'ai_crawlers', 'llms_txt'] as $key) {
            $this->assertArrayHasKey($key, $report['checks'], "audit missing check: {$key}");
            $this->assertArrayHasKey('status', $report['checks'][$key]);
            $this->assertArrayHasKey('fixable', $report['checks'][$key]);
        }
    }

    public function test_system_prompt_steers_seo_behaviour(): void {
        $this->mockAnthropic->enqueueEndTurn('ok');
        $this->postChat('hi');
        $system = $this->mockAnthropic->lastRequest()['system'] ?? '';

        $this->assertStringContainsString('seo_audit', $system);
        $this->assertStringContainsString('SEO & AI-SEO', $system);
        // Must point at the fix kinds, not invent a separate write tool.
        $this->assertStringContainsString('seo_setting', $system);
        $this->assertStringContainsString('seo_meta', $system);
    }

    public function test_chat_routes_to_seo_audit(): void {
        $this->mockAnthropic
            ->enqueueToolUse('seo_audit', [])
            ->enqueueEndTurn('Your SEO looks mostly good — a couple of fixes recommended.');

        $res = $this->postChat('audit my seo please');

        $this->assertSame(200, $res['status']);
        $calls = $res['data']['tool_calls'] ?? [];
        $this->assertNotEmpty($calls);
        $this->assertSame('seo_audit', $calls[0]['name']);
        $this->assertArrayHasKey('checks', $calls[0]['output']);
    }
}
