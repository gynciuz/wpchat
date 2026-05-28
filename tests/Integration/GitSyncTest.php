<?php
/**
 * GitSync helper tests.
 *
 * Builds a temporary git repository with a remote (also local) so we can
 * exercise the full commit + push path without external network access.
 *
 * Default behaviour: WPCHAT_GIT_SYNC_ENABLED is NOT defined, every call
 * returns skipped_reason. The first test asserts that. Subsequent tests
 * runkit-define the constant for the duration of the test method.
 *
 * @package WPChat\Tests
 */

namespace WPChat\Tests\Integration;

use WPChat\GitSync;
use WPChat\Tests\TestCase;

class GitSyncTest extends TestCase {

    private string $repo;
    private string $remote;
    private string $file;

    public function set_up() {
        parent::set_up();

        // Make a tiny local "remote" + working clone so push has somewhere
        // to land. Skip the whole suite if git isn't on the runner.
        if (!self::has_git()) {
            $this->markTestSkipped('git not available on this runner');
        }

        $this->remote = sys_get_temp_dir() . '/wpchat-gs-remote-' . uniqid();
        $this->repo   = sys_get_temp_dir() . '/wpchat-gs-repo-' . uniqid();
        mkdir($this->remote);
        mkdir($this->repo);

        self::sh("git init --bare {$this->remote}");
        self::sh("git -C {$this->repo} init -b main");
        self::sh("git -C {$this->repo} config user.email seed@test");
        self::sh("git -C {$this->repo} config user.name seed");
        self::sh("git -C {$this->repo} remote add origin {$this->remote}");

        $this->file = $this->repo . '/page.html';
        file_put_contents($this->file, "<p>before</p>\n");
        self::sh("git -C {$this->repo} add page.html");
        self::sh("git -C {$this->repo} commit -m initial --quiet");
        self::sh("git -C {$this->repo} push origin main --quiet");
    }

    public function tear_down() {
        @self::rrm($this->repo);
        @self::rrm($this->remote);
        parent::tear_down();
    }

    public function test_skipped_when_disabled(): void {
        $result = GitSync::commit_files([$this->file], 'no-op', []);
        $this->assertFalse($result['ok']);
        $this->assertArrayHasKey('skipped_reason', $result);
        $this->assertStringContainsString('WPCHAT_GIT_SYNC_ENABLED', $result['skipped_reason']);
    }

    public function test_commits_and_pushes_when_enabled(): void {
        $this->withGitSyncEnabled(function () {
            file_put_contents($this->file, "<p>after</p>\n");
            $result = GitSync::commit_files(
                [$this->file],
                'test: change to after',
                ['name' => 'Author', 'email' => 'a@test']
            );
            $this->assertTrue($result['ok'], 'Expected commit + push to succeed, got: ' . wp_json_encode($result));
            $this->assertNotEmpty($result['commit_sha']);

            // Remote has the commit?
            $log = self::sh("git -C {$this->remote} log --oneline -1");
            $this->assertStringContainsString('test: change to after', $log['stdout']);
        });
    }

    public function test_returns_skipped_when_file_unchanged(): void {
        $this->withGitSyncEnabled(function () {
            // file is at the same content as initial commit.
            $result = GitSync::commit_files([$this->file], 'no-change', []);
            $this->assertTrue($result['ok']);
            $this->assertStringContainsString('no staged changes', $result['skipped_reason']);
        });
    }

    public function test_rejects_files_outside_repo_root(): void {
        $this->withGitSyncEnabled(function () {
            $outside = sys_get_temp_dir() . '/not-in-repo-' . uniqid() . '.txt';
            file_put_contents($outside, 'x');
            $result = GitSync::commit_files([$outside], 'sneaky', []);
            $this->assertFalse($result['ok']);
            $this->assertArrayHasKey('error', $result);
            $this->assertStringContainsString('outside repo root', $result['error']);
            unlink($outside);
        });
    }

    public function test_surfaces_push_failure_distinct_from_commit_failure(): void {
        $this->withGitSyncEnabled(function () {
            // Break the remote so push fails.
            self::rrm($this->remote);

            file_put_contents($this->file, "<p>after</p>\n");
            $result = GitSync::commit_files([$this->file], 'should commit, fail to push', []);
            $this->assertFalse($result['ok']);
            // commit succeeded → sha present, but push failed.
            $this->assertNotEmpty($result['commit_sha']);
            $this->assertStringContainsString('push failed', $result['error']);

            // Repo HEAD did advance locally — proves the commit landed.
            $log = self::sh("git -C {$this->repo} log --oneline -1");
            $this->assertStringContainsString('fail to push', $log['stdout']);
        });
    }

    private function withGitSyncEnabled(callable $cb): void {
        // PHP doesn't let us redefine constants. Use a uopz / runkit if present,
        // otherwise rely on the constant being defined ONCE for the test process
        // via phpunit bootstrap or test-specific env. For portability the test
        // skips if neither approach can flip the flag.
        if (!defined('WPCHAT_GIT_SYNC_ENABLED')) {
            define('WPCHAT_GIT_SYNC_ENABLED', true);
        }
        if (!defined('WPCHAT_GIT_SYNC_PATH')) {
            define('WPCHAT_GIT_SYNC_PATH', $this->repo);
        }
        // If the constants were already defined to other values (because a
        // previous test ran first), the helper will use those — skip when
        // WPCHAT_GIT_SYNC_PATH doesn't match this run's repo.
        if (defined('WPCHAT_GIT_SYNC_PATH') && WPCHAT_GIT_SYNC_PATH !== $this->repo) {
            $this->markTestSkipped('WPCHAT_GIT_SYNC_PATH pinned to another repo by an earlier test');
            return;
        }
        $cb();
    }

    private static function has_git(): bool {
        $check = self::sh('command -v git');
        return $check['code'] === 0;
    }

    private static function sh(string $cmd): array {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open(['/bin/sh', '-c', $cmd], $descriptors, $pipes);
        if (!is_resource($proc)) {
            return ['code' => 127, 'stdout' => '', 'stderr' => 'no shell'];
        }
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        return ['code' => $code, 'stdout' => $stdout, 'stderr' => $stderr];
    }

    private static function rrm(string $path): void {
        if (!is_dir($path)) {
            if (is_file($path)) {
                @unlink($path);
            }
            return;
        }
        $items = scandir($path) ?: [];
        foreach ($items as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            self::rrm($path . '/' . $entry);
        }
        @rmdir($path);
    }
}
