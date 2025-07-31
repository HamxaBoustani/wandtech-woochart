// ===== PRODUCTION-READY CODE (Version 3.2) =====
// Wandtech WooChart - Dashboard Scripts
// Compatible with PHP plugin version 3.0.0 and later.
// This version removes all currency units from tooltips for a cleaner look.

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
        '#3B82F6', '#10B981', '#F97316', '#8B5CF6', '#EF4444',
        '#F59E0B', '#14B8A6', '#6366F1', '#EC4899', '#84CC16'
    ];

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
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        rtl: isRtl,
                        callbacks: {
                            label: (context) => {
                                const value = context.parsed;

                                // ===== THE KEY CHANGE: Just format the number, no currency. =====
                                // This will automatically add thousand separators (e.g., 1,234,567 or ۱٬۲۳۴٬۵۶۷)
                                // based on the user's browser locale.
                                return value.toLocaleString();
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
                    legend: { position: 'bottom' },
                    tooltip: {
                        rtl: isRtl,
                        callbacks: {
                            label: (context) => {
                                const value = context.parsed;
                                const data = context.dataset.data || [];
                                
                                if (data.length === 0) return `${value}`;
                                
                                const sum = data.reduce((a, b) => a + b, 0);
                                const percentage = sum > 0 ? `(${(value / sum * 100).toFixed(2)}%)` : '';
                                
                                // This part remains the same as it doesn't deal with currency.
                                return `${value} ${percentage}`.trim();
                            }
                        }
                    }
                }
            }
        });
    }
});