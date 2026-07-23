<?php
/**
 * SCENARIO — site backend handles `field='photo'` for a team_member-shaped
 * kind: preview returns old + new image URLs, apply rewrites <img src> in
 * fixture HTML files, missing confirmation rejects.
 *
 * We register a fake backend that mirrors a custom backend's shape (parses
 * team__member blocks, accepts field=role and field=photo) but operates
 * on tmp fixture files instead of the real static HTML — so the test
 * is reproducible without that backend installed.
 *
 * @package ChatAdmin\Tests
 */

namespace ChatAdmin\Tests\Scenarios;

use ChatAdmin\ContentBackend;
use ChatAdmin\ContentConfirmation;
use ChatAdmin\Tools;
use ChatAdmin\Tests\TestCase;

class PhotoReplaceTest extends TestCase {

    private string $tmpDir;
    private string $page1;
    private string $page2;

    public function set_up() {
        parent::set_up();
        $this->tmpDir = sys_get_temp_dir() . '/chatadmin-photo-' . uniqid();
        mkdir($this->tmpDir);
        $this->page1 = $this->tmpDir . '/index.html';
        $this->page2 = $this->tmpDir . '/team.html';
        $block = '<div class="team__member"><img src="/old/nesar.jpg" alt="Nesar Azheer" loading="lazy"><div class="team__member-info"><h3 class="team__member-name">Nesar Azheer</h3><p class="team__member-role">Meistras</p></div></div>';
        file_put_contents($this->page1, "<html><body>$block</body></html>");
        file_put_contents($this->page2, "<html><body>$block</body></html>");
    }

    public function tear_down() {
        @unlink($this->page1);
        @unlink($this->page2);
        @rmdir($this->tmpDir);
        parent::tear_down();
    }

    public function test_preview_photo_returns_old_and_new_urls_without_writing(): void {
        $this->registerBackend();
        $disk_before = file_get_contents($this->page1);

        $result = Tools::preview_content_change([
            'target' => ['kind' => 'photo_test_member', 'name' => 'Nesar'],
            'field'  => 'photo',
            'value'  => 'https://example.com/new/nesar-v2.jpg',
        ]);

        $this->assertArrayHasKey('matches', $result);
        $this->assertNotEmpty($result['matches']);
        $first = $result['matches'][0];
        $this->assertSame('photo', $first['field']);
        $this->assertSame('/old/nesar.jpg', $first['old_value']);
        $this->assertSame('https://example.com/new/nesar-v2.jpg', $first['new_value']);

        $this->assertSame($disk_before, file_get_contents($this->page1), 'Preview must not write.');
    }

    public function test_apply_photo_rewrites_img_src_in_all_pages(): void {
        $this->registerBackend();

        $result = Tools::apply_content_change([
            'target'       => ['kind' => 'photo_test_member', 'name' => 'Nesar'],
            'field'        => 'photo',
            'value'        => 'https://example.com/new/nesar-v2.jpg',
            'confirmation' => 'taip',
        ]);

        $this->assertTrue($result['ok'], 'apply should succeed: ' . wp_json_encode($result));
        foreach ([$this->page1, $this->page2] as $p) {
            $html = file_get_contents($p);
            $this->assertStringContainsString('src="https://example.com/new/nesar-v2.jpg"', $html);
            $this->assertStringNotContainsString('/old/nesar.jpg', $html);
        }
    }

    public function test_apply_photo_rejects_without_confirmation(): void {
        $this->registerBackend();
        $disk_before = file_get_contents($this->page1);

        $result = Tools::apply_content_change([
            'target'       => ['kind' => 'photo_test_member', 'name' => 'Nesar'],
            'field'        => 'photo',
            'value'        => 'https://example.com/new/nesar-v2.jpg',
            'confirmation' => 'maybe later',
        ]);

        $this->assertFalse($result['ok'] ?? true);
        $this->assertSame($disk_before, file_get_contents($this->page1), 'No write without confirmation.');
    }

    private function registerBackend(): void {
        $page1 = $this->page1;
        $page2 = $this->page2;
        $backend = new class($page1, $page2) implements ContentBackend {
            public function __construct(private string $p1, private string $p2) {}
            public function handled_kinds(): array { return ['photo_test_member']; }
            public function describe_kinds(): array {
                return ['photo_test_member' => ['description' => 'fixture', 'fields' => ['photo']]];
            }
            public function list_items(string $kind, array $args = []): array { return ['items' => []]; }
            public function preview(array $target, string $field, $value): array {
                $matches = [];
                foreach ([$this->p1, $this->p2] as $path) {
                    if (preg_match('/<img[^>]*src="([^"]+)"[^>]*alt="Nesar[^"]*"/i', file_get_contents($path), $m)) {
                        $matches[] = ['field' => 'photo', 'old_value' => $m[1], 'new_value' => $value, 'location' => basename($path)];
                    }
                }
                return ['matches' => $matches];
            }
            public function apply(array $target, string $field, $value, string $confirmation): array {
                if (!ContentConfirmation::is_confirmed($confirmation)) {
                    return ['ok' => false, 'error' => 'confirmation required'];
                }
                $changed = 0;
                foreach ([$this->p1, $this->p2] as $path) {
                    $html = file_get_contents($path);
                    $next = preg_replace('/(<img[^>]*?)src="[^"]*"/i', '$1src="' . htmlspecialchars($value, ENT_QUOTES) . '"', $html, 1);
                    if ($next !== null && $next !== $html) {
                        file_put_contents($path, $next);
                        $changed++;
                    }
                }
                return ['ok' => $changed > 0, 'changes' => $changed];
            }
        };
        add_filter('chatadmin_content_backends', static function ($backends) use ($backend) {
            $backends[] = $backend;
            return $backends;
        });
    }
}
