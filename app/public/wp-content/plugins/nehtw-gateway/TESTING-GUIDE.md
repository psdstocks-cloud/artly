# Nehtw Billing System - Testing Guide

## 1. Build React Component

```bash
cd app/public/wp-content/plugins/nehtw-gateway
npm install
npm run build
```

This will create `assets/js/subscription-dashboard.min.js` from the JSX source.

## 2. Test Database Migration

### Option A: Via WordPress Admin
1. Go to **Plugins** → **Installed Plugins**
2. Deactivate the "Nehtw Gateway" plugin
3. Activate it again
4. This will run the activation hook and create all database tables

### Option B: Via Test Script
1. Access: `http://your-site.local/wp-content/plugins/nehtw-gateway/test-billing-system.php`
2. Check "Database Tables Check" section
3. All tables should show ✓ with 0 rows (if no data yet)

### Option C: Via WP-CLI
```bash
wp plugin deactivate nehtw-gateway
wp plugin activate nehtw-gateway
```

## 3. Configure Payment Gateway

### Stripe Setup
1. Go to **Nehtw Dashboard** → **Payment Gateways**
2. Enter your Stripe Secret Key (starts with `sk_`)
3. Enter your Stripe Publishable Key (starts with `pk_`)
4. Save settings

**Note:** You'll need to install the Stripe PHP library:
```bash
composer require stripe/stripe-php
# OR download from: https://github.com/stripe/stripe-php
# Place in: includes/libraries/stripe-php/
```

### PayPal Setup
1. Go to **Nehtw Dashboard** → **Payment Gateways**
2. Enter your PayPal Client ID
3. Enter your PayPal Secret
4. Select Mode (Sandbox or Live)
5. Save settings

## 4. Test REST API Endpoints

### Using Browser/Postman

**Get Subscription:**
```
GET /wp-json/nehtw/v1/subscription
Headers: Cookie (logged in user)
```

**Get Plans:**
```
GET /wp-json/nehtw/v1/plans
(No auth required)
```

**Get Invoices:**
```
GET /wp-json/nehtw/v1/invoices
Headers: Cookie (logged in user)
```

**Change Plan:**
```
POST /wp-json/nehtw/v1/subscription/change-plan
Headers: 
  Content-Type: application/json
  Cookie (logged in user)
Body: {
  "new_plan_key": "pro",
  "apply_immediately": true
}
```

### Using Test Script
Access the test script and click "Test REST API" button.

## 5. Test Cron Jobs

### Check Scheduled Events
```bash
wp cron event list | grep nehtw
```

Should show:
- `nehtw_process_payment_retries` (every hour)
- `nehtw_check_dunning_schedule` (daily)
- `nehtw_check_expiry_warnings` (daily)
- `nehtw_process_subscription_renewals` (every 6 hours)

### Manually Trigger Cron
```bash
# Payment retries
wp cron event run nehtw_process_payment_retries

# Dunning check
wp cron event run nehtw_check_dunning_schedule

# Expiry warnings
wp cron event run nehtw_check_expiry_warnings

# Subscription renewals
wp cron event run nehtw_process_subscription_renewals
```

### Via WordPress Admin
1. Install "WP Crontrol" plugin
2. Go to **Tools** → **Cron Events**
3. Find Nehtw events and click "Run Now"

## 6. Test Email Templates

### Trigger Test Emails
1. Create a test subscription with a failed payment
2. Or manually trigger dunning emails via code:

```php
// In functions.php or test file
$dunning = new Nehtw_Dunning_Manager();
$dunning->send_dunning_email( $invoice_id, 1 ); // Level 1
```

### Check Email Logs
- Check WordPress debug log: `wp-content/debug.log`
- Check server mail logs
- Use a mail testing service (Mailtrap, etc.)

## 7. Complete Test Checklist

- [ ] Database tables created successfully
- [ ] All classes load without errors
- [ ] REST API endpoints respond correctly
- [ ] Cron jobs are scheduled
- [ ] Payment gateway settings save correctly
- [ ] Stripe integration works (test with test cards)
- [ ] PayPal integration works (test in sandbox)
- [ ] Invoice creation works
- [ ] Payment retry logic works
- [ ] Dunning emails send correctly
- [ ] Subscription management works (pause, resume, cancel)
- [ ] Frontend React component loads
- [ ] Usage tracking records data

## 8. Production Checklist

Before going live:

- [ ] Remove `test-billing-system.php` file
- [ ] Set up real Stripe/PayPal credentials
- [ ] Test with real payment methods
- [ ] Configure email templates with your branding
- [ ] Set up proper error logging
- [ ] Test cron jobs on production server
- [ ] Verify database backups include new tables
- [ ] Test subscription renewal flow end-to-end
- [ ] Verify invoice PDF generation works
- [ ] Test all user-facing features

## Troubleshooting

### Tables Not Created
- Check WordPress debug log for errors
- Verify `dbDelta` has proper permissions
- Check database user has CREATE TABLE privileges

### REST API Returns 404
- Flush rewrite rules: `wp rewrite flush`
- Check permalink structure is not "Plain"
- Verify REST API is enabled

### Cron Jobs Not Running
- Check if WP-Cron is disabled (some hosts disable it)
- Use real cron: `*/5 * * * * wp cron event run --due-now`
- Check server timezone matches WordPress timezone

### Payment Gateway Errors
- Verify API keys are correct
- Check Stripe/PayPal library is installed
- Review error logs for specific error messages
- Test with sandbox/test credentials first

