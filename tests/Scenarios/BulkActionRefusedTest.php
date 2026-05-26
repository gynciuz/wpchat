<?php
/**
 * SCENARIO — the assistant must NEVER batch destructive operations,
 * even if the user explicitly asks. This is the "physically cannot
 * wreck your store" guarantee we sell on.
 *
 * Structural test: no tool definition exposes an array-of-ids field,
 * and the system prompt contains the bulk-action ban.
 *
 * @package WPChat\Tests
 */

namespace WPChat\Tests\Scenarios;

use WPChat\Tools;
use WPChat\Tests\TestCase;

class BulkActionRefusedTest extends TestCase {

    public function test_no_tool_accepts_array_of_ids(): void {
        $forbidden_field_names = ['order_ids', 'post_ids', 'user_ids', 'ids', 'targets'];
        foreach (Tools::definitions() as $def) {
            $properties = $def['input_schema']['properties'] ?? [];
            // SDL stdClass case (no properties).
            if (!is_array($properties)) {
                continue;
            }
            foreach ($properties as $field => $schema) {
                $this->assertNotContains(
                    $field,
                    $forbidden_field_names,
                    sprintf(
                        'Tool "%s" exposes field "%s" — this would enable bulk destructive ops via the LLM. Move it to a single-target field.',
                        $def['name'],
                        $field
                    )
                );
            }
        }
    }

    public function test_no_tool_accepts_bulk_flag(): void {
        foreach (Tools::definitions() as $def) {
            $properties = $def['input_schema']['properties'] ?? [];
            if (!is_array($properties)) {
                continue;
            }
            $this->assertArrayNotHasKey('bulk', $properties, "Tool {$def['name']} should not have a `bulk` flag.");
            $this->assertArrayNotHasKey('apply_to_all', $properties, "Tool {$def['name']} should not have an `apply_to_all` flag.");
        }
    }

    public function test_system_prompt_explicitly_bans_bulk_destructive(): void {
        $this->mockAnthropic->enqueueEndTurn('ok');
        $this->postChat('hi');

        $system = $this->mockAnthropic->lastRequest()['system'] ?? '';
        $this->assertStringContainsString('No bulk destructive ops', $system);
        $this->assertStringContainsString('cancel all', strtolower($system), 'Prompt must call out the typical bulk-request examples.');
    }

    public function test_delete_word_pattern_documented_in_prompt(): void {
        $this->mockAnthropic->enqueueEndTurn('ok');
        $this->postChat('hi');

        $system = $this->mockAnthropic->lastRequest()['system'] ?? '';
        // The DELETE-word safety pattern (planned but not yet wired into tools)
        // must already be documented in the prompt so the LLM applies it when
        // destructive tools land.
        $this->assertStringContainsString('DELETE', $system);
        $this->assertStringContainsString('IŠTRINTI', $system);
        $this->assertStringContainsString('УДАЛИТЬ', $system);
    }
}
