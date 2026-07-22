<?php
/**
 * Git commit-on-write helper.
 *
 * Site backends that mutate tracked files (e.g. GE's chatadmin-gentleman-backend
 * editing html/pages/*.html) can call this helper after a successful write
 * to commit + push the change automatically. Without it, edits sit
 * uncommitted on prod and disappear on the next disaster-recovery reset.
 *
 * **Off by default.** Activates only when wp-config.php defines:
 *
 *   define('CHATADMIN_GIT_SYNC_ENABLED', true);
 *   define('CHATADMIN_GIT_SYNC_PATH', '/absolute/path/to/repo/root');  // optional, defaults to ABSPATH
 *
 * Prod-side prerequisites for the push to succeed:
 *   - `git` binary in PATH
 *   - Working git remote with push access (deploy key with write
 *     scope, or HTTPS remote with a PAT in the URL)
 *   - The repo's PHP-runtime user (typically www-data / web user) must
 *     have write access to the .git directory.
 *
 * The helper gracefully no-ops + returns a status when any of those
 * are missing — the calling write still succeeds, the operator sees
 * a "sync skipped" reason in the response note.
 *
 * @package ChatAdmin
 */

namespace ChatAdmin;

if (!defined('ABSPATH')) {
    exit;
}

class GitSync {

    const LOCK_FILE = 'chatadmin-git-sync.lock';

    /**
     * Commit + push the given files. Returns a status array.
     *
     * @param string[]    $absolute_paths Files to stage (must be under repo root).
     * @param string      $message        Commit message (will be sanitised).
     * @param array{name?: string, email?: string} $author Author info; falls back to git config.
     * @return array {ok: bool, skipped_reason?: string, commit_sha?: string, error?: string}
     */
    /**
     * True iff $path is the repo root itself or a file/dir strictly under it.
     * Uses a trailing-separator boundary so a sibling directory that merely
     * shares the root's string prefix (e.g. `/var/www/html-secrets` vs a root
     * of `/var/www/html`) is correctly rejected.
     */
    public static function path_is_within(string $path, string $root): bool {
        $root = rtrim($root, '/');
        return $path === $root || strpos($path, $root . '/') === 0;
    }

    public static function commit_files(array $absolute_paths, string $message, array $author = []): array {
        if (!self::is_enabled()) {
            return ['ok' => false, 'skipped_reason' => 'CHATADMIN_GIT_SYNC_ENABLED not set to true in wp-config.php'];
        }

        $repo_root = self::repo_root();
        if (!is_dir($repo_root . '/.git')) {
            return ['ok' => false, 'skipped_reason' => 'No .git directory at ' . $repo_root];
        }

        $absolute_paths = array_filter($absolute_paths, 'is_string');
        if (empty($absolute_paths)) {
            return ['ok' => false, 'skipped_reason' => 'no files'];
        }
        foreach ($absolute_paths as $p) {
            if (!self::path_is_within($p, $repo_root)) {
                return ['ok' => false, 'error' => 'File outside repo root: ' . $p];
            }
        }

        // Serialize concurrent writes with flock so two rapid edits don't
        // race on the same staging area / push.
        $lock_path = sys_get_temp_dir() . '/' . self::LOCK_FILE;
        $lock_fp = @fopen($lock_path, 'c');
        if (!$lock_fp) {
            return ['ok' => false, 'error' => 'Could not open lock file: ' . $lock_path];
        }
        if (!@flock($lock_fp, LOCK_EX)) {
            fclose($lock_fp);
            return ['ok' => false, 'error' => 'Could not acquire git-sync lock'];
        }

        try {
            $env = self::author_env($author);

            // git add — pass each file relative to repo_root to avoid path-traversal surprises.
            $rels = [];
            foreach ($absolute_paths as $p) {
                $rels[] = ltrim(substr($p, strlen($repo_root)), '/');
            }

            $add_status = self::run(['git', '-C', $repo_root, 'add', '--', ...$rels]);
            if ($add_status['code'] !== 0) {
                return ['ok' => false, 'error' => 'git add failed: ' . $add_status['stderr']];
            }

            // Empty stage? Nothing to commit (file content was already at this state).
            $diff_status = self::run(['git', '-C', $repo_root, 'diff', '--cached', '--quiet']);
            if ($diff_status['code'] === 0) {
                return ['ok' => true, 'skipped_reason' => 'no staged changes (file already at this content)'];
            }

            $commit_msg = self::sanitise_message($message);
            $commit_status = self::run(['git', '-C', $repo_root, 'commit', '-m', $commit_msg], $env);
            if ($commit_status['code'] !== 0) {
                return ['ok' => false, 'error' => 'git commit failed: ' . $commit_status['stderr']];
            }

            $sha_status = self::run(['git', '-C', $repo_root, 'rev-parse', 'HEAD']);
            $commit_sha = trim($sha_status['stdout'] ?? '');

            // Push. Try once; surface the actual stderr if it fails. The
            // calling backend's response should tell the user the file
            // committed locally but didn't sync — never silent.
            $push_status = self::run(['git', '-C', $repo_root, 'push', 'origin', 'HEAD']);
            if ($push_status['code'] !== 0) {
                return [
                    'ok'         => false,
                    'commit_sha' => $commit_sha,
                    'error'      => 'git push failed: ' . trim($push_status['stderr']) . ' — commit is local-only',
                ];
            }

            return ['ok' => true, 'commit_sha' => $commit_sha];
        } finally {
            flock($lock_fp, LOCK_UN);
            fclose($lock_fp);
        }
    }

