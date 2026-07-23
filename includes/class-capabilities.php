<?php
/**
 * Capability provisioning for ChatAdmin operators.
 *
 * ChatAdmin's whole promise is that a non-technical operator (a shop manager,
 * a salon owner) can edit the site by chatting — without ever touching the
 * WordPress admin. But WordPress gates content edits behind granular caps
 * (`edit_others_pages`, `edit_published_pages`, `publish_pages`, …). A custom
 * "orders + chat" role that only carries the WooCommerce caps can open the
 * chat yet still hit "your role doesn't permit editing this" the moment it
 * tries to fix a typo on a published page.
 *
 * So on activation — and after every auto-update (the activation hook does NOT
 * fire on update) — we top up the roles that can already use ChatAdmin
 * (anything holding `manage_woocommerce` or `edit_shop_orders`) with the
 * content-editing caps the assistant needs. We only ADD caps, never remove
 * them, and we never touch `manage_options` (site administration stays with
 * real admins). Roles without WooCommerce caps (subscriber, author, plain
 * editor without shop access) are left exactly as they were.
 *
 * @package ChatAdmin
 */

namespace ChatAdmin;

if (!defined('ABSPATH')) {
    exit;
}

class Capabilities {

    /**
     * A role qualifies as a ChatAdmin operator if it can reach the chat, i.e.
     * it holds either of the WooCommerce management caps the chat route gates
     * on (see Rest / Admin).
     *
     * @var string[]
     */
    const OPERATOR_MARKERS = ['manage_woocommerce', 'edit_shop_orders'];

    /**
     * Content-editing caps every ChatAdmin operator role should hold so the
     * assistant can edit posts and pages (including ones authored by someone
     * else / already published), manage categories and tags, and upload the
     * images referenced in chat. Deliberately does NOT include delete_* or
     * any `manage_options`-level cap.
     *
     * @var string[]
     */
    const CONTENT_CAPS = [
        // Posts
        'edit_posts',
        'edit_others_posts',
        'edit_published_posts',
        'edit_private_posts',
        'publish_posts',
        'read_private_posts',
        // Pages
        'edit_pages',
        'edit_others_pages',
        'edit_published_pages',
        'edit_private_pages',
        'publish_pages',
        'read_private_pages',
        // Taxonomies + media
        'manage_categories',
        'upload_files',
    ];

    /**
     * Grant the content caps to every role that can use ChatAdmin. Idempotent —
     * WP_Role::add_cap() is a no-op when the cap is already present, so this is
     * safe to run on every activation and upgrade.
     *
     * The built-in `administrator` role is always included: a healthy admin
     * already has these caps (no-op), but if a botched update left the admin
     * unable to edit pages through the chat ("missing required rights"), this
     * restores the content caps. We never grant `manage_options` here — that
     * stays with whatever the site's roles already define.
     */
    public static function provision(): void {
        $roles = wp_roles();
        if (!$roles instanceof \WP_Roles) {
            return;
        }

        foreach ($roles->role_objects as $slug => $role) {
            if (!$role instanceof \WP_Role) {
                continue;
            }
            if ($slug !== 'administrator' && !self::is_operator_role($role)) {
                continue;
            }
            foreach (self::CONTENT_CAPS as $cap) {
                if (!$role->has_cap($cap)) {
                    $role->add_cap($cap);
                }
            }
        }
    }

    /** True when the role already holds one of the ChatAdmin access caps. */
    private static function is_operator_role(\WP_Role $role): bool {
        foreach (self::OPERATOR_MARKERS as $marker) {
            if ($role->has_cap($marker)) {
                return true;
            }
        }
        return false;
    }
}
