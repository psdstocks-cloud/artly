<?php
/**
 * Billing System Test Script
 * 
 * Run this script to test the billing system components
 * Access via: /wp-content/plugins/nehtw-gateway/test-billing-system.php
 * 
 * WARNING: Remove this file in production!
 */

// Load WordPress
require_once( dirname( __FILE__ ) . '/../../../wp-load.php' );

// Security check - only allow admins
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Unauthorized access' );
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Nehtw Billing System - Test Suite</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a1a; color: #fff; }
        .test-section { margin: 20px 0; padding: 15px; background: #2a2a2a; border-radius: 8px; }
        .test-result { margin: 10px 0; padding: 10px; border-radius: 4px; }
        .success { background: #2d5016; color: #90ee90; }
        .error { background: #501616; color: #ff6b6b; }
        .info { background: #1a3a5a; color: #87ceeb; }
        button { padding: 10px 20px; margin: 5px; cursor: pointer; }
        pre { background: #000; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Nehtw Billing System - Test Suite</h1>
    
    <div class="test-section">
        <h2>1. Database Tables Check</h2>
        <?php
        global $wpdb;
        $tables = [
            'nehtw_invoices',
            'nehtw_payment_attempts',
            'nehtw_subscription_history',
            'nehtw_dunning_emails',
            'nehtw_usage_tracking',
            'nehtw_payment_retries',
        ];
        
        foreach ( $tables as $table ) {
            $table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;
            
            if ( $exists ) {
                $count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
                echo "<div class='test-result success'>✓ Table $table_name exists ($count rows)</div>";
            } else {
                echo "<div class='test-result error'>✗ Table $table_name does NOT exist</div>";
            }
        }
        ?>
    </div>
    
    <div class="test-section">
        <h2>2. Class Loading Check</h2>
        <?php
        $classes = [
            'Nehtw_Invoice_Manager',
            'Nehtw_Payment_Retry',
            'Nehtw_Dunning_Manager',
            'Nehtw_Subscription_Manager',
            'Nehtw_Usage_Tracker',
            'Nehtw_Subscription_REST_API',
            'Nehtw_Billing_Cron',
        ];
        
        foreach ( $classes as $class ) {
            if ( class_exists( $class ) ) {
                echo "<div class='test-result success'>✓ Class $class loaded</div>";
            } else {
                echo "<div class='test-result error'>✗ Class $class NOT loaded</div>";
            }
        }
        ?>
    </div>
    
    <div class="test-section">
        <h2>3. REST API Endpoints Check</h2>
        <?php
        $rest_server = rest_get_server();
        $routes = $rest_server->get_routes();
        
        $nehtw_routes = array_filter( array_keys( $routes ), function( $route ) {
            return strpos( $route, '/nehtw/v1/' ) === 0;
        } );
        
        if ( ! empty( $nehtw_routes ) ) {
            echo "<div class='test-result success'>✓ Found " . count( $nehtw_routes ) . " Nehtw REST API routes:</div>";
            echo "<pre>";
            foreach ( $nehtw_routes as $route ) {
                echo "  - $route\n";
            }
            echo "</pre>";
        } else {
            echo "<div class='test-result error'>✗ No Nehtw REST API routes found</div>";
        }
        ?>
    </div>
    
    <div class="test-section">
        <h2>4. Cron Jobs Check</h2>
        <?php
        $cron_hooks = [
            'nehtw_process_payment_retries',
            'nehtw_check_dunning_schedule',
            'nehtw_check_expiry_warnings',
            'nehtw_process_subscription_renewals',
        ];
        
        foreach ( $cron_hooks as $hook ) {
            $next_run = wp_next_scheduled( $hook );
            if ( $next_run ) {
                $next_run_date = date( 'Y-m-d H:i:s', $next_run );
                echo "<div class='test-result success'>✓ Cron hook '$hook' scheduled (next: $next_run_date)</div>";
            } else {
                echo "<div class='test-result error'>✗ Cron hook '$hook' NOT scheduled</div>";
            }
        }
        ?>
    </div>
    
    <div class="test-section">
        <h2>5. Payment Gateway Settings</h2>
        <?php
        $stripe_key = get_option( 'nehtw_stripe_secret_key' );
        $paypal_id = get_option( 'nehtw_paypal_client_id' );
        $default_gateway = get_option( 'nehtw_default_payment_gateway', 'stripe' );
        
        echo "<div class='test-result info'>Default Gateway: $default_gateway</div>";
        
        if ( $stripe_key ) {
            $masked = substr( $stripe_key, 0, 7 ) . '...' . substr( $stripe_key, -4 );
            echo "<div class='test-result success'>✓ Stripe Secret Key configured ($masked)</div>";
        } else {
            echo "<div class='test-result error'>✗ Stripe Secret Key NOT configured</div>";
        }
        
        if ( $paypal_id ) {
            echo "<div class='test-result success'>✓ PayPal Client ID configured</div>";
        } else {
            echo "<div class='test-result error'>✗ PayPal Client ID NOT configured</div>";
        }
        ?>
    </div>
    
    <div class="test-section">
        <h2>6. Test Actions</h2>
        <button onclick="testRestAPI()">Test REST API</button>
        <button onclick="testCronJobs()">Test Cron Jobs</button>
        <button onclick="testInvoiceCreation()">Test Invoice Creation</button>
        <div id="test-results"></div>
    </div>
    
    <script>
        function testRestAPI() {
            const results = document.getElementById('test-results');
            results.innerHTML = '<div class="test-result info">Testing REST API endpoints...</div>';
            
            fetch('/wp-json/nehtw/v1/plans')
                .then(res => res.json())
                .then(data => {
                    results.innerHTML = '<div class="test-result success">✓ REST API /plans endpoint working</div><pre>' + JSON.stringify(data, null, 2) + '</pre>';
                })
                .catch(err => {
                    results.innerHTML = '<div class="test-result error">✗ REST API test failed: ' + err.message + '</div>';
                });
        }
        
        function testCronJobs() {
            const results = document.getElementById('test-results');
            results.innerHTML = '<div class="test-result info">Cron jobs are scheduled via WordPress. Check WP-Cron or use WP-CLI: wp cron event list</div>';
        }
        
        function testInvoiceCreation() {
            const results = document.getElementById('test-results');
            results.innerHTML = '<div class="test-result info">Invoice creation requires an active subscription. Test via subscription renewal process.</div>';
        }
    </script>
</body>
</html>

