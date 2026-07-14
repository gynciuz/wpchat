<?php
/**
 * SCENARIO — assistant must never refuse a content-edit request on the
 * grounds that the page is "static HTML." A site-registered backend may
 * explicitly handle static HTML (the GE team_member backend does exactly
 * this). The assistant has to try the available kinds before giving up.
 *
 * Locks in the fix for the 2026-05-27 regression where the LLM told Vlad
 * he needed FTP access to edit a barber's role, even though the
 * team_member backend was registered and could write to the static files.
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
            'NEVER refuse a content edit on the grounds that the page is "static HTML."',
            $system,
            'The prompt must explicitly forbid the "static HTML, you need FTP" dead-end response.'
        );
    }

    public function test_system_prompt_provides_kind_to_intent_mapping(): void {
        $this->mockAnthropic->enqueueEndTurn('ok');
        $this->postChat('hi');

        $system = $this->mockAnthropic->lastRequest()['system'] ?? '';
        // The prompt must teach: person/barber → team_member.
        $this->assertStringContainsString('team_member', $system);
        $this->assertStringContainsString('person/barber', $system);
    }
}
