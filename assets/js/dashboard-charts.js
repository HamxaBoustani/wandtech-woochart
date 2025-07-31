// ===== PRODUCTION-READY CODE =====
// assets/js/dashboard-charts.js

document.addEventListener('DOMContentLoaded', function () {
    // wandtech_chart_data is passed from PHP via wp_localize_script
    if (typeof wandtech_chart_data === 'undefined') {
        console.error('Wandtech Chart Data is not available.');
        return;
    }

    const currencySymbol = wandtech_chart_data.currency_symbol;

    /**
     * Sales Chart Initialization
     */
    const salesCanvas = document.getElementById('wandtech-sales-chart');
    if (salesCanvas) {
        const salesData = wandtech_chart_data.sales_data;
        new Chart(salesCanvas, {
            // ===== CHANGE #1: Changed chart type to 'doughnut' =====
            type: 'doughnut',
            data: {
                labels: salesData.labels,
                datasets: [{
                    label: 'Sales Summary',
                    data: salesData.values,
                    backgroundColor: [
                        '#4BC0C0', // Net Sales
                        '#36A2EB', // Gross Sales
                        '#FF6384', // Refunds
                        '#FFCE56', // Coupons
                        '#9966FF', // Taxes
                        '#FF9F40', // Shipping
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                // ===== CHANGE #2: Updated options for a doughnut chart =====
                plugins: {
                    legend: {
                        position: 'bottom', // Legend at the bottom looks good for doughnut charts
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                let label = context.label || '';
                                let value = context.parsed;
                                return `${label}: ${value.toLocaleString(undefined, {
                                    style: 'currency',
                                    currency: currencySymbol
                                })}`;
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Order Status Chart Initialization (No Changes Here)
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
                        '#36A2EB', '#FF6384', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40',
                        '#C9CBCF', '#8AC926', '#FF595E', '#1982C4'
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