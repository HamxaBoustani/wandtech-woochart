<?php
/**
 * Plugin Name:       Wandtech WooChart
 * Plugin URI:        https://github.com/HamxaBoustani/wandtech-woochart/
 * Description:       Dashboard charts for WooCommerce sales and order statuses.
 * Version:           2.4
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
define('WANDTECH_CHART_CACHE_KEY', 'wandtech_dashboard_numeric_data_v3'); // Key changed as data structure changed
define('WANDTECH_CHART_CACHE_DURATION', HOUR_IN_SECONDS);

// ===== HPOS COMPATIBILITY DECLARATION =====
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// ===== PLUGIN INITIALIZATION =====
add_action('plugins_loaded', 'wandtech_load_textdomain_v4');
add_action('admin_enqueue_scripts', 'wandtech_enqueue_dashboard_scripts_v4');
add_action('wp_dashboard_setup', 'wandtech_register_dashboard_widgets_v4');


// ===== HOOK CALLBACKS =====

function wandtech_load_textdomain_v4() {
    load_plugin_textdomain('wandtech-woochart', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

function wandtech_enqueue_dashboard_scripts_v4($hook) {
    if ('index.php' !== $hook || !class_exists('WooCommerce')) {
        return;
    }

    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.1', true);
    
    $script_path = plugin_dir_url(__FILE__) . 'assets/js/dashboard-charts.js';
    wp_enqueue_script('wandtech-woochart-dashboard', $script_path, ['chartjs'], '2.4', true);

    // ===== THE FIX - PART 1: Localize data separately =====
    $chart_data = wandtech_get_chart_data_safely();
    
    // Create a separate, non-cached array for translations
    $translation_strings = [
        'labels' => [
            'net_sales'   => __('Net Sales', 'wandtech-woochart'),
            'gross_sales' => __('Gross Sales', 'wandtech-woochart'),
            'refunds'     => __('Refunds', 'wandtech-woochart'),
            'coupons'     => __('Coupons', 'wandtech-woochart'),
            'taxes'       => __('Taxes', 'wandtech-woochart'),
            'shipping'    => __('Shipping', 'wandtech-woochart'),
        ]
    ];

    // Pass both the cached data and the fresh translations to JS
    wp_localize_script('wandtech-woochart-dashboard', 'wandtech_chart_data', $chart_data);
    wp_localize_script('wandtech-woochart-dashboard', 'wandtech_chart_i18n', $translation_strings); // i18n stands for Internationalization
}

function wandtech_register_dashboard_widgets_v4() {
    // ... (rest of the function remains the same, but for consistency let's include it)
    if (!class_exists('WooCommerce')) {
        $inactive_notice = function() { echo '<p>' . esc_html__('WooCommerce is not active.', 'wandtech-woochart') . '</p>'; };
        wp_add_dashboard_widget('wandtech_woochart_sales_inactive', __('WooCommerce Sales Summary', 'wandtech-woochart'), $inactive_notice);
        wp_add_dashboard_widget('wandtech_woochart_status_inactive', __('WooCommerce Orders by Status', 'wandtech-woochart'), $inactive_notice);
        return;
    }
    wp_add_dashboard_widget('wandtech_woochart_sales', __('WooCommerce Sales Summary', 'wandtech-woochart'), 'wandtech_render_sales_widget_v4');
    wp_add_dashboard_widget('wandtech_woochart_status', __('WooCommerce Orders by Status', 'wandtech-woochart'), 'wandtech_render_status_widget_v4');
}

// ===== DATA FETCHING & CACHING (THE SAFE WAY) =====

function wandtech_get_chart_data_safely() {
    $cached_data = get_transient(WANDTECH_CHART_CACHE_KEY);
    if (false !== $cached_data) {
        return $cached_data;
    }

    $orders = wc_get_orders([ /* ... */ 'limit' => -1, 'status' => array_keys(wc_get_order_statuses()), 'date_after' => date('Y-m-d', strtotime('-30 days')), 'return' => 'objects' ]);
    
    $sales_data = [ 'gross_sales' => 0, 'refunds' => 0, 'coupons' => 0, 'taxes' => 0, 'shipping' => 0 ];
    $status_counts = array_fill_keys(array_keys(wc_get_order_statuses()), 0);

    foreach ($orders as $order) {
        $sales_data['gross_sales'] += $order->get_total();
        $sales_data['refunds'] += $order->get_total_refunded();
        $sales_data['coupons'] += $order->get_discount_total();
        $sales_data['taxes'] += $order->get_total_tax();
        $sales_data['shipping'] += $order->get_shipping_total();
        $status_key = 'wc-' . $order->get_status();
        if (isset($status_counts[$status_key])) $status_counts[$status_key]++;
    }
    
    $net_sales = $sales_data['gross_sales'] - $sales_data['refunds'] - $sales_data['coupons'] - $sales_data['taxes'];

    $wc_statuses = wc_get_order_statuses();
    $final_status_data = [];
    foreach ($status_counts as $status_key => $count) {
        if ($count > 0 && isset($wc_statuses[$status_key])) {
            $final_status_data[$wc_statuses[$status_key]] = $count; // Use label as key for JS
        }
    }
    
    // ===== THE FIX - PART 2: Store non-translated keys in cache =====
    $final_data = [
        'sales_data' => [
            // Using static, non-translated keys
            'net_sales'   => round($net_sales, 2),
            'gross_sales' => round($sales_data['gross_sales'], 2),
            'refunds'     => round($sales_data['refunds'], 2),
            'coupons'     => round($sales_data['coupons'], 2),
            'taxes'       => round($sales_data['taxes'], 2),
            'shipping'    => round($sales_data['shipping'], 2),
        ],
        'status_data' => [
            'labels' => array_keys($final_status_data),
            'values' => array_values($final_status_data),
        ],
        'currency_symbol' => get_woocommerce_currency(),
    ];
    
    set_transient(WANDTECH_CHART_CACHE_KEY, $final_data, WANDTECH_CHART_CACHE_DURATION);
    return $final_data;
}

// ===== WIDGET RENDERING =====

function wandtech_render_sales_widget_v4() { echo '<div style="height: 300px; position: relative;"><canvas id="wandtech-sales-chart"></canvas></div>'; }
function wandtech_render_status_widget_v4() { echo '<div style="height: 300px; position: relative;"><canvas id="wandtech-status-chart"></canvas></div>'; }