    public static function is_enabled(): bool {
        return defined('CHATADMIN_GIT_SYNC_ENABLED') && CHATADMIN_GIT_SYNC_ENABLED === true;
    }

    public static function repo_root(): string {
        if (defined('CHATADMIN_GIT_SYNC_PATH') && is_string(CHATADMIN_GIT_SYNC_PATH)) {
            return rtrim(CHATADMIN_GIT_SYNC_PATH, '/');
        }
        return rtrim(ABSPATH, '/');
    }

    /** Build author env vars; falls back to git's own config when missing. */
    private static function author_env(array $author): array {
        $env = [];
        if (!empty($author['name'])) {
            $env['GIT_AUTHOR_NAME']    = (string) $author['name'];
            $env['GIT_COMMITTER_NAME'] = (string) $author['name'];
        }
        if (!empty($author['email'])) {
            $env['GIT_AUTHOR_EMAIL']    = (string) $author['email'];
            $env['GIT_COMMITTER_EMAIL'] = (string) $author['email'];
        }
        return $env;
    }

    /** Strip control chars + cap length; commit messages should be plain. */
    private static function sanitise_message(string $message): string {
        $clean = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $message) ?? $message;
        $clean = trim($clean);
        if ($clean === '') {
            $clean = 'ChatAdmin: content edit';
        }
        if (mb_strlen($clean, 'UTF-8') > 4000) {
            $clean = mb_substr($clean, 0, 3997, 'UTF-8') . '…';
        }
        return $clean;
    }

    /**
     * Run a command without going through a shell — proc_open with an
     * argv array so we don't have to escape anything. Returns code +
     * captured stdout / stderr.
     */
    private static function run(array $argv, array $extra_env = []): array {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $env = $_ENV ?: getenv();
        if (!is_array($env)) {
            $env = [];
        }
        foreach ($extra_env as $k => $v) {
            $env[$k] = $v;
        }
        // Guarantee HOME so git can find global config / ssh known_hosts.
        if (empty($env['HOME']) && function_exists('posix_getpwuid')) {
            $info = @posix_getpwuid(posix_geteuid());
            if (!empty($info['dir'])) {
                $env['HOME'] = $info['dir'];
            }
        }

        $proc = @proc_open($argv, $descriptors, $pipes, null, $env);
        if (!is_resource($proc)) {
            return ['code' => 127, 'stdout' => '', 'stderr' => 'Could not exec: ' . ($argv[0] ?? '?')];
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        return ['code' => $code, 'stdout' => $stdout, 'stderr' => $stderr];
    }
}
