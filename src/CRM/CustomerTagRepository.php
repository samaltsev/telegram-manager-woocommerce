<?php
declare(strict_types=1);

namespace WCTM\CRM;

if (!defined('ABSPATH')) {
    exit;
}

final class CustomerTagRepository
{
    private bool $indexesChecked = false;

    public function all(): array
    {
        global $wpdb;
        $this->ensureUniqueIndexes();
        $table = $wpdb->prefix . 'wctm_customer_tags';
        return $wpdb->get_results("SELECT * FROM {$table} ORDER BY name ASC", ARRAY_A) ?: [];
    }

    public function ensure(string $name): int
    {
        global $wpdb;
        $this->ensureUniqueIndexes();
        $name = trim(sanitize_text_field($name));
        if ($name === '') {
            return 0;
        }
        $slug = sanitize_title($name);
        $table = $wpdb->prefix . 'wctm_customer_tags';
        $id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s", $slug));
        if ($id > 0) {
            return $id;
        }
        $wpdb->insert($table, [
            'name' => $name,
            'slug' => $slug,
            'created_at' => current_time('mysql'),
        ]);
        return (int) $wpdb->insert_id;
    }

    public function remove(int $tagId): bool
    {
        global $wpdb;
        if ($tagId <= 0) {
            return false;
        }

        $wpdb->delete($wpdb->prefix . 'wctm_customer_tag_links', ['tag_id' => $tagId], ['%d']);
        $deleted = $wpdb->delete($wpdb->prefix . 'wctm_customer_tags', ['id' => $tagId], ['%d']);

        return $deleted !== false;
    }

    public function assign(int $userId, int $tagId): void
    {
        global $wpdb;
        $this->ensureUniqueIndexes();
        if ($userId <= 0 || $tagId <= 0) {
            return;
        }
        $wpdb->replace($wpdb->prefix . 'wctm_customer_tag_links', [
            'user_id' => $userId,
            'tag_id' => $tagId,
            'created_at' => current_time('mysql'),
        ]);
    }

    public function unassign(int $userId, int $tagId): bool
    {
        global $wpdb;
        if ($userId <= 0 || $tagId <= 0) {
            return false;
        }

        $deleted = $wpdb->delete(
            $wpdb->prefix . 'wctm_customer_tag_links',
            [
                'user_id' => $userId,
                'tag_id' => $tagId,
            ],
            ['%d', '%d']
        );

        return $deleted !== false;
    }

    /**
     * @param array<int, int> $userIds
     * @param array<int, int> $tagIds
     */
    public function bulkAssign(array $userIds, array $tagIds): int
    {
        $assigned = 0;
        $userIds = array_values(array_unique(array_filter(array_map('absint', $userIds))));
        $tagIds = array_values(array_unique(array_filter(array_map('absint', $tagIds))));

        foreach ($userIds as $userId) {
            foreach ($tagIds as $tagId) {
                $this->assign($userId, $tagId);
                $assigned++;
            }
        }

        return $assigned;
    }

    public function tagsForUser(int $userId): array
    {
        global $wpdb;
        $tags = $wpdb->prefix . 'wctm_customer_tags';
        $links = $wpdb->prefix . 'wctm_customer_tag_links';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT t.* FROM {$tags} t INNER JOIN {$links} l ON l.tag_id = t.id WHERE l.user_id = %d ORDER BY t.name ASC",
            $userId
        ), ARRAY_A) ?: [];
    }

    private function ensureUniqueIndexes(): void
    {
        if ($this->indexesChecked) {
            return;
        }

        global $wpdb;
        $tags = $wpdb->prefix . 'wctm_customer_tags';
        $links = $wpdb->prefix . 'wctm_customer_tag_links';

        $wpdb->query("DELETE l1 FROM {$links} l1 INNER JOIN {$links} l2 ON l1.user_id = l2.user_id AND l1.tag_id = l2.tag_id AND l1.id > l2.id");
        $this->addUniqueIndexIfMissing($tags, 'slug', 'slug');
        $this->addUniqueIndexIfMissing($links, 'user_tag', 'user_id, tag_id');

        $this->indexesChecked = true;
    }

    private function addUniqueIndexIfMissing(string $table, string $indexName, string $columns): void
    {
        global $wpdb;

        $exists = $wpdb->get_var($wpdb->prepare(
            "SHOW INDEX FROM {$table} WHERE Key_name = %s",
            $indexName
        ));

        if ($exists) {
            return;
        }

        $wpdb->query("ALTER TABLE {$table} ADD UNIQUE KEY {$indexName} ({$columns})");
    }
}
