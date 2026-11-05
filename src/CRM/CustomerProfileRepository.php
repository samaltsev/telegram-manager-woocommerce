<?php
declare(strict_types=1);

namespace WCTM\CRM;

if (!defined('ABSPATH')) {
    exit;
}

final class CustomerProfileRepository
{
    public function customers(int $limit = 50): array
    {
        return $this->search([
            'limit' => $limit,
            'offset' => 0,
        ]);
    }

    /**
     * @param array{search?:string,telegram?:string,limit?:int,offset?:int,orderby?:string,order?:string} $args
     * @return array<int, array<string, mixed>>
     */
    public function search(array $args = []): array
    {
        $users = get_users([
            'role__in' => ['customer', 'subscriber'],
            'number' => $this->limit($args),
            'offset' => max(0, (int) ($args['offset'] ?? 0)),
            'orderby' => $this->orderby((string) ($args['orderby'] ?? 'ID')),
            'order' => strtoupper((string) ($args['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC',
            'fields' => 'all',
        ] + $this->queryFilters($args));

        $rows = [];
        foreach ($users as $user) {
            $userId = (int) $user->ID;
            $rows[] = [
                'user_id' => $userId,
                'name' => trim((string) $user->display_name),
                'email' => (string) $user->user_email,
                'telegram' => $this->telegramForUser($userId),
                'orders_count' => function_exists('wc_get_customer_order_count') ? (int) wc_get_customer_order_count($userId) : 0,
                'total_spent' => function_exists('wc_get_customer_total_spent') ? (float) wc_get_customer_total_spent($userId) : 0.0,
                'last_order' => $this->lastOrderDate($userId),
            ];
        }

        return $rows;
    }

    /**
     * @param array{search?:string,telegram?:string} $args
     */
    public function count(array $args = []): int
    {
        $query = new \WP_User_Query([
            'role__in' => ['customer', 'subscriber'],
            'number' => 1,
            'fields' => 'ID',
            'count_total' => true,
        ] + $this->queryFilters($args));

        return (int) $query->get_total();
    }

    public function telegramForUser(int $userId): string
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wctm_customer_profile';
        $row = $wpdb->get_row($wpdb->prepare("SELECT username, chat_id FROM {$table} WHERE user_id = %d AND status = 'active' ORDER BY id DESC LIMIT 1", $userId), ARRAY_A);
        if (!$row) {
            return '';
        }
        $username = trim((string) ($row['username'] ?? ''));
        return $username !== '' ? '@' . ltrim($username, '@') : (string) ($row['chat_id'] ?? '');
    }

    private function lastOrderDate(int $userId): string
    {
        if (!function_exists('wc_get_orders')) {
            return '';
        }
        $orders = wc_get_orders([
            'customer_id' => $userId,
            'limit' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects',
        ]);
        if (empty($orders) || !is_object($orders[0])) {
            return '';
        }
        $date = $orders[0]->get_date_created();
        return $date ? $date->date_i18n(get_option('date_format')) : '';
    }

    /**
     * @param array{search?:string,telegram?:string} $args
     * @return array<string, mixed>
     */
    private function queryFilters(array $args): array
    {
        $filters = [];
        $search = trim((string) ($args['search'] ?? ''));
        if ($search !== '') {
            $filters['search'] = '*' . esc_attr($search) . '*';
            $filters['search_columns'] = ['user_login', 'user_email', 'display_name'];
        }

        $telegram = sanitize_key((string) ($args['telegram'] ?? ''));
        if ($telegram === 'linked' || $telegram === 'unlinked') {
            $linkedIds = $this->linkedUserIds();

            if ($telegram === 'linked') {
                $filters['include'] = $linkedIds ?: [0];
            } else {
                $filters['exclude'] = $linkedIds;
            }
        }

        return $filters;
    }

    /**
     * @return array<int, int>
     */
    private function linkedUserIds(): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wctm_customer_profile';
        $ids = $wpdb->get_col("SELECT DISTINCT user_id FROM {$table} WHERE user_id > 0 AND status = 'active'");

        return array_values(array_map('intval', $ids ?: []));
    }

    /**
     * @param array{limit?:int} $args
     */
    private function limit(array $args): int
    {
        return max(1, min(200, (int) ($args['limit'] ?? 50)));
    }

    private function orderby(string $orderby): string
    {
        return in_array($orderby, ['ID', 'display_name', 'user_email', 'user_registered'], true) ? $orderby : 'ID';
    }
}
