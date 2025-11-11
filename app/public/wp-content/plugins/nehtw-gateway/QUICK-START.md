# Quick Start Guide - Nehtw Billing System

## Installation Steps

### 1. Build React Component
```bash
cd app/public/wp-content/plugins/nehtw-gateway
npm install
npm run build
```

### 2. Activate Plugin
- Go to WordPress Admin → Plugins
- Deactivate "Nehtw Gateway" (if active)
- Activate "Nehtw Gateway"
- This creates all database tables

### 3. Configure Payment Gateway
- Go to **Nehtw Dashboard** → **Payment Gateways**
- Enter Stripe credentials (or PayPal)
- Set default gateway
- Save

### 4. Test System
- Access: `/wp-content/plugins/nehtw-gateway/test-billing-system.php`
- Verify all checks pass
- Test REST API endpoints
- Verify cron jobs are scheduled

## Key Features

✅ **Automatic Payment Retries** - 3 attempts over 7 days  
✅ **Progressive Dunning Emails** - 4-level email sequence  
✅ **Invoice Management** - Generation, PDF, tracking  
✅ **Subscription Controls** - Pause, resume, cancel, change plans  
✅ **Usage Tracking** - Overage billing support  
✅ **Complete Audit Trail** - Full subscription history  

## Next Steps

1. Install Stripe PHP library (if using Stripe)
2. Customize email templates in Dunning Manager
3. Test with real payment methods
4. Remove test file before production

See `TESTING-GUIDE.md` for detailed testing instructions.

