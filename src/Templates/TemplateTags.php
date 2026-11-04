<?php
declare(strict_types=1);

namespace WCTM\Templates;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateTags
{
    private static function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        if ($phone === '') {
            return '';
        }

        $phone = preg_replace('/[^\d\+]/', '', $phone) ?: '';
        if (str_starts_with($phone, '00')) {
            $phone = '+' . substr($phone, 2);
        }

        return $phone;
    }

    private static function countryName(string $countryCode): string
    {
        $countryCode = strtoupper(trim($countryCode));
        if ($countryCode === '' || !function_exists('WC')) {
            return $countryCode;
        }

        $countries = WC()->countries;
        if (!$countries || !method_exists($countries, 'get_countries')) {
            return $countryCode;
        }

        $map = $countries->get_countries();

        return isset($map[$countryCode]) ? (string) $map[$countryCode] : $countryCode;
    }

    /**
     * @param array<int, string> $parts
     */
    private static function buildAddressLine(array $parts): string
    {
        $parts = array_values(array_filter(array_map(static function ($value): string {
            return trim((string) $value);
        }, $parts), static fn (string $value): bool => $value !== ''));

        return $parts !== [] ? implode(', ', $parts) : '-';
    }

    private static function getProductImage($product): string
    {
        $url = $product && $product->get_image_id() ? wp_get_attachment_url($product->get_image_id()) : '';

        return esc_url($url ?: 'https://example.com/default-product.jpg');
    }


    private static function adminOrderEditUrl(\WC_Order $order): string
    {
        $orderId = (int) $order->get_id();

        if ($orderId <= 0) {
            return admin_url('admin.php?page=wc-orders');
        }

        if (
            class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')
            && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
        ) {
            $url = admin_url('admin.php?page=wc-orders&action=edit&id=' . $orderId);
        } else {
            $url = admin_url('post.php?post=' . $orderId . '&action=edit');
        }

        $url = html_entity_decode((string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url = str_replace(['&#038;', '&amp;'], '&', $url);

        return esc_url_raw($url);
    }

    public static function forOrder(\WC_Order $order): array
    {
        $customerName = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        $billingPhone = (string) $order->get_billing_phone();
        $billingPhoneNormalized = self::normalizePhone($billingPhone);
        $billingEmail = (string) $order->get_billing_email();

        $billingAddress = self::buildAddressLine([
            (string) $order->get_billing_address_1(),
            (string) $order->get_billing_address_2(),
            (string) $order->get_billing_city(),
            (string) $order->get_billing_state(),
            (string) $order->get_billing_postcode(),
            self::countryName((string) $order->get_billing_country()),
        ]);

        $shippingAddress = self::buildAddressLine([
            (string) $order->get_shipping_address_1(),
            (string) $order->get_shipping_address_2(),
            (string) $order->get_shipping_city(),
            (string) $order->get_shipping_state(),
            (string) $order->get_shipping_postcode(),
            self::countryName((string) $order->get_shipping_country()),
        ]);

        $adminOrderUrl = self::adminOrderEditUrl($order);
        $telUrl = $billingPhoneNormalized !== '' ? ('tel:' . ltrim($billingPhoneNormalized, '+')) : '';
        $writeCustomerUrl = $billingEmail !== '' ? ('mailto:' . $billingEmail) : '';

        $items = [];
        $firstProductImage = '';
        $lineItems = $order->get_items('line_item');
        $lineItemCount = 0;

        foreach ($lineItems as $item) {
            if (!is_object($item) || !method_exists($item, 'get_name')) {
                continue;
            }

            $lineItemCount++;
            if (count($items) < 3) {
                $items[] = esc_html(wp_strip_all_tags((string) $item->get_name())) . ' ×' . (int) $item->get_quantity();
            }

            if (!$firstProductImage && method_exists($item, 'get_product')) {
                $firstProductImage = self::getProductImage($item->get_product());
            }
        }

        if ($lineItemCount > 3) {
            $items[] = sprintf(
                esc_html__('+%d more', 'telegram-manager-woocommerce'),
                $lineItemCount - 3
            );
        }

        $itemsShort = $items !== [] ? implode("\n", $items) : esc_html__('Items not available', 'telegram-manager-woocommerce');

        $customFields = [];
        foreach ($order->get_meta_data() as $meta) {
            if (!is_object($meta) || !isset($meta->key)) {
                continue;
            }

            $metaKey = trim((string) $meta->key);
            if ($metaKey === '' || str_starts_with($metaKey, '_')) {
                continue;
            }

            $metaValue = method_exists($meta, 'get_data') ? ($meta->get_data()['value'] ?? null) : null;
            if (is_array($metaValue) || is_object($metaValue)) {
                $metaValue = wp_json_encode($metaValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $metaValue = trim((string) $metaValue);
            if ($metaValue === '') {
                continue;
            }

            $label = ucwords(str_replace(['_', '-'], ' ', $metaKey));
            $customFields[] = $label . ': ' . $metaValue;
        }

        $customFieldsText = $customFields !== [] ? implode("\n", $customFields) : '-';

        $customerDetailsText = implode("\n", [
            'Name: ' . ($customerName !== '' ? $customerName : '-'),
            'Phone: ' . ($billingPhone !== '' ? $billingPhone : '-'),
            'Email: ' . ($billingEmail !== '' ? $billingEmail : '-'),
            'Payment: ' . ((string) $order->get_payment_method_title() !== '' ? (string) $order->get_payment_method_title() : '-'),
            'Shipping: ' . ((string) $order->get_shipping_method() !== '' ? (string) $order->get_shipping_method() : '-'),
            'Billing address: ' . $billingAddress,
            'Shipping address: ' . $shippingAddress,
            'Customer note: ' . ((string) $order->get_customer_note() !== '' ? (string) $order->get_customer_note() : '-'),
            'Custom fields: ' . $customFieldsText,
        ]);

        return [
            'order_id' => (string) $order->get_id(),
            'order_number' => (string) $order->get_order_number(),
            'order_status' => wc_get_order_status_name($order->get_status()),
            'order_total' => esc_html(html_entity_decode(wp_strip_all_tags($order->get_formatted_order_total()), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            'currency' => $order->get_currency(),

            'customer_name' => esc_html($customerName ?: '-'),
            'billing_first_name' => esc_html((string) ($order->get_billing_first_name() ?: '-')),
            'billing_last_name' => esc_html((string) ($order->get_billing_last_name() ?: '-')),
            'billing_company' => esc_html((string) ($order->get_billing_company() ?: '-')),
            'billing_email' => esc_html($billingEmail ?: '-'),
            'billing_phone' => esc_html($billingPhone ?: '-'),
            'billing_phone_normalized' => esc_html($billingPhoneNormalized ?: '-'),
            'button_phone_text' => esc_html('Phone: ' . ($billingPhone !== '' ? $billingPhone : '-')),
            'button_email_text' => esc_html('Email: ' . ($billingEmail !== '' ? $billingEmail : '-')),
            'billing_country' => esc_html(self::countryName((string) $order->get_billing_country()) ?: '-'),
            'billing_city' => esc_html((string) ($order->get_billing_city() ?: '-')),
            'billing_state' => esc_html((string) ($order->get_billing_state() ?: '-')),
            'billing_postcode' => esc_html((string) ($order->get_billing_postcode() ?: '-')),
            'billing_address_1' => esc_html((string) ($order->get_billing_address_1() ?: '-')),
            'billing_address_2' => esc_html((string) ($order->get_billing_address_2() ?: '-')),
            'billing_address_full' => esc_html($billingAddress),

            'shipping_first_name' => esc_html((string) ($order->get_shipping_first_name() ?: '-')),
            'shipping_last_name' => esc_html((string) ($order->get_shipping_last_name() ?: '-')),
            'shipping_company' => esc_html((string) ($order->get_shipping_company() ?: '-')),
            'shipping_country' => esc_html(self::countryName((string) $order->get_shipping_country()) ?: '-'),
            'shipping_city' => esc_html((string) ($order->get_shipping_city() ?: '-')),
            'shipping_state' => esc_html((string) ($order->get_shipping_state() ?: '-')),
            'shipping_postcode' => esc_html((string) ($order->get_shipping_postcode() ?: '-')),
            'shipping_address_1' => esc_html((string) ($order->get_shipping_address_1() ?: '-')),
            'shipping_address_2' => esc_html((string) ($order->get_shipping_address_2() ?: '-')),
            'shipping_address_full' => esc_html($shippingAddress),

            'payment_method' => esc_html((string) ($order->get_payment_method_title() ?: '-')),
            'shipping_method' => esc_html((string) ($order->get_shipping_method() ?: '-')),
            'customer_note' => esc_html((string) ($order->get_customer_note() ?: '-')),
            'customer_details_text' => esc_html($customerDetailsText),
            'customer_custom_fields_text' => esc_html($customFieldsText),

            'items_count' => (string) $lineItemCount,
            'items_short' => $itemsShort,
            'order_items_text' => substr($itemsShort, 0, 4096),

            'admin_order_url' => $adminOrderUrl,
            'call_customer_url' => esc_url($telUrl),
            'write_customer_url' => esc_url($writeCustomerUrl),
            'admin_url' => esc_url(admin_url()),
            'site_name' => esc_html(get_bloginfo('name')),
            'store_url' => esc_url(home_url('/')),
            'product_image' => $firstProductImage ?: self::getProductImage(null),
        ];
    }

    public static function forProduct(\WC_Product $product): array
    {
        $imageUrl = self::getProductImage($product);

        $price = esc_html(wp_strip_all_tags(wc_price($product->get_price())));
        $oldPrice = '';
        if ($product->is_on_sale() && $product->get_regular_price()) {
            $oldPrice = esc_html(wp_strip_all_tags(wc_price($product->get_regular_price())));
        }

        $shortDescription = '';
        if (method_exists($product, 'get_short_description')) {
            $shortDescription = esc_html(wp_trim_words(wp_strip_all_tags($product->get_short_description()), 20, '...'));
        }

        return [
            'product_id' => (string) $product->get_id(),
            'product_name' => esc_html($product->get_name()),
            'product_price' => $price,
            'product_old_price' => $oldPrice,
            'product_url' => esc_url(get_permalink($product->get_id())),
            'product_short_description' => $shortDescription,
            'product_image' => $imageUrl,
        ];
    }
}
