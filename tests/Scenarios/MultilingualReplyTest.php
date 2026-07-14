<?php
/**
 * SCENARIO — the system prompt must instruct the LLM to mirror the
 * user's language (LT input → LT reply, RU input → RU reply, etc.)
 * AND to write to the site in the site's content language by default.
 *
 * Locks in the multilingual behavior so a future prompt edit can't
 * silently break the cross-language UX Vlad's wife relies on.
 *
 * @package ChatAdmin\Tests
 */

namespace ChatAdmin\Tests\Scenarios;

use ChatAdmin\Tests\TestCase;

class MultilingualReplyTest extends TestCase {

    public function test_prompt_requires_user_language_mirror(): void {
        $this->mockAnthropic->enqueueEndTurn('ok');
        $this->postChat('hi');
        $system = $this->mockAnthropic->lastRequest()['system'] ?? '';

        $this->assertStringContainsString('ALWAYS respond in the user\'s language', $system);
    }

    public function test_prompt_specifies_site_language_for_writes(): void {
        $this->mockAnthropic->enqueueEndTurn('ok');
        $this->postChat('hi');
        $system = $this->mockAnthropic->lastRequest()['system'] ?? '';

        $this->assertStringContainsString('use the site\'s content language by default', $system);
    }

    public function test_prompt_includes_today_date(): void {
        $this->mockAnthropic->enqueueEndTurn('ok');
        $this->postChat('hi');
        $system = $this->mockAnthropic->lastRequest()['system'] ?? '';

        $this->assertStringContainsString('Today\'s date', $system);
        $this->assertStringContainsString(date('Y-'), $system);
    }
}
