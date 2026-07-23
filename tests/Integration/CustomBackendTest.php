<?php
/**
 * A site-registered custom content backend must be AUTO-DETECTED by the core
 * plugin: its kind shows up in the router + the assistant's system prompt, and
 * (via the optional search()) its content is found by find_text — with no
 * change to ChatAdmin itself. This is the universal-plugin extension contract.
 *
 * @package ChatAdmin\Tests
 */

namespace ChatAdmin\Tests\Integration;

use ChatAdmin\ContentBackend;
use ChatAdmin\ContentRouter;
use ChatAdmin\Tools;
use ChatAdmin\Tests\TestCase;

class CustomBackendTest extends TestCase {

    /** @var callable|null */
    private $cb = null;

    private function register_stub(): void {
        $this->cb = static function (array $backends): array {
            $backends[] = new Stub_Team_Backend();
            return $backends;
        };
        \add_filter('chatadmin_content_backends', $this->cb);
    }

    public function tear_down(): void {
        if ($this->cb) {
            \remove_filter('chatadmin_content_backends', $this->cb);
            $this->cb = null;
        }
        parent::tear_down();
    }

    public function test_registered_backend_kind_is_detected_by_router(): void {
        $this->register_stub();
        $this->assertContains('stub_team', ContentRouter::all_kinds());
        $this->assertArrayHasKey('stub_team', ContentRouter::all_descriptions());
    }

    public function test_registered_backend_kind_appears_in_system_prompt(): void {
        $this->register_stub();
        $this->mockAnthropic->enqueueEndTurn('ok');
        $this->postChat('hi');

        $system = $this->mockAnthropic->lastRequest()['system'] ?? '';
        $this->assertStringContainsString('stub_team', $system, 'A registered backend kind must be advertised to the assistant.');
    }

    public function test_find_text_includes_backend_search_hits(): void {
        $this->register_stub();
        $res = Tools::find_text(['query' => 'barzdaX']);

        $kinds = array_column($res['hits'], 'kind');
        $this->assertContains('stub_team', $kinds, 'find_text must include hits from a backend that implements search().');
    }
}

/**
 * Minimal backend for the test — one item whose role contains "barzdaX".
 */
class Stub_Team_Backend implements ContentBackend {

    public function handled_kinds(): array {
        return ['stub_team'];
    }

    public function describe_kinds(): array {
        return ['stub_team' => ['description' => 'A stub team member.', 'fields' => ['role']]];
    }

    public function required_cap(string $kind, array $target = []): string {
        return 'edit_posts';
    }

    public function list_items(string $kind, array $args = []): array {
        return ['kind' => $kind, 'count' => 1, 'items' => [['id' => 'a', 'role' => 'barzdaX']]];
    }

    public function preview(array $target, string $field, $value): array {
        return ['matches' => [['location' => 'stub_team:a', 'field' => $field, 'old_value' => 'barzdaX', 'new_value' => $value]]];
    }

    public function apply(array $target, string $field, $value, string $confirmation): array {
        return ['ok' => true];
    }

    public function search(string $query): array {
        if (stripos('barzdaX', $query) === false) {
            return [];
        }
        return [[
            'where'    => 'stub team member “a” (role)',
            'kind'     => 'stub_team',
            'target'   => ['kind' => 'stub_team', 'id' => 'a'],
            'fields'   => ['role'],
            'editable' => \current_user_can('edit_posts'),
        ]];
    }
}
