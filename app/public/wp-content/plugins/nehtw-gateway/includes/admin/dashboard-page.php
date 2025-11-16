<?php

/**
 * Nehtw Admin Dashboard Page
 * 
 * Main admin dashboard UI
 * 
 * @package Nehtw_Gateway
 * @version 2.0.0
 * 
 * INSTALLATION:
 * 1. Copy this file to: /wp-content/plugins/nehtw-gateway/includes/admin/dashboard-page.php
 * 2. This file is loaded by the admin menu callback
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Get realtime stats for display
$realtime_stats = Nehtw_Analytics_Engine::get_realtime_stats();
$subscription_health = class_exists( 'Nehtw_Admin_Subscriptions' )
    ? Nehtw_Admin_Subscriptions::get_health_metrics()
    : array(
        'active'          => 0,
        'overdue'         => 0,
        'pending_retries' => 0,
        'last_billing_run'=> get_option( 'nehtw_billing_cron_last_run' ),
    );
?>

<div class="wrap nehtw-dashboard">
    <h1 class="nehtw-dashboard-title">
        <span class="dashicons dashicons-chart-area"></span>
        Nehtw Gateway Dashboard
    </h1>
    
    <!-- Action Buttons -->
    <div class="nehtw-dashboard-actions">
        <button id="refresh-dashboard" class="button button-secondary">
            <span class="dashicons dashicons-update"></span>
            Refresh
        </button>
        <a href="<?php echo admin_url('admin-post.php?action=nehtw_manual_poll'); ?>" class="button button-secondary">
            <span class="dashicons dashicons-controls-play"></span>
            Poll Orders Now
        </a>
        <a href="<?php echo admin_url('admin-post.php?action=nehtw_backfill_analytics&days=30'); ?>" class="button button-secondary">
            <span class="dashicons dashicons-database"></span>
            Backfill Analytics
        </a>
        <a href="<?php echo admin_url('admin-post.php?action=nehtw_sync_orders'); ?>" class="button button-secondary">
            <span class="dashicons dashicons-update"></span>
            Sync Existing Orders
        </a>
        <a href="<?php echo wp_nonce_url(rest_url('nehtw/v1/admin/orders/export'), 'wp_rest'); ?>" class="button button-secondary">
            <span class="dashicons dashicons-download"></span>
            Export CSV
        </a>
    </div>
    
    <!-- Real-time Stats Cards -->
    <div class="nehtw-stats-grid">
        <div class="nehtw-stat-card">
            <div class="stat-icon pending">
                <span class="dashicons dashicons-clock"></span>
            </div>
            <div class="stat-content">
                <div class="stat-label">Pending</div>
                <div class="stat-value"><?php echo number_format($realtime_stats['pending_orders']); ?></div>
            </div>
        </div>
        
        <div class="nehtw-stat-card">
            <div class="stat-icon processing">
                <span class="dashicons dashicons-update"></span>
            </div>
            <div class="stat-content">
                <div class="stat-label">Processing</div>
                <div class="stat-value"><?php echo number_format($realtime_stats['processing_orders']); ?></div>
            </div>
        </div>
        
        <div class="nehtw-stat-card">
            <div class="stat-icon success">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="stat-content">
                <div class="stat-label">Today's Orders</div>
                <div class="stat-value"><?php echo number_format($realtime_stats['orders_today']); ?></div>
                <div class="stat-sub"><?php echo number_format($realtime_stats['successful_today']); ?> successful</div>
            </div>
        </div>
        
        <div class="nehtw-stat-card">
            <div class="stat-icon revenue">
                <span class="dashicons dashicons-money-alt"></span>
            </div>
            <div class="stat-content">
                <div class="stat-label">Today's Revenue</div>
                <div class="stat-value">$<?php echo number_format($realtime_stats['revenue_today'], 2); ?></div>
            </div>
        </div>
        
        <div class="nehtw-stat-card">
            <div class="stat-icon users">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="stat-content">
                <div class="stat-label">Active Users Today</div>
                <div class="stat-value"><?php echo number_format($realtime_stats['active_users_today']); ?></div>
            </div>
        </div>
        
        <div class="nehtw-stat-card <?php echo $realtime_stats['critical_alerts'] > 0 ? 'alert-critical' : ''; ?>">
            <div class="stat-icon alerts">
                <span class="dashicons dashicons-warning"></span>
            </div>
            <div class="stat-content">
                <div class="stat-label">Unread Alerts</div>
                <div class="stat-value"><?php echo number_format($realtime_stats['unread_alerts']); ?></div>
                <?php if ($realtime_stats['critical_alerts'] > 0): ?>
                    <div class="stat-sub critical"><?php echo $realtime_stats['critical_alerts']; ?> critical</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="nehtw-panel" style="margin-top:20px;">
        <h2><?php esc_html_e( 'Subscription Health', 'nehtw-gateway' ); ?></h2>
        <ul>
            <li><strong><?php esc_html_e( 'Active subscriptions:', 'nehtw-gateway' ); ?></strong> <?php echo esc_html( number_format_i18n( $subscription_health['active'] ) ); ?></li>
            <li><strong><?php esc_html_e( 'Overdue subscriptions:', 'nehtw-gateway' ); ?></strong> <?php echo esc_html( number_format_i18n( $subscription_health['overdue'] ) ); ?></li>
            <li><strong><?php esc_html_e( 'Pending payment retries:', 'nehtw-gateway' ); ?></strong> <?php echo esc_html( number_format_i18n( $subscription_health['pending_retries'] ) ); ?></li>
            <li><strong><?php esc_html_e( 'Last billing audit:', 'nehtw-gateway' ); ?></strong> <?php echo esc_html( Nehtw_Admin_Subscriptions::format_date( $subscription_health['last_billing_run'] ) ); ?></li>
        </ul>
    </div>
    
    <!-- Date Range Selector -->
    <div class="nehtw-date-range">
        <label>Date Range:</label>
        <button class="button range-btn active" data-range="7">Last 7 Days</button>
        <button class="button range-btn" data-range="30">Last 30 Days</button>
        <button class="button range-btn" data-range="90">Last 90 Days</button>
        <input type="date" id="custom-start-date" placeholder="Start Date">
        <input type="date" id="custom-end-date" placeholder="End Date">
        <button class="button" id="apply-custom-range">Apply</button>
    </div>
    
    <!-- Charts Section -->
    <div class="nehtw-charts-section">
        <div class="nehtw-chart-container">
            <h2>Orders Trend (Last 30 Days)</h2>
            <div id="loading-trend" class="loading">Loading chart...</div>
            <canvas id="orders-trend-chart"></canvas>
        </div>
        
        <div class="nehtw-chart-container">
            <h2>Revenue Trend (Last 30 Days)</h2>
            <div id="loading-revenue" class="loading">Loading chart...</div>
            <canvas id="revenue-trend-chart"></canvas>
        </div>
    </div>
    
    <!-- Two Column Layout -->
    <div class="nehtw-two-column">
        <!-- Top Providers -->
        <div class="nehtw-panel">
            <h2>Top Providers (Last 30 Days)</h2>
            <div id="loading-providers" class="loading">Loading providers...</div>
            <table id="providers-table" class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th>Orders</th>
                        <th>Success Rate</th>
                        <th>Avg Time</th>
                        <th>Revenue</th>
                    </tr>
                </thead>
                <tbody id="providers-tbody">
                    <!-- Loaded via JS -->
                </tbody>
            </table>
        </div>
        
        <!-- Recent Alerts -->
        <div class="nehtw-panel">
            <h2>Recent Alerts</h2>
            <button class="button button-small" id="mark-all-read" style="float: right;">Mark All Read</button>
            <div id="loading-alerts" class="loading">Loading alerts...</div>
            <div id="alerts-container">
                <!-- Loaded via JS -->
            </div>
        </div>
    </div>
    
    <!-- Recent Orders -->
    <div class="nehtw-panel">
        <h2>Recent Orders</h2>
        <div id="loading-orders" class="loading">Loading orders...</div>
        <table id="orders-table" class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Task ID</th>
                    <th>User</th>
                    <th>Provider</th>
                    <th>Status</th>
                    <th>Cost</th>
                    <th>Ordered At</th>
                    <th>Processing Time</th>
                </tr>
            </thead>
            <tbody id="orders-tbody">
                <!-- Loaded via JS -->
            </tbody>
        </table>
    </div>
</div>

<style>
/* Nehtw Dashboard Styles */
.nehtw-dashboard {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
}
.nehtw-dashboard-title {
    display: flex;
    align-items: center;
    gap: 12px;
    color: #0073aa;
    margin-bottom: 20px;
}
.nehtw-dashboard-actions {
    margin-bottom: 20px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
/* Stats Cards */
.nehtw-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 30px;
}
.nehtw-stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: box-shadow 0.2s;
}
.nehtw-stat-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.nehtw-stat-card.alert-critical {
    border-color: #dc3232;
    background: #fff8f8;
}
.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}
.stat-icon.pending {
    background: #f0f6fc;
    color: #0073aa;
}
.stat-icon.processing {
    background: #fff8e5;
    color: #f0b429;
}
.stat-icon.success {
    background: #ecf7ed;
    color: #46b450;
}
.stat-icon.revenue {
    background: #f3e7f5;
    color: #826eb4;
}
.stat-icon.users {
    background: #e5f5fa;
    color: #00a0d2;
}
.stat-icon.alerts {
    background: #fef7f1;
    color: #f56e28;
}
.stat-content {
    flex: 1;
}
.stat-label {
    font-size: 13px;
    color: #646970;
    margin-bottom: 4px;
}
.stat-value {
    font-size: 28px;
    font-weight: 600;
    color: #1d2327;
    line-height: 1;
}
.stat-sub {
    font-size: 12px;
    color: #646970;
    margin-top: 4px;
}
.stat-sub.critical {
    color: #dc3232;
    font-weight: 600;
}
/* Date Range */
.nehtw-date-range {
    margin-bottom: 20px;
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}
.range-btn.active {
    background: #0073aa;
    color: #fff;
    border-color: #0073aa;
}
/* Charts */
.nehtw-charts-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.nehtw-chart-container {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
}
.nehtw-chart-container h2 {
    margin-top: 0;
    font-size: 16px;
    color: #1d2327;
}
/* Two Column Layout */
.nehtw-two-column {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
/* Panels */
.nehtw-panel {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
}
.nehtw-panel h2 {
    margin-top: 0;
    font-size: 16px;
    color: #1d2327;
    border-bottom: 1px solid #f0f0f1;
    padding-bottom: 10px;
    margin-bottom: 15px;
}
/* Loading State */
.loading {
    text-align: center;
    padding: 40px;
    color: #646970;
}
/* Alert Item */
.alert-item {
    padding: 12px;
    border: 1px solid #dcdcde;
    border-radius: 4px;
    margin-bottom: 10px;
    display: flex;
    gap: 12px;
    align-items: start;
}
.alert-item.unread {
    background: #f0f6fc;
    border-left: 4px solid #0073aa;
}
.alert-item.severity-critical {
    background: #fff8f8;
    border-left: 4px solid #dc3232;
}
.alert-item.severity-warning {
    background: #fff8e5;
    border-left: 4px solid #f0b429;
}
.alert-icon {
    font-size: 20px;
}
.alert-content {
    flex: 1;
}
.alert-title {
    font-weight: 600;
    margin-bottom: 4px;
}
.alert-message {
    font-size: 13px;
    color: #646970;
    margin-bottom: 8px;
}
.alert-meta {
    font-size: 12px;
    color: #8c8f94;
}
.alert-actions {
    display: flex;
    gap: 8px;
}
/* Status Badges */
.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}
.status-badge.pending {
    background: #f0f6fc;
    color: #0073aa;
}
.status-badge.processing {
    background: #fff8e5;
    color: #f0b429;
}
.status-badge.ready {
    background: #ecf7ed;
    color: #46b450;
}
.status-badge.failed {
    background: #fef7f1;
    color: #dc3232;
}
/* Responsive */
@media (max-width: 782px) {
    .nehtw-stats-grid {
        grid-template-columns: 1fr;
    }
    
    .nehtw-charts-section,
    .nehtw-two-column {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    'use strict';
    
    let currentRange = 30; // Default 30 days
    
    // Load all data
    function loadDashboardData() {
        loadStats();
        loadProviders();
        loadAlerts();
        loadRecentOrders();
    }
    
    // Load statistics
    function loadStats() {
        const endDate = new Date().toISOString().split('T')[0];
        const startDate = new Date(Date.now() - (currentRange * 24 * 60 * 60 * 1000)).toISOString().split('T')[0];
        
        $.ajax({
            url: '<?php echo rest_url('nehtw/v1/admin/stats'); ?>',
            data: { start_date: startDate, end_date: endDate },
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function(data) {
                renderCharts(data);
            }
        });
    }
    
    // Render charts
    function renderCharts(data) {
        $('#loading-trend, #loading-revenue').hide();
        
        // Orders Trend Chart
        const ordersDates = data.daily_trend.map(d => d.date);
        const ordersData = data.daily_trend.map(d => parseInt(d.total_orders));
        const successData = data.daily_trend.map(d => parseInt(d.successful_orders));
        const failedData = data.daily_trend.map(d => parseInt(d.failed_orders));
        
        const ordersCtx = document.getElementById('orders-trend-chart');
        if (ordersCtx) {
            new Chart(ordersCtx, {
                type: 'line',
                data: {
                    labels: ordersDates,
                    datasets: [{
                        label: 'Total Orders',
                        data: ordersData,
                        borderColor: '#0073aa',
                        backgroundColor: 'rgba(0, 115, 170, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Successful',
                        data: successData,
                        borderColor: '#46b450',
                        backgroundColor: 'rgba(70, 180, 80, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Failed',
                        data: failedData,
                        borderColor: '#dc3232',
                        backgroundColor: 'rgba(220, 50, 50, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'top' }
                    }
                }
            });
        }
        
        // Revenue Trend Chart
        const revenueData = data.daily_trend.map(d => parseFloat(d.total_revenue));
        
        const revenueCtx = document.getElementById('revenue-trend-chart');
        if (revenueCtx) {
            new Chart(revenueCtx, {
                type: 'bar',
                data: {
                    labels: ordersDates,
                    datasets: [{
                        label: 'Revenue ($)',
                        data: revenueData,
                        backgroundColor: '#826eb4'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        }
    }
    
    // Load providers
    function loadProviders() {
        $.ajax({
            url: '<?php echo rest_url('nehtw/v1/admin/providers/performance'); ?>',
            data: { days: currentRange },
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function(data) {
                $('#loading-providers').hide();
                renderProviders(data);
            }
        });
    }
    
    // Render providers table
    function renderProviders(providers) {
        let html = '';
        providers.forEach(p => {
            const successRate = parseFloat(p.avg_success_rate).toFixed(1);
            const avgTime = Math.round(parseFloat(p.avg_processing_time));
            const revenue = parseFloat(p.total_revenue).toFixed(2);
            
            html += `
                <tr>
                    <td><strong>${p.provider}</strong></td>
                    <td>${p.total_orders}</td>
                    <td><span style="color: ${successRate >= 90 ? '#46b450' : successRate >= 70 ? '#f0b429' : '#dc3232'}">${successRate}%</span></td>
                    <td>${avgTime}s</td>
                    <td>$${revenue}</td>
                </tr>
            `;
        });
        $('#providers-tbody').html(html);
    }
    
    // Load alerts
    function loadAlerts() {
        $.ajax({
            url: '<?php echo rest_url('nehtw/v1/admin/alerts'); ?>',
            data: { unread_only: false, limit: 20 },
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function(data) {
                $('#loading-alerts').hide();
                renderAlerts(data);
            }
        });
    }
    
    // Render alerts
    function renderAlerts(alerts) {
        if (alerts.length === 0) {
            $('#alerts-container').html('<p>No alerts</p>');
            return;
        }
        
        let html = '';
        alerts.forEach(alert => {
            const isUnread = !parseInt(alert.is_read);
            const severityClass = 'severity-' + alert.severity;
            
            html += `
                <div class="alert-item ${isUnread ? 'unread' : ''} ${severityClass}" data-alert-id="${alert.id}">
                    <div class="alert-icon">⚠️</div>
                    <div class="alert-content">
                        <div class="alert-title">${alert.title}</div>
                        <div class="alert-message">${alert.message}</div>
                        <div class="alert-meta">${alert.created_at}</div>
                    </div>
                    <div class="alert-actions">
                        ${isUnread ? '<button class="button button-small mark-read">Mark Read</button>' : ''}
                        ${!parseInt(alert.is_resolved) ? '<button class="button button-small resolve-alert">Resolve</button>' : ''}
                    </div>
                </div>
            `;
        });
        $('#alerts-container').html(html);
    }
    
    // Load recent orders
    function loadRecentOrders() {
        $.ajax({
            url: '<?php echo rest_url('nehtw/v1/admin/orders/recent'); ?>',
            data: { limit: 20 },
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function(data) {
                $('#loading-orders').hide();
                renderOrders(data);
            }
        });
    }
    
    // Render orders table
    function renderOrders(orders) {
        let html = '';
        orders.forEach(order => {
            const processingTime = order.processing_time_seconds ? order.processing_time_seconds + 's' : '-';
            html += `
                <tr>
                    <td><code>${order.task_id}</code></td>
                    <td>${order.user_email}</td>
                    <td>${order.site}</td>
                    <td><span class="status-badge ${order.status}">${order.status}</span></td>
                    <td>$${parseFloat(order.cost).toFixed(2)}</td>
                    <td>${order.ordered_at}</td>
                    <td>${processingTime}</td>
                </tr>
            `;
        });
        $('#orders-tbody').html(html);
    }
    
    // Event handlers
    $('#refresh-dashboard').click(loadDashboardData);
    
    $('.range-btn').click(function() {
        $('.range-btn').removeClass('active');
        $(this).addClass('active');
        currentRange = parseInt($(this).data('range'));
        loadStats();
        loadProviders();
    });
    
    $(document).on('click', '.mark-read', function() {
        const alertId = $(this).closest('.alert-item').data('alert-id');
        $.ajax({
            url: '<?php echo rest_url('nehtw/v1/admin/alerts/'); ?>' + alertId + '/read',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function() {
                loadAlerts();
            }
        });
    });
    
    $(document).on('click', '.resolve-alert', function() {
        const alertId = $(this).closest('.alert-item').data('alert-id');
        $.ajax({
            url: '<?php echo rest_url('nehtw/v1/admin/alerts/'); ?>' + alertId + '/resolve',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', '<?php echo wp_create_nonce('wp_rest'); ?>');
            },
            success: function() {
                loadAlerts();
            }
        });
    });
    
    // Initial load
    loadDashboardData();
});
</script>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

