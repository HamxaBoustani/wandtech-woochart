// Wandtech WooChart - Dashboard Scripts
document.addEventListener('DOMContentLoaded', function () {
    if (typeof wandtech_chart_data === 'undefined' || typeof wandtech_chart_i18n === 'undefined') {
        console.error('Wandtech Chart Data or I18n strings are not available.');
        return;
    }

    const currencySymbol = wandtech_chart_data.currency_symbol;
    const i18n_labels = wandtech_chart_i18n.labels;

    // A beautiful, modern, and high-contrast color palette for data visualization
    const chartColors = [
        '#3B82F6', // Blue 500
        '#10B981', // Emerald 500
        '#F97316', // Orange 500
        '#8B5CF6', // Violet 500
        '#EF4444', // Red 500
        '#F59E0B', // Amber 500
        '#14B8A6', // Teal 500
        '#6366F1', // Indigo 500
        '#EC4899', // Pink 500
        '#84CC16', // Lime 500
    ];

    /**
     * Sales Chart Initialization
     */
    const salesCanvas = document.getElementById('wandtech-sales-chart');
    if (salesCanvas) {
        const salesData = wandtech_chart_data.sales_data;
        const salesLabels = Object.keys(salesData).map(key => i18n_labels[key] || key);
        const salesValues = Object.values(salesData);

        new Chart(salesCanvas, {
            type: 'doughnut',
            data: {
                labels: salesLabels,
                datasets: [{
                    label: 'Sales Summary',
                    data: salesValues,
                    backgroundColor: chartColors, // Use the shared color palette
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
                            label: function (context) {
                                let label = context.label || '';
                                let value = context.parsed;
                                return `${label}: ${value.toLocaleString(undefined, { style: 'currency', currency: currencySymbol })}`;
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Order Status Chart Initialization
     */
    const statusCanvas = document.getElementById('wandtech-status-chart');
    if (statusCanvas) {
        const statusData = wandtech_chart_data.status_data;
        new Chart(statusCanvas, {
            type: 'doughnut',
            data: {
                labels: statusData.labels,
                datasets: [{
                    data: statusData.values,
                    backgroundColor: chartColors, // Use the same shared color palette
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
                            label: function (context) {
                                let label = context.label || '';
                                let value = context.parsed;
                                if (context.dataset.data.length === 0) return `${label}: ${value}`;
                                let sum = context.dataset.data.reduce((a, b) => a + b, 0);
                                let percentage = sum > 0 ? ((value / sum) * 100).toFixed(2) + '%' : '0%';
                                return `${label}: ${value} (${percentage})`;
                            }
                        }
                    }
                }
            }
        });
    }
});