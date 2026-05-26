<?php
/**
 * SCENARIO — full conversation-history flow end-to-end:
 *  1. User sends a message → row persists, conversation_id returned.
 *  2. Same user sends a follow-up → row appended to the SAME conversation.
 *  3. GET /conversations returns it, labeled by first user message.
 *  4. GET /conversations/{id} replays the full transcript.
 *  5. A different user cannot read the first user's conversation.
 *
 * @package WPChat\Tests
 */

namespace WPChat\Tests\Scenarios;

use WPChat\Tests\TestCase;

class HistoryFlowTest extends TestCase {

    public function test_chat_post_persists_message_and_returns_conversation_id(): void {
        $this->mockAnthropic->enqueueEndTurn('Hi there.');
        $resp = $this->postChat('Hello assistant');

        $this->assertSame(200, $resp['status']);
        $this->assertNotEmpty($resp['data']['conversation_id']);
        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/',
            $resp['data']['conversation_id']
        );
    }

    public function test_followup_messages_join_the_same_conversation(): void {
        $this->mockAnthropic->enqueueEndTurn('First reply.');
        $first = $this->postChat('first');
        $conv  = $first['data']['conversation_id'];

        $this->mockAnthropic->enqueueEndTurn('Second reply.');
        $second = $this->postChat('second', $conv);

        $this->assertSame($conv, $second['data']['conversation_id']);

        $loaded = $this->getConversation($conv);
        $contents = array_column($loaded['data']['messages'], 'content');
        $this->assertContains('first', $contents);
        $this->assertContains('second', $contents);
    }

    public function test_list_conversations_labels_by_first_user_message(): void {
        $this->mockAnthropic->enqueueEndTurn('Reply.');
        $this->postChat('Show me yesterday orders please');

        $list = $this->getConversations();
        $this->assertSame(200, $list['status']);
        $this->assertNotEmpty($list['data']['conversations']);
        $this->assertStringContainsString(
            'Show me yesterday orders',
            $list['data']['conversations'][0]['label']
        );
    }

    public function test_cross_user_conversation_returns_404(): void {
        // User A sends a message.
        $this->mockAnthropic->enqueueEndTurn('Reply A.');
        $resp_a = $this->postChat('User A private message');
        $conv_a = $resp_a['data']['conversation_id'];

        // Switch to user B.
        $other = $this->factory()->user->create(['role' => 'administrator']);
        \wp_set_current_user($other);

        $stolen = $this->getConversation($conv_a);
        $this->assertSame(404, $stolen['status'], 'Cross-user reads must return 404, never leak content.');
    }
}
