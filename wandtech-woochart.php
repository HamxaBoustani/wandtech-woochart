<?php
/**
 * Plugin Name: Wandtech WooChart
 * Description: Dashboard charts for WooCommerce sales and order statuses.
 * Version: 1.0
 * Author: Hamxa
 * Text Domain: wandtech-woochart
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

// Load plugin textdomain
add_action('plugins_loaded', function () {
    load_plugin_textdomain(
        'wandtech-woochart',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
});

add_action('admin_enqueue_scripts', function($hook) {
    if ($hook === 'index.php') {
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
        // wp_enqueue_script('wandtech-woochart-chartjs', plugin_dir_url(__FILE__) . 'assets/js/chart.js', ['chartjs'], null, true);

    }
});

add_action('wp_dashboard_setup', function() {
    wp_add_dashboard_widget(
        'wandtech_woochart_sales',
        __('WooCommerce Sales Chart', 'wandtech-woochart'),
        'wandtech_woochart_sales_widget'
    );

    wp_add_dashboard_widget(
        'wandtech_woochart_status',
        __('WooCommerce Orders by Status', 'wandtech-woochart'),
        'wandtech_woochart_status_widget'
    );
});


// Sales chart widget
function wandtech_woochart_sales_widget() {
    if (!class_exists('WooCommerce')) {
        echo '<p>' . __('WooCommerce is not active.', 'wandtech-woochart') . '</p>';
        return;
    }

    $orders = wc_get_orders([
        'limit' => -1,
        'status' => ['wc-completed', 'wc-processing', 'wc-on-hold'],
        'date_after' => strtotime('-30 days'),
        'return' => 'ids',
    ]);

    $data = [
        'gross_sales' => 0,
        'refunds' => 0,
        'coupons' => 0,
        'taxes' => 0,
        'shipping' => 0,
        'net_sales' => 0,
        'total_sales' => 0,
    ];

    foreach ($orders as $order_id) {
        $order = wc_get_order($order_id);
        $data['gross_sales'] += $order->get_total();
        $data['refunds'] += $order->get_total_refunded();
        $data['coupons'] += $order->get_discount_total();
        $data['taxes'] += $order->get_total_tax();
        $data['shipping'] += $order->get_shipping_total();
        $data['total_sales'] += $order->get_total();
    }

    $data['net_sales'] = $data['gross_sales'] - $data['refunds'] - $data['coupons'];

    $labels = [
        __('Gross sales', 'woocommerce'),
        __('Refunds', 'woocommerce'),
        __('Coupons', 'woocommerce'),
        __('Net Sales', 'woocommerce'),
        __('Taxes', 'woocommerce'),
        __('Shipping', 'woocommerce'),
        __('Total sales', 'woocommerce'),
    ];
    ?>
    <canvas id="wandtech-sales-chart" height="200"></canvas>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        new Chart(document.getElementById('wandtech-sales-chart'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($labels) ?>,
                datasets: [{
                    data: [
                        <?= round($data['gross_sales'], 2) ?>,
                        <?= round($data['refunds'], 2) ?>,
                        <?= round($data['coupons'], 2) ?>,
                        <?= round($data['net_sales'], 2) ?>,
                        <?= round($data['taxes'], 2) ?>,
                        <?= round($data['shipping'], 2) ?>,
                        <?= round($data['total_sales'], 2) ?>
                    ],
                    backgroundColor: [
                        '#36A2EB',
                        '#FF6384',
                        '#FFCE56',
                        '#4BC0C0',
                        '#9966FF',
                        '#FF9F40',
                        '#2ecc71'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.parsed.toLocaleString(undefined, {
                                    style: 'currency',
                                    currency: '<?= get_woocommerce_currency(); ?>'
                                });
                            }
                        }
                    }
                },
                responsive: true
            }
        });
    });
    </script>
    <?php
}

// Order status chart widget
function wandtech_woochart_status_widget() {
    if (!class_exists('WooCommerce')) {
        echo '<p>WooCommerce is not active.</p>';
        return;
    }

    $statuses = wc_get_order_statuses();
    $counts = [];

    foreach ($statuses as $key => $label) {
        $status = str_replace('wc-', '', $key);
        $orders = wc_get_orders([
            'limit' => -1,
            'status' => [$key],
            'date_after' => strtotime('-30 days'),
            'return' => 'ids',
        ]);
        $counts[$status] = count($orders);
    }

    ?>
    <canvas id="wandtech-status-chart" height="200"></canvas>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        new Chart(document.getElementById('wandtech-status-chart'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_values($statuses)) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($counts)) ?>,
                    backgroundColor: [
                        '#36A2EB', '#FF6384', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40',
                        '#C9CBCF', '#8AC926', '#FF595E', '#1982C4'
                    ],
                }]
            },
            options: {
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    });
    </script>
    <?php
}
