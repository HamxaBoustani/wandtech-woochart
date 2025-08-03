<?php
/**
 * Plugin Name:       Wandtech WooChart
 * Plugin URI:        https://github.com/HamxaBoustani/wandtech-woochart/
 * Description:       Dashboard charts for WooCommerce sales and order statuses.
 * Version:           3.1.1
 * Author:            Hamxa
 * Author URI:        https://github.com/HamxaBoustani
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wandtech-woochart
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Tested up to:      6.5
 * Requires PHP:      7.4
 * WC requires at least: 7.0
 * WC tested up to: 8.5
 */

if (!defined('ABSPATH')) exit;

// ===== CONSTANTS & CONFIGURATION =====
// Cache key updated to reflect the new, accurate calculation logic
define('WANDTECH_CHART_CACHE_KEY', 'wandtech_dashboard_data_v1_accurate_sales');
define('WANDTECH_CHART_CACHE_DURATION', HOUR_IN_SECONDS);

// ===== HPOS COMPATIBILITY & INITIALIZATION (No Changes) =====
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});
add_action('plugins_loaded', 'wandtech_load_textdomain_final');
add_action('admin_enqueue_scripts', 'wandtech_enqueue_scripts_final');
add_action('wp_dashboard_setup', 'wandtech_register_widgets_final');

// ===== HOOK CALLBACKS (No Changes) =====
function wandtech_load_textdomain_final() { /* ... */ load_plugin_textdomain('wandtech-woochart', false, dirname(plugin_basename(__FILE__)) . '/languages'); }
function wandtech_enqueue_scripts_final($hook) {
    if ('index.php' !== $hook || !class_exists('WooCommerce')) return;
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.1', true);
    $script_path = plugin_dir_url(__FILE__) . 'assets/js/dashboard-charts.js';
    wp_enqueue_script('wandtech-woochart-dashboard', $script_path, ['chartjs'], '3.1.1', true);
    $chart_data = wandtech_get_chart_data_final();
    $translation_strings = [
        'sales_labels' => [
            'net_sales'     => __('Net Sales', 'wandtech-woochart'),
            'gross_sales'   => __('Gross Sales', 'wandtech-woochart'),
            'refunds'       => __('Refunds', 'wandtech-woochart'),
            'coupons'       => __('Coupon Discount', 'wandtech-woochart'),
            'sale_discount' => __('Sale Discount', 'wandtech-woochart'),
            'taxes'         => __('Taxes', 'wandtech-woochart'),
            'shipping'      => __('Shipping', 'wandtech-woochart'),
        ],
        'status_labels' => wc_get_order_statuses()
    ];
    wp_localize_script('wandtech-woochart-dashboard', 'wandtech_chart_data', $chart_data);
    wp_localize_script('wandtech-woochart-dashboard', 'wandtech_chart_i18n', $translation_strings);
}
function wandtech_register_widgets_final() { /* ... */ if (!class_exists('WooCommerce')){ $inactive_notice = function() { echo '<p>' . esc_html__('WooCommerce is not active.', 'wandtech-woochart') . '</p>'; }; wp_add_dashboard_widget('wandtech_woochart_sales_inactive', __('WooCommerce Sales Summary', 'wandtech-woochart'), $inactive_notice); wp_add_dashboard_widget('wandtech_woochart_status_inactive', __('WooCommerce Orders by Status', 'wandtech-woochart'), $inactive_notice); return; } wp_add_dashboard_widget('wandtech_woochart_sales', __('WooCommerce Sales Summary', 'wandtech-woochart'), 'wandtech_render_chart_container_sales'); wp_add_dashboard_widget('wandtech_woochart_status', __('WooCommerce Orders by Status', 'wandtech-woochart'), 'wandtech_render_chart_container_status'); }

// ===== DATA FETCHING & CACHING (ACCURATE SALE DISCOUNT LOGIC) =====

