// ===== PRODUCTION-READY CODE (Version 3.2.3) =====
// Wandtech WooChart - Dashboard Scripts
// Compatible with PHP plugin version 3.1.1 and later.
// This version adds RTL support for legend item layout.

document.addEventListener('DOMContentLoaded', function () {
    // --- 1. Data Validation and Setup ---
    if (
        typeof wandtech_chart_data === 'undefined' ||
        typeof wandtech_chart_i18n?.sales_labels === 'undefined'
    ) {
        console.error('Wandtech Chart: Required data objects are missing or malformed.');
        return;
    }

    const { sales_data, status_data } = wandtech_chart_data;
    const { sales_labels, status_labels } = wandtech_chart_i18n;

    const isRtl = document.documentElement.dir === 'rtl';

    const CHART_COLORS = [
        '#3B82F6', '#10B981', '#EF4444', '#8B5CF6', '#EC4899',
        '#F97316', '#14B8A6', '#F59E0B', '#6366F1', '#84CC16'
    ];

    // --- Create a reusable options object with enhanced RTL support ---
    const commonChartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: isRtl ? 'right' : 'left',
                align: 'start',
                // ===== THE KEY CHANGE: Enable RTL layout for legend items =====
                rtl: isRtl, // This tells Chart.js to render legend items in RTL mode
                // =============================================================
                labels: {
                    boxWidth: 20,
                    padding: 15
                }
            }
        },
        layout: {
            padding: {
                left: isRtl ? 0 : 10,
                right: isRtl ? 10 : 0
            }
        }
    };


    // --- 2. Sales Summary Chart ---
    const salesCanvas = document.getElementById('wandtech-sales-chart');
    if (salesCanvas && sales_data) {
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
                ...commonChartOptions,
                plugins: {
                    ...commonChartOptions.plugins,
                    tooltip: {
                        rtl: isRtl, // Tooltip also needs to know about RTL for alignment
                        callbacks: {
                            label: (context) => context.parsed.toLocaleString()
                        }
                    }
                }
            }
        });
    }

    // --- 3. Order Status Chart ---
    const statusCanvas = document.getElementById('wandtech-status-chart');
    if (statusCanvas && status_data) {
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
                ...commonChartOptions,
                plugins: {
                    ...commonChartOptions.plugins,
                    tooltip: {
                        rtl: isRtl, // Tooltip also needs to know about RTL for alignment
                        callbacks: {
                            label: (context) => {
                                const value = context.parsed;
                                const data = context.dataset.data || [];
                                if (data.length === 0) return `${value}`;
                                const sum = data.reduce((a, b) => a + b, 0);
                                const percentage = sum > 0 ? `(${(value / sum * 100).toFixed(2)}%)` : '';
                                return `${value} ${percentage}`.trim();
                            }
                        }
                    }
                }
            }
        });
    }
});