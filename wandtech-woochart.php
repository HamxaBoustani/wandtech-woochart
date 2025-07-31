<?php
/**
 * Plugin Name:       Wandtech WooChart
 * Plugin URI:        https://github.com/HamxaBoustani/wandtech-woochart/
 * Description:       High-performance and safe dashboard charts for WooCommerce sales and order statuses.
 * Version:           2.1
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
define('WANDTECH_CHART_CACHE_KEY', 'wandtech_dashboard_chart_data_v2'); // Changed key to avoid conflicts
define('WANDTECH_CHART_CACHE_DURATION', HOUR_IN_SECONDS); // Cache data for 1 hour

// ===== PLUGIN INITIALIZATION =====
add_action('plugins_loaded', 'wandtech_load_textdomain_v2');
add_action('admin_enqueue_scripts', 'wandtech_enqueue_dashboard_scripts_v2');
add_action('wp_dashboard_setup', 'wandtech_register_dashboard_widgets_v2');

// ===== HOOK CALLBACKS =====

function wandtech_load_textdomain_v2() {
    load_plugin_textdomain('wandtech-woochart', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

function wandtech_enqueue_dashboard_scripts_v2($hook) {
    // Only load on the dashboard page and if WooCommerce is active
    if ('index.php' !== $hook || !class_exists('WooCommerce')) {
        return;
    }

    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.1', true);

    $script_path = plugin_dir_url(__FILE__) . 'assets/js/dashboard-charts.js';
    wp_enqueue_script('wandtech-woochart-dashboard', $script_path, ['chartjs'], '2.1', true);

    // Fetch data using our robust, cached method and pass it to the script
    $chart_data = wandtech_get_chart_data_safely();
    wp_localize_script('wandtech-woochart-dashboard', 'wandtech_chart_data', $chart_data);
}

function wandtech_register_dashboard_widgets_v2() {
    if (!class_exists('WooCommerce')) {
        $inactive_notice = function() {
            echo '<p>' . esc_html__('WooCommerce is not active.', 'wandtech-woochart') . '</p>';
        };
        wp_add_dashboard_widget('wandtech_woochart_sales_inactive', __('WooCommerce Sales Summary', 'wandtech-woochart'), $inactive_notice);
        wp_add_dashboard_widget('wandtech_woochart_status_inactive', __('WooCommerce Orders by Status', 'wandtech-woochart'), $inactive_notice);
        return;
    }
    wp_add_dashboard_widget('wandtech_woochart_sales', __('WooCommerce Sales Summary', 'wandtech-woochart'), 'wandtech_render_sales_widget_v2');
    wp_add_dashboard_widget('wandtech_woochart_status', __('WooCommerce Orders by Status', 'wandtech-woochart'), 'wandtech_render_status_widget_v2');
}

// ===== DATA FETCHING & CACHING (THE SAFE WAY) =====

/**
 * Retrieves chart data from cache or generates it using a single, safe WooCommerce API call.
 * This is the heart of the optimized and safe plugin.
 * @return array The structured data for charts.
 */
function wandtech_get_chart_data_safely() {
    // 1. Try to get data from cache first
    $cached_data = get_transient(WANDTECH_CHART_CACHE_KEY);
    if (false !== $cached_data) {
        return $cached_data;
    }

    // 2. If cache is empty, fetch all necessary orders in ONE efficient query
    $orders = wc_get_orders([
        'limit'      => -1, // Get all orders matching the date query
        'status'     => array_keys(wc_get_order_statuses()), // All statuses
        'date_after' => date('Y-m-d', strtotime('-30 days')),
        'return'     => 'objects', // Return full order objects
    ]);
    
    // Initialize data structures
    $sales_data = [
        'gross_sales' => 0,
        'refunds'     => 0,
        'coupons'     => 0,
        'taxes'       => 0,
        'shipping'    => 0,
    ];
    $status_counts = array_fill_keys(array_keys(wc_get_order_statuses()), 0);

    // 3. Loop through the orders ONCE to calculate everything
    foreach ($orders as $order) {
        // Aggregate sales figures
        $sales_data['gross_sales'] += $order->get_total();
        $sales_data['refunds']     += $order->get_total_refunded();
        $sales_data['coupons']     += $order->get_discount_total();
        $sales_data['taxes']       += $order->get_total_tax();
        $sales_data['shipping']    += $order->get_shipping_total();

        // Count statuses
        $status_key = 'wc-' . $order->get_status();
        if (isset($status_counts[$status_key])) {
            $status_counts[$status_key]++;
        }
    }
    
    // Calculate net sales
    $net_sales = $sales_data['gross_sales'] - $sales_data['refunds'] - $sales_data['coupons'] - $sales_data['taxes'];

    // 4. Prepare the final data structure for JavaScript
    $wc_statuses = wc_get_order_statuses();
    $final_status_labels = [];
    $final_status_values = [];

    foreach ($status_counts as $status_key => $count) {
        if ($count > 0 && isset($wc_statuses[$status_key])) { // Only show statuses with orders
            $final_status_labels[] = $wc_statuses[$status_key];
            $final_status_values[] = $count;
        }
    }
    
    $final_data = [
        'sales_data' => [
            'labels' => [
                __('Net Sales', 'wandtech-woochart'),
                __('Gross Sales', 'wandtech-woochart'),
                __('Refunds', 'wandtech-woochart'),
                __('Coupons', 'wandtech-woochart'),
                __('Taxes', 'wandtech-woochart'),
                __('Shipping', 'wandtech-woochart'),
            ],
            'values' => [
                round($net_sales, 2),
                round($sales_data['gross_sales'], 2),
                round($sales_data['refunds'], 2),
                round($sales_data['coupons'], 2),
                round($sales_data['taxes'], 2),
                round($sales_data['shipping'], 2),
            ],
        ],
        'status_data' => [
            'labels' => $final_status_labels,
            'values' => $final_status_values,
        ],
        'currency_symbol' => get_woocommerce_currency(),
    ];
    
    // 5. Store the final data in cache for future requests
    set_transient(WANDTECH_CHART_CACHE_KEY, $final_data, WANDTECH_CHART_CACHE_DURATION);

    return $final_data;
}

// ===== WIDGET RENDERING =====

function wandtech_render_sales_widget_v2() {
    echo '<div style="height: 300px; position: relative;"><canvas id="wandtech-sales-chart"></canvas></div>';
}

function wandtech_render_status_widget_v2() {
    echo '<div style="height: 300px; position: relative;"><canvas id="wandtech-status-chart"></canvas></div>';
}
