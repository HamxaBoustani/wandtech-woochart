<?php
/**
 * Plugin Name:       Wandtech WooChart
 * Plugin URI:        https://github.com/HamxaBoustani/wandtech-woochart/
 * Description:       Dashboard charts for WooCommerce sales and order statuses.
 * Version:           3.0.0
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
// Cache key updated to reflect the new, accurate data structure
define('WANDTECH_CHART_CACHE_KEY', 'wandtech_dashboard_data_v8_user_centric');
define('WANDTECH_CHART_CACHE_DURATION', HOUR_IN_SECONDS);

// ===== HPOS COMPATIBILITY DECLARATION =====
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// ===== PLUGIN INITIALIZATION =====
add_action('plugins_loaded', 'wandtech_load_textdomain_final');
add_action('admin_enqueue_scripts', 'wandtech_enqueue_scripts_final');
add_action('wp_dashboard_setup', 'wandtech_register_widgets_final');

// ===== HOOK CALLBACKS =====

/**
 * Loads the plugin's translated strings.
 */
function wandtech_load_textdomain_final() {
    load_plugin_textdomain('wandtech-woochart', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

/**
 * Enqueues scripts and styles for the dashboard and localizes data.
 */
function wandtech_enqueue_scripts_final($hook) {
    if ('index.php' !== $hook || !class_exists('WooCommerce')) {
        return;
    }

    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.1', true);
    
    $script_path = plugin_dir_url(__FILE__) . 'assets/js/dashboard-charts.js';
    wp_enqueue_script('wandtech-woochart-dashboard', $script_path, ['chartjs'], '2.9', true);

    // Get the language-independent data from cache/db
    $chart_data = wandtech_get_chart_data_final();
    
    // Create a dictionary of all necessary translations, always fresh for the current user
    $translation_strings = [
        'sales_labels' => [
            'net_sales'   => __('Net Sales', 'wandtech-woochart'),
            'gross_sales' => __('Gross Sales', 'wandtech-woochart'),
            'refunds'     => __('Refunds', 'wandtech-woochart'),
            'coupons'     => __('Coupons', 'wandtech-woochart'),
            'taxes'       => __('Taxes', 'wandtech-woochart'),
            'shipping'    => __('Shipping', 'wandtech-woochart'),
        ],
        'status_labels' => wc_get_order_statuses() 
    ];

    wp_localize_script('wandtech-woochart-dashboard', 'wandtech_chart_data', $chart_data);
    wp_localize_script('wandtech-woochart-dashboard', 'wandtech_chart_i18n', $translation_strings);
}

/**
 * Registers the dashboard widgets.
 */
function wandtech_register_widgets_final() {
    if (!class_exists('WooCommerce')) {
        $inactive_notice = function() {
            echo '<p>' . esc_html__('WooCommerce is not active.', 'wandtech-woochart') . '</p>';
        };
        wp_add_dashboard_widget('wandtech_woochart_sales_inactive', __('WooCommerce Sales Summary', 'wandtech-woochart'), $inactive_notice);
        wp_add_dashboard_widget('wandtech_woochart_status_inactive', __('WooCommerce Orders by Status', 'wandtech-woochart'), $inactive_notice);
        return;
    }
    wp_add_dashboard_widget('wandtech_woochart_sales', __('WooCommerce Sales Summary', 'wandtech-woochart'), 'wandtech_render_chart_container_sales');
    wp_add_dashboard_widget('wandtech_woochart_status', __('WooCommerce Orders by Status', 'wandtech-woochart'), 'wandtech_render_chart_container_status');
}

// ===== DATA FETCHING & CACHING (The User-Centric Accurate Version) =====

/**
 * Fetches and caches all data required for the charts with accurate, user-centric calculations.
 * @return array The structured data for the charts.
 */
function wandtech_get_chart_data_final() {
    $cached_data = get_transient(WANDTECH_CHART_CACHE_KEY);
    if (false !== $cached_data) {
        return $cached_data;
    }

    // Query 1: Get orders for sales calculation (only paid statuses)
    $sales_orders = wc_get_orders([
        'limit'      => -1,
        'type'       => 'shop_order',
        'status'     => ['wc-processing', 'wc-completed', 'wc-on-hold'],
        'date_after' => date('Y-m-d', strtotime('-30 days')),
        'return'     => 'objects'
    ]);
    
    // Query 2: Get refunds
    $refunds_query = wc_get_orders([
        'limit'      => -1,
        'type'       => 'shop_order_refund',
        'date_after' => date('Y-m-d', strtotime('-30 days')),
        'return'     => 'objects'
    ]);

    // Query 3: Get all orders for status counting
    $all_orders_for_status = wc_get_orders([
        'limit'      => -1,
        'type'       => 'shop_order',
        'date_after' => date('Y-m-d', strtotime('-30 days')),
        'return'     => 'objects'
    ]);

    // --- Calculations ---
    $sales = [
        'gross_sales' => 0, 'coupons' => 0, 'taxes' => 0,
        'shipping' => 0, 'refunds' => 0,
    ];

    // Process sales from paid orders
    foreach ($sales_orders as $order) {
        if (!is_a($order, 'WC_Order')) continue;

        // Gross Sales = Total amount paid for products (Total - Shipping - All Taxes).
        $gross_product_sales = $order->get_total() - $order->get_shipping_total() - $order->get_total_tax() - $order->get_shipping_tax();
        $sales['gross_sales'] += $gross_product_sales;

        $sales['coupons']  += $order->get_discount_total();
        $sales['taxes']    += $order->get_total_tax();
        $sales['shipping'] += $order->get_shipping_total();
    }
    
    // Process refunds
    foreach ($refunds_query as $refund) {
        if(is_a($refund, 'WC_Order_Refund')) {
            $sales['refunds'] += $refund->get_amount();
        }
    }
    
    // Net Sales = Gross Product Sales - Total Refunds.
    $net_sales = $sales['gross_sales'] - $sales['refunds'];

    // Process status counts from all orders
    $all_statuses = wc_get_order_statuses();
    $status_counts = array_fill_keys(array_keys($all_statuses), 0);
    foreach($all_orders_for_status as $order) {
        if (!is_a($order, 'WC_Order')) continue;
        $status_key = 'wc-' . $order->get_status();
         if (isset($status_counts[$status_key])) {
            $status_counts[$status_key]++;
        }
    }

    // --- Prepare final data structure ---
    $status_keys = [];
    $status_values = [];
    foreach ($status_counts as $key => $count) {
        if ($count > 0) {
            $status_keys[] = $key;
            $status_values[] = $count;
        }
    }
    
    $final_data = [
        'sales_data' => [
            'net_sales'   => round($net_sales, 2),
            'gross_sales' => round($sales['gross_sales'], 2),
            'refunds'     => round($sales['refunds'], 2),
            'coupons'     => round($sales['coupons'], 2),
            'taxes'       => round($sales['taxes'], 2),
            'shipping'    => round($sales['shipping'], 2),
        ],
        'status_data' => [
            'keys'   => $status_keys,
            'values' => $status_values,
        ],
        'currency_symbol' => get_woocommerce_currency(),
    ];
    
    set_transient(WANDTECH_CHART_CACHE_KEY, $final_data, WANDTECH_CHART_CACHE_DURATION);
    return $final_data;
}

// ===== WIDGET RENDERING =====

/**
 * Renders the HTML container for the sales chart.
 */
function wandtech_render_chart_container_sales() {
    echo '<div style="height: 300px; position: relative;"><canvas id="wandtech-sales-chart"></canvas></div>';
}

/**
 * Renders the HTML container for the status chart.
 */
function wandtech_render_chart_container_status() {
    echo '<div style="height: 300px; position: relative;"><canvas id="wandtech-status-chart"></canvas></div>';
}