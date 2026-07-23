<?php
/**
 * Plugin Name: ChatAdmin Example — Custom Content Backend
 * Description: Reference site plugin showing how to expose non-standard content
 *              (here: "team members" stored outside normal posts) to ChatAdmin.
 *              Copy this into a site-specific plugin and replace the storage
 *              read/write with wherever YOUR content actually lives (a page's
 *              static HTML, a page-builder blob, an options record, a file, an
 *              external API — anything).
 * License: MIT
 *
 * How it works
 * ------------
 * ChatAdmin's core stays universal. A site registers a ContentBackend via the
 * `chatadmin_content_backends` filter; ChatAdmin then AUTO-DETECTS it:
 *   - the backend's kinds appear in the assistant's system prompt (describe_kinds),
 *   - edits route through the standard preview → confirm → apply flow,
 *   - access is gated by the WP capability required_cap() returns,
 *   - and (optional) search() makes the content findable via the find_text tool.
 * No change to the ChatAdmin plugin is needed.
 *
 * This demo stores team members in a single wp_option so it runs as-is; the
 * TODO markers show exactly where to swap in your real storage.
 *
 * @package ChatAdmin\Examples
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', function () {
    // ChatAdmin loads its interfaces on plugins_loaded; guard so this file is
    // inert if ChatAdmin isn't active.
    if (!interface_exists('\ChatAdmin\ContentBackend')) {
        return;
    }

    add_filter('chatadmin_content_backends', function (array $backends) {
        $backends[] = new Example_Team_Member_Backend();
        return $backends;
    });
});

if (!interface_exists('\ChatAdmin\ContentBackend')) {
    return;
}

/**
 * Exposes a "team_member" kind. A team member has a name, a role/position, and
 * a photo (attachment id). Replace the read_all()/write_all() storage with your
 * real source.
 */
class Example_Team_Member_Backend implements \ChatAdmin\ContentBackend {

    const KIND   = 'team_member';
    const OPTION = 'example_team_members'; // TODO: replace with your real storage

    /** Which kind slugs this backend claims. */
    public function handled_kinds(): array {
        return [self::KIND];
    }

    /**
     * Shown to the assistant so it knows this content exists and what it can
     * change. `fields` are the editable field names apply() accepts.
     */
    public function describe_kinds(): array {
        return [
            self::KIND => [
                'description' => 'A team member / staff profile shown on the site (name, role, photo). '
                    . 'Target shape: {kind: "' . self::KIND . '", id: "<slug>"}. '
                    . 'List them with list_content_blocks("' . self::KIND . '").',
                'fields'      => ['name', 'role', 'photo'],
            ],
        ];
    }

    /** WP capability the current user needs to edit this kind. */
    public function required_cap(string $kind, array $target = []): string {
        return 'edit_pages'; // TODO: match how sensitive your content is
    }

    /** Enumerate items so the assistant (and the user) can pick one. */
    public function list_items(string $kind, array $args = []): array {
        $items  = [];
        $search = strtolower((string) ($args['search'] ?? ''));
        foreach ($this->read_all() as $slug => $m) {
            if ($search !== '' && strpos(strtolower($m['name'] . ' ' . $m['role']), $search) === false) {
                continue;
            }
            $items[] = ['id' => $slug, 'name' => $m['name'], 'role' => $m['role'], 'photo' => $m['photo'] ?? 0];
        }
        return ['kind' => $kind, 'count' => count($items), 'items' => $items];
    }

    /** Read-only diff: what WOULD change. */
    public function preview(array $target, string $field, $value): array {
        $slug = (string) ($target['id'] ?? '');
        $all  = $this->read_all();
        if ($slug === '' || !isset($all[$slug])) {
            return ['error' => 'Unknown team member. Call list_content_blocks("' . self::KIND . '") first.'];
        }
        if (!in_array($field, ['name', 'role', 'photo'], true)) {
            return ['error' => "Unsupported field: $field"];
        }
        return ['matches' => [[
            'location'  => "team_member:{$slug}",
            'field'     => $field,
            'old_value' => $all[$slug][$field] ?? '',
            'new_value' => $value,
        ]]];
    }

    /** Apply the change AFTER ChatAdmin has confirmed the phrase. */
    public function apply(array $target, string $field, $value, string $confirmation): array {
        // Reuse ChatAdmin's multilingual confirmation whitelist.
        if (!\ChatAdmin\ContentConfirmation::is_confirmed($confirmation)) {
            return ['error' => 'Confirmation required (yes / taip / да / ok / …).'];
        }
        $slug = (string) ($target['id'] ?? '');
        $all  = $this->read_all();
        if ($slug === '' || !isset($all[$slug])) {
            return ['error' => 'Unknown team member.'];
        }
        if (!in_array($field, ['name', 'role', 'photo'], true)) {
            return ['error' => "Unsupported field: $field"];
        }
        $all[$slug][$field] = $field === 'photo' ? (int) $value : (string) $value;
        $this->write_all($all);
        return ['ok' => true, 'id' => $slug, 'field' => $field];
    }

    /**
     * OPTIONAL — lets the find_text tool locate your content too. Return
     * find_text-shaped hits. Scope to what the user may edit.
     */
    public function search(string $query): array {
        $hits = [];
        $q    = strtolower($query);
        $can  = current_user_can($this->required_cap(self::KIND));
        foreach ($this->read_all() as $slug => $m) {
            foreach (['name', 'role'] as $field) {
                if (strpos(strtolower((string) ($m[$field] ?? '')), $q) !== false) {
                    $hits[] = [
                        'where'    => "team member “{$m['name']}” ({$field})",
                        'kind'     => self::KIND,
                        'target'   => ['kind' => self::KIND, 'id' => $slug],
                        'fields'   => [$field],
                        'editable' => (bool) $can,
                    ];
                }
            }
        }
        return $hits;
    }

    // --- storage: REPLACE these two with your real source --------------------

    /** @return array<string,array{name:string,role:string,photo:int}> */
    private function read_all(): array {
        // TODO: read from GE's static HTML page / page-builder data / file / API.
        $stored = get_option(self::OPTION, []);
        return is_array($stored) ? $stored : [];
    }

    private function write_all(array $all): void {
        // TODO: write back to the same place — and, if it's static HTML, also
        // regenerate/flush that HTML (and purge any page cache) here.
        update_option(self::OPTION, $all, false);
    }
}
