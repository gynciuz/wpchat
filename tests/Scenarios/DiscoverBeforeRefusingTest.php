<?php
/**
 * SCENARIO — the assistant must never refuse a content-edit on the grounds
 * that a page is "static HTML" or "needs FTP". Instead it locates the text
 * with find_text (which covers custom post types, post meta, and taxonomy
 * terms) and edits what's editable, handing off only for content that is
 * genuinely theme-hardcoded.
 *
 * Locks in the 2026-05-27 fix (the LLM wrongly told a user they needed FTP to
 * edit a barber's role) — now generalised to the find_text discovery flow that
 * replaced the old hardcoded "person/barber → team_member" mapping.
 *
 * @package ChatAdmin\Tests
 */

namespace ChatAdmin\Tests\Scenarios;

use ChatAdmin\Tests\TestCase;

class DiscoverBeforeRefusingTest extends TestCase {

    public function test_system_prompt_contains_discover_before_giving_up_rule(): void {
        $this->mockAnthropic->enqueueEndTurn('ok');
        $this->postChat('hi');

        $system = $this->mockAnthropic->lastRequest()['system'] ?? '';
        $this->assertStringContainsString('Discover before giving up', $system);
        $this->assertStringContainsString('list_content_blocks', $system);
    }

    public function test_system_prompt_forbids_static_html_refusal(): void {
        $this->mockAnthropic->enqueueEndTurn('ok');
        $this->postChat('hi');

        $system = $this->mockAnthropic->lastRequest()['system'] ?? '';
        $this->assertStringContainsString(
            'Never refuse just because a page "looks static."',
            $system,
            'The prompt must explicitly forbid the "static HTML, you need FTP" dead-end response.'
        );
    }

    public function test_system_prompt_leads_discovery_with_find_text(): void {
        $this->mockAnthropic->enqueueEndTurn('ok');
        $this->postChat('hi');

        $system = $this->mockAnthropic->lastRequest()['system'] ?? '';
        // Discovery is now driven by find_text (locate the text wherever it is
        // stored) plus custom-post-type awareness — not a hardcoded mapping to a
        // team_member backend that a site may not even have registered.
        $this->assertStringContainsString('find_text', $system);
        $this->assertStringContainsString('Custom post types', $system);
    }
}
