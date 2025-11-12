/**
 * Nehtw Gateway Analytics Admin
 * 
 * Renders analytics charts and data using Chart.js
 */

(function() {
    'use strict';
    
    const settings = window.nehtwAnalyticsSettings || {};
    const restBase = settings.restUrl || '';
    const nonce = settings.nonce || '';
    
    let downloadsChart = null;
    let providerChart = null;
    
    /**
     * Fetch data from REST API
     */
    async function fetchData(endpoint) {
        try {
            const response = await fetch(restBase + endpoint, {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': nonce,
                    'Content-Type': 'application/json',
                },
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Error fetching analytics data:', error);
            return null;
        }
    }
    
    /**
     * Format number with commas
     */
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    
    /**
     * Format time in seconds to human readable
     */
    function formatTime(seconds) {
        if (seconds < 60) {
            return Math.round(seconds) + 's';
        } else if (seconds < 3600) {
            return Math.round(seconds / 60) + 'm';
        } else {
            return Math.round(seconds / 3600) + 'h';
        }
    }
    
    /**
     * Load and display summary KPIs
     */
    async function loadSummary() {
        const data = await fetchData('analytics/summary');
        if (!data) return;
        
        document.getElementById('kpi-total-downloads').textContent = formatNumber(data.total_downloads || 0);
        document.getElementById('kpi-total-points').textContent = formatNumber(Math.round(data.total_points || 0));
        document.getElementById('kpi-success-rate').textContent = (data.success_rate || 0).toFixed(1) + '%';
        document.getElementById('kpi-avg-processing').textContent = formatTime(data.avg_processing_seconds || 0);
        document.getElementById('kpi-last-30-days').textContent = formatNumber(data.last_30_days_downloads || 0);
    }
    
    /**
     * Load and render time series chart
     */
    async function loadTimeseries(range = '30d') {
        const data = await fetchData('analytics/timeseries?range=' + range);
        if (!data || !data.labels || data.labels.length === 0) {
            return;
        }
        
        const ctx = document.getElementById('nehtw-downloads-chart');
        if (!ctx) return;
        
        // Destroy existing chart if it exists
        if (downloadsChart) {
            downloadsChart.destroy();
        }
        
        downloadsChart = new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: 'Downloads',
                        data: data.downloads,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        yAxisID: 'yDownloads',
                        tension: 0.1,
                    },
                    {
                        label: 'Points',
                        data: data.points,
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        yAxisID: 'yPoints',
                        tension: 0.1,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                },
                scales: {
                    yDownloads: {
                        type: 'linear',
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Downloads'
                        }
                    },
                    yPoints: {
                        type: 'linear',
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Points'
                        },
                        grid: {
                            drawOnChartArea: false,
                        }
                    }
                }
            }
        });
    }
    
    /**
     * Load and render provider breakdown chart
     */
    async function loadProviders() {
        const data = await fetchData('analytics/providers');
        if (!data || data.length === 0) {
            return;
        }
        
        const ctx = document.getElementById('nehtw-provider-chart');
        if (!ctx) return;
        
        // Destroy existing chart if it exists
        if (providerChart) {
            providerChart.destroy();
        }
        
        const labels = data.map(p => p.provider);
        const downloads = data.map(p => p.downloads);
        const colors = [
            'rgba(255, 99, 132, 0.8)',
            'rgba(54, 162, 235, 0.8)',
            'rgba(255, 206, 86, 0.8)',
            'rgba(75, 192, 192, 0.8)',
            'rgba(153, 102, 255, 0.8)',
            'rgba(255, 159, 64, 0.8)',
        ];
        
        providerChart = new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Downloads',
                    data: downloads,
                    backgroundColor: labels.map((_, i) => colors[i % colors.length]),
                    borderColor: labels.map((_, i) => colors[i % colors.length].replace('0.8', '1')),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            afterLabel: function(context) {
                                const provider = data[context.dataIndex];
                                return [
                                    'Points: ' + formatNumber(Math.round(provider.points)),
                                    'Completed: ' + provider.completed,
                                    'Failed: ' + provider.failed,
                                    'Failure Rate: ' + provider.failure_rate + '%'
                                ];
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Downloads'
                        }
                    }
                }
            }
        });
    }
    
    /**
     * Load and display top users table
     */
    async function loadTopUsers() {
        const data = await fetchData('analytics/top-users');
        if (!data || data.length === 0) {
            const tbody = document.querySelector('#nehtw-top-users-table tbody');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="3" style="text-align: center; padding: 20px;">No data available</td></tr>';
            }
            return;
        }
        
        const tbody = document.querySelector('#nehtw-top-users-table tbody');
        if (!tbody) return;
        
        tbody.innerHTML = data.map(user => `
            <tr>
                <td><strong>${user.name}</strong></td>
                <td>${formatNumber(user.downloads)}</td>
                <td>${formatNumber(Math.round(user.points))}</td>
            </tr>
        `).join('');
    }
    
    /**
     * Initialize analytics dashboard
     */
    function initAnalytics() {
        // Load all data
        loadSummary();
        loadTimeseries('30d');
        loadProviders();
        loadTopUsers();
        
        // Handle range selector change
        const rangeSelector = document.getElementById('timeseries-range');
        if (rangeSelector) {
            rangeSelector.addEventListener('change', function() {
                loadTimeseries(this.value);
            });
        }
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAnalytics);
    } else {
        initAnalytics();
    }
})();

