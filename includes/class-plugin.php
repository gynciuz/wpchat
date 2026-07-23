<?php
/**
 * ChatAdmin plugin bootstrap.
 *
 * @package ChatAdmin
 */

namespace ChatAdmin;

if (!defined('ABSPATH')) {
    exit;
}

final class Plugin {

    /** Option that records the plugin version whose migrations last ran. */
    const VERSION_OPTION = 'chatadmin_installed_version';

    private static ?Plugin $instance = null;

    public static function instance(): Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function boot(): void {
        // WordPress does NOT fire the activation hook when a plugin is updated
        // (via the auto-updater or a manual "Update Now") — only on a fresh
        // activate. So run pending migrations here too, guarded by a stored
        // version so it's a cheap single option read on every normal request.
        // This is what keeps settings, the messages table, and operator caps
        // intact across an auto-update instead of leaving a half-migrated
        // install (the reported "update wiped things / disabled the plugin").
        // boot() already runs on plugins_loaded, so call directly rather than
        // re-hooking the same action (a callback added to the running hook at a
        // lower priority would be skipped for this request).
        $this->maybe_upgrade();

        new Admin();
        new Settings();
        new Rest();
        new Upload();
        new Onboarding();
        new Seo();
        new Frontend();
    }

    /**
     * Run install/upgrade migrations once per version. Idempotent and safe to
     * call on every request — it no-ops when the stored version already matches
     * the running code.
     */
    public function maybe_upgrade(): void {
        $installed = (string) get_option(self::VERSION_OPTION, '');
        if ($installed === CHATADMIN_VERSION) {
            return;
        }
        self::run_migrations();
        update_option(self::VERSION_OPTION, CHATADMIN_VERSION);
    }

    public static function activate(): void {
        self::run_migrations();
        update_option(self::VERSION_OPTION, CHATADMIN_VERSION);
    }

    /**
     * The actual install/upgrade steps. Every step is idempotent so this can
     * run from both the activation hook and the version-gated upgrade path.
     */
    public static function run_migrations(): void {
        if (!get_option('chatadmin_settings')) {
            add_option('chatadmin_settings', [
                'llm_provider'      => 'anthropic',
                'anthropic_api_key' => '',
                'model'             => 'claude-sonnet-4-6',
            ]);
        }
        History::migrate();
        Capabilities::provision();
    }

    public static function deactivate(): void {
        // Intentionally leave chatadmin_settings (and the operator caps) in
        // place — the user may reactivate later and expects their key, model,
        // and permissions to still be there.
    }
}
