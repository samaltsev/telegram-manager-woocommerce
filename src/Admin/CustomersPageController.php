<?php
declare(strict_types=1);

namespace WCTM\Admin;

use WCTM\CRM\CustomerProfileRepository;
use WCTM\CRM\CustomerTagRepository;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('\WP_List_Table') && defined('ABSPATH')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class CustomersPageController
{
    public function __construct(
        private CustomerProfileRepository $customers,
        private CustomerTagRepository $tags
    ) {
    }

    public function render(): void
    {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied.', 'telegram-manager-woocommerce'));
        }

        $table = new CustomersListTable($this->customers, $this->tags);
        $table->prepare_items();

        echo '<div class="wrap wctm-admin">';
        echo '<h1>Telegram CRM</h1>';
        echo '<p>Customer profiles with WooCommerce revenue, Telegram binding and tags.</p>';
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="' . esc_attr((string) ($_GET['page'] ?? 'wctm-customers')) . '">';
        $table->search_box(__('Search customers', 'telegram-manager-woocommerce'), 'wctm_customer_search');
        echo '<div class="tablenav top"><div class="alignleft actions">';
        echo '<select name="telegram">';
        foreach ([
            '' => __('All Telegram states', 'telegram-manager-woocommerce'),
            'linked' => __('Telegram linked', 'telegram-manager-woocommerce'),
            'unlinked' => __('Telegram unlinked', 'telegram-manager-woocommerce'),
        ] as $value => $label) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($value),
                selected($table->telegramFilter(), $value, false),
                esc_html($label)
            );
        }
        echo '</select> ';
        submit_button(__('Filter', 'telegram-manager-woocommerce'), 'secondary', 'filter_action', false);
        echo '</div></div>';
        $table->display();
        echo '</form>';
        echo '</div>';
    }
}

final class CustomersListTable extends \WP_List_Table
{
    private CustomerProfileRepository $customers;
    private CustomerTagRepository $tags;
    private string $telegram = '';

    public function __construct(CustomerProfileRepository $customers, CustomerTagRepository $tags)
    {
        parent::__construct([
            'singular' => 'customer',
            'plural' => 'customers',
            'ajax' => false,
        ]);

        $this->customers = $customers;
        $this->tags = $tags;
    }

    public function telegramFilter(): string
    {
        return $this->telegram;
    }

    public function get_columns(): array
    {
        return [
            'customer' => __('Customer', 'telegram-manager-woocommerce'),
            'email' => __('Email', 'telegram-manager-woocommerce'),
            'telegram' => __('Telegram', 'telegram-manager-woocommerce'),
            'orders_count' => __('Orders', 'telegram-manager-woocommerce'),
            'total_spent' => __('Total spent', 'telegram-manager-woocommerce'),
            'last_order' => __('Last order', 'telegram-manager-woocommerce'),
            'tags' => __('Tags', 'telegram-manager-woocommerce'),
        ];
    }

    protected function get_sortable_columns(): array
    {
        return [
            'customer' => ['display_name', false],
            'email' => ['user_email', false],
        ];
    }

    public function prepare_items(): void
    {
        $perPage = $this->get_items_per_page('wctm_customers_per_page', 20);
        $page = max(1, (int) ($this->get_pagenum() ?: 1));
        $search = sanitize_text_field((string) wp_unslash($_REQUEST['s'] ?? ''));
        $this->telegram = sanitize_key((string) wp_unslash($_REQUEST['telegram'] ?? ''));

        if (!in_array($this->telegram, ['', 'linked', 'unlinked'], true)) {
            $this->telegram = '';
        }

        $args = [
            'search' => $search,
            'telegram' => $this->telegram,
            'limit' => $perPage,
            'offset' => ($page - 1) * $perPage,
            'orderby' => sanitize_key((string) wp_unslash($_REQUEST['orderby'] ?? 'ID')),
            'order' => sanitize_key((string) wp_unslash($_REQUEST['order'] ?? 'DESC')),
        ];

        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
        $this->items = $this->customers->search($args);
        $totalItems = $this->customers->count($args);

        $this->set_pagination_args([
            'total_items' => $totalItems,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($totalItems / $perPage),
        ]);
    }

    protected function column_customer(array $item): string
    {
        $userId = (int) ($item['user_id'] ?? 0);
        $name = (string) ($item['name'] ?? '');

        return '<strong>#' . esc_html((string) $userId) . '</strong> ' . esc_html($name !== '' ? $name : __('Unnamed customer', 'telegram-manager-woocommerce'));
    }

    protected function column_total_spent(array $item): string
    {
        $spent = (float) ($item['total_spent'] ?? 0);
        return esc_html(function_exists('wc_price') ? wp_strip_all_tags(wc_price($spent)) : (string) $spent);
    }

    protected function column_tags(array $item): string
    {
        $userId = (int) ($item['user_id'] ?? 0);
        $tagNames = array_map(static fn($tag): string => (string) ($tag['name'] ?? ''), $this->tags->tagsForUser($userId));
        $tagNames = array_filter($tagNames);

        return $tagNames ? esc_html(implode(', ', $tagNames)) : '<span class="description">-</span>';
    }

    protected function column_default($item, $column_name): string
    {
        return esc_html((string) ($item[$column_name] ?? ''));
    }

    public function no_items(): void
    {
        esc_html_e('No customers found.', 'telegram-manager-woocommerce');
    }
}