function wandtech_get_chart_data_final() {
    $cached_data = get_transient(WANDTECH_CHART_CACHE_KEY);
    if (false !== $cached_data) {
        return $cached_data;
    }

    $sales_orders = wc_get_orders(['limit' => -1, 'type' => 'shop_order', 'status' => ['wc-processing', 'wc-completed', 'wc-on-hold'], 'date_after' => date('Y-m-d', strtotime('-30 days')), 'return' => 'objects']);
    $refunds_query = wc_get_orders(['limit' => -1, 'type' => 'shop_order_refund', 'date_after' => date('Y-m-d', strtotime('-30 days')), 'return' => 'objects']);
    $all_orders_for_status = wc_get_orders(['limit' => -1, 'type' => 'shop_order', 'date_after' => date('Y-m-d', strtotime('-30 days')), 'return' => 'objects']);

    $sales = [
        'gross_sales'   => 0, 'coupons' => 0, 'sale_discount' => 0,
        'taxes'         => 0, 'shipping' => 0, 'refunds' => 0,
    ];

    foreach ($sales_orders as $order) {
        if (!is_a($order, 'WC_Order')) continue;

        // ===== THE ACCURATE CALCULATION LOGIC =====
        foreach ($order->get_items() as $item) {
            // Get the product object from the item
            $product = $item->get_product();
            if (!$product) continue;

            // Get the price the customer *actually paid* for one unit of this item line
            // This is the price after sale discounts but before coupon discounts applied to the whole cart.
            $price_paid_per_unit = $item->get_total() / $item->get_quantity();

            // The 'regular_price' is the price before any sale.
            $regular_price = (float) $product->get_regular_price();
            
            // If there's a regular price and it's higher than what was paid, a sale was active.
            if ($regular_price > 0 && $regular_price > $price_paid_per_unit) {
                $sales['sale_discount'] += ($regular_price - $price_paid_per_unit) * $item->get_quantity();
            }

            // Gross sales should be the sum of what each item *would have* cost at its regular price
            $sales['gross_sales'] += $regular_price * $item->get_quantity();
        }

        $sales['coupons']  += $order->get_discount_total();
        $sales['taxes']    += $order->get_total_tax();
        $sales['shipping'] += $order->get_shipping_total();
    }
    
    foreach ($refunds_query as $refund) {
        if(is_a($refund, 'WC_Order_Refund')) {
            $sales['refunds'] += $refund->get_amount();
        }
    }
    
    // Net Sales = Gross Sales (full price) - Sale Discount - Coupon Discount - Refunds
    $net_sales = $sales['gross_sales'] - $sales['sale_discount'] - $sales['coupons'] - $sales['refunds'];
    
    // Process status counts (no changes here)
    $all_statuses = wc_get_order_statuses();
    $status_counts = array_fill_keys(array_keys($all_statuses), 0);
    foreach($all_orders_for_status as $order) {
        if (!is_a($order, 'WC_Order')) continue;
        $status_key = 'wc-' . $order->get_status();
         if (isset($status_counts[$status_key])) $status_counts[$status_key]++;
    }
    $status_keys = [];
    $status_values = [];
    foreach ($status_counts as $key => $count) {
        if ($count > 0) { $status_keys[] = $key; $status_values[] = $count; }
    }
    
    // Final data structure remains the same
    $final_data = [
        'sales_data' => [
            'net_sales'     => round($net_sales, 2),
            'gross_sales'   => round($sales['gross_sales'], 2),
            'refunds'       => round($sales['refunds'], 2),
            'coupons'       => round($sales['coupons'], 2),
            'sale_discount' => round($sales['sale_discount'], 2),
            'taxes'         => round($sales['taxes'], 2),
            'shipping'      => round($sales['shipping'], 2),
        ],
        'status_data' => [ 'keys' => $status_keys, 'values' => $status_values ],
        'currency_symbol' => get_woocommerce_currency(),
    ];
    
    set_transient(WANDTECH_CHART_CACHE_KEY, $final_data, WANDTECH_CHART_CACHE_DURATION);
    return $final_data;
}

// ===== WIDGET RENDERING (No Changes) =====
function wandtech_render_chart_container_sales() { /* ... */ echo '<div style="height: 300px; position: relative;"><canvas id="wandtech-sales-chart"></canvas></div>'; }
function wandtech_render_chart_container_status() { /* ... */ echo '<div style="height: 300px; position: relative;"><canvas id="wandtech-status-chart"></canvas></div>'; }