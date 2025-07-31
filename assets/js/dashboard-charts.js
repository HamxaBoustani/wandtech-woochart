// ===== PRODUCTION-READY CODE (Final Version) =====
// Wandtech WooChart - Dashboard Scripts
// Compatible with PHP plugin version 2.9 and later.

/**
 * Initializes dashboard charts for Wandtech WooChart.
 * This script depends on `wandtech_chart_data` (numeric data) and
 * `wandtech_chart_i18n` (translation strings) passed from PHP.
 */
document.addEventListener('DOMContentLoaded', function () {
    // --- 1. Data Validation and Setup ---
    // A robust check to ensure all required data structures exist.
    if (
        typeof wandtech_chart_data?.currency_symbol === 'undefined' ||
        typeof wandtech_chart_i18n?.sales_labels === 'undefined' ||
        typeof wandtech_chart_i18n?.status_labels === 'undefined'
    ) {
        console.error('Wandtech Chart: Required data objects (wandtech_chart_data or wandtech_chart_i18n) are missing or malformed.');
        return;
    }

    // Destructure data for cleaner access
    const { currency_symbol, sales_data, status_data } = wandtech_chart_data;
    const { sales_labels, status_labels } = wandtech_chart_i18n;

    // A beautiful, modern, and high-contrast color palette for data visualization.
    const CHART_COLORS = [
        '#3B82F6', '#10B981', '#F97316', '#8B5CF6', '#EF4444',
        '#F59E0B', '#14B8A6', '#6366F1', '#EC4899', '#84CC16'
    ];

    // --- 2. Sales Summary Chart ---
    const salesCanvas = document.getElementById('wandtech-sales-chart');
    if (salesCanvas && sales_data) {
        // Dynamically create translated labels from the received keys.
        const translatedSalesLabels = Object.keys(sales_data).map(key => sales_labels[key] || key);
        const salesValues = Object.values(sales_data);

        new Chart(salesCanvas, {
            type: 'doughnut',
            data: {
                labels: translatedSalesLabels,
                datasets: [{
                    data: salesValues,
                    backgroundColor: CHART_COLORS,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const label = context.label || '';
                                const value = context.parsed;
                                return `${label}: ${value.toLocaleString(undefined, { style: 'currency', currency: currency_symbol })}`;
                            }
                        }
                    }
                }
            }
        });
    }

    // --- 3. Order Status Chart ---
    const statusCanvas = document.getElementById('wandtech-status-chart');
    if (statusCanvas && status_data) {
        // Dynamically map the raw status keys to their fresh translated labels.
        const translatedStatusLabels = status_data.keys.map(key => status_labels[key] || key);

        new Chart(statusCanvas, {
            type: 'doughnut',
            data: {
                labels: translatedStatusLabels,
                datasets: [{
                    data: status_data.values,
                    backgroundColor: CHART_COLORS,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const label = context.label || '';
                                const value = context.parsed;
                                const data = context.dataset.data || [];
                                
                                if (data.length === 0) return `${label}: ${value}`;
                                
                                const sum = data.reduce((a, b) => a + b, 0);
                                const percentage = sum > 0 ? `(${(value / sum * 100).toFixed(2)}%)` : '';
                                
                                return `${label}: ${value} ${percentage}`.trim();
                            }
                        }
                    }
                }
            }
        });
    }
});