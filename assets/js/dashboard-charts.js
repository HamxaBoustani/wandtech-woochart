// Wandtech WooChart - Dashboard Scripts
document.addEventListener('DOMContentLoaded', function () {
    if (typeof wandtech_chart_data === 'undefined' || typeof wandtech_chart_i18n === 'undefined') {
        console.error('Wandtech Chart Data or I18n strings are not available.');
        return;
    }

    const currencySymbol = wandtech_chart_data.currency_symbol;
    const i18n_labels = wandtech_chart_i18n.labels;

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
                    backgroundColor: [
                        '#4BC0C0',
                        '#36A2EB',
                        '#FF6384',
                        '#FFCE56',
                        '#9966FF',
                        '#FF9F40',
                    ],
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
                    backgroundColor: [
                        '#36A2EB',
                        '#FF6384',
                        '#FFCE56',
                        '#4BC0C0',
                        '#9966FF',
                        '#FF9F40',
                        '#C9CBCF',
                        '#8AC926',
                        '#FF595E',
                        '#1982C4'
                    ],
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