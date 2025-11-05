# Artly WordPress Theme & Nehtw Gateway Plugin

A modern WordPress theme and plugin suite for a points-based wallet system with WooCommerce integration for stock asset downloads and AI image generation.

## 🎯 Project Overview

Artly is a custom WordPress theme with an integrated wallet system that allows users to purchase points and use them to download stock assets from various providers (Shutterstock, Adobe Stock, Depositphotos, etc.) and generate AI images.

## ✨ Features

### Theme Features
- **Modern Homepage** with GSAP animations and ScrollTrigger
- **Dynamic Pricing Page** with currency conversion (EGP/USD)
- **Custom Signup & Login Pages** with AJAX form submission
- **Light/Dark Mode Toggle** with theme persistence
- **Responsive Design** optimized for all devices
- **Cinematic Motion** using GSAP and Lottie animations

### Plugin Features (Nehtw Gateway)
- **Wallet System** - Points-based credit system
- **Stock Downloads** - Integration with Nehtw API for stock asset downloads
- **WooCommerce Integration** - Automatic wallet top-up via checkout
- **Transaction History** - User dashboard with complete transaction log
- **Currency Detection** - IP-based geo-location currency detection
- **Multi-Currency Support** - EGP and USD with real-time conversion

## 🏗️ Project Structure

```
artly/
├── .gitignore
├── README.md
└── app/
    └── public/
        └── wp-content/
            ├── themes/
            │   └── artly/              # Custom WordPress theme
            │       ├── assets/
            │       │   ├── css/        # Stylesheets
            │       │   └── js/         # JavaScript files
            │       ├── functions.php   # Theme functions
            │       ├── front-page.php  # Homepage template
            │       ├── page-pricing.php # Pricing page
            │       ├── page-signup.php  # Signup page
            │       └── page-login.php   # Login page
            └── plugins/
                └── nehtw-gateway/      # Custom plugin
                    ├── includes/
                    │   ├── class-artly-woocommerce-points.php
                    │   └── class-nehtw-stock-orders.php
                    ├── assets/
                    │   ├── css/
                    │   └── js/
                    └── nehtw-gateway.php
```

## 📋 Requirements

- **WordPress**: 5.8 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher
- **WooCommerce**: 7.0 or higher (for payment integration)
- **Local by Flywheel** (for local development) or similar WordPress environment

## 🚀 Installation

### 1. Clone the Repository

```bash
git clone https://github.com/your-username/artly.git
cd artly
```

### 2. WordPress Setup

1. Install WordPress in your local environment (Local by Flywheel, XAMPP, etc.)
2. Copy the theme and plugin files to your WordPress installation:
   - Theme: Copy `app/public/wp-content/themes/artly/` to your WordPress `wp-content/themes/`
   - Plugin: Copy `app/public/wp-content/plugins/nehtw-gateway/` to your WordPress `wp-content/plugins/`

### 3. Database Setup

The plugin will automatically create the necessary database tables on activation:
- `wp_nehtw_wallet_transactions` - Wallet transaction history
- `wp_nehtw_stock_orders` - Stock download orders
- `wp_nehtw_ai_jobs` - AI image generation jobs
- `wp_nehtw_stock_sites` - Supported stock site configurations

### 4. Activate Theme & Plugin

1. Go to **WordPress Admin → Appearance → Themes**
2. Activate the **Artly** theme
3. Go to **Plugins**
4. Activate **Nehtw Gateway**

### 5. WooCommerce Setup

1. Install and activate **WooCommerce**
2. Create a product for wallet top-ups:
   - Go to **Products → Add New**
   - Name: "Wallet points top-up"
   - Type: Simple product
   - Virtual: ✅ Checked
   - Price: 0 (will be set dynamically)
   - Note the Product ID (e.g., 25)
3. Configure the product ID:
   ```php
   update_option('artly_woocommerce_product_id', YOUR_PRODUCT_ID);
   ```

### 6. Configure Pages

Create the following pages in WordPress:
- **Pricing** (slug: `pricing`) - Uses `page-pricing.php` template
- **Signup** (slug: `signup`) - Uses `page-signup.php` template
- **Login** (slug: `login`) - Uses `page-login.php` template
- **My Downloads** (slug: `my-downloads`) - Add shortcode: `[nehtw_gateway_my_downloads]`

## 🔧 Configuration

### Currency Detection

The system automatically detects user currency based on IP geolocation. To configure:

1. The system uses `ipapi.co` for geolocation (free tier)
2. Currency preference is stored in cookies and localStorage
3. Default currency: EGP (for Egypt-based service)

### WooCommerce Product ID

Set the wallet top-up product ID:
```php
update_option('artly_woocommerce_product_id', 25);
```

### Nehtw API Configuration

API key is defined in `nehtw-gateway.php`:
```php
define( 'NEHTW_GATEWAY_API_KEY', 'YOUR_API_KEY' );
```

## 🎨 Theme Features

### Pricing Page
- Dynamic points calculator (1-500 points)
- Tiered pricing system
- Currency toggle (EGP/USD)
- Light/Dark mode toggle
- GSAP scroll animations
- FAQ section

### User Authentication
- Custom signup page with AJAX submission
- Custom login page with password visibility toggle
- Auto-login after signup
- Redirect to custom login page

### Dashboard
- Wallet balance display
- Transaction history with order links
- Stock downloader interface
- React-based UI components

## 💳 Payment Flow

1. User selects points on pricing page
2. Clicks "Subscribe to X points / month"
3. Redirected to WooCommerce cart
4. Completes checkout
5. Wallet automatically credited with points
6. Transaction recorded in dashboard

## 🔐 Security Notes

- **Never commit** `wp-config.php` (already in .gitignore)
- API keys should be stored in environment variables or wp-config.php
- User passwords are hashed using WordPress's built-in functions
- All form inputs are sanitized and validated

## 📦 Dependencies

### Frontend Libraries
- **GSAP 3.12.5** - Animation library (loaded via CDN)
- **ScrollTrigger** - GSAP plugin for scroll animations
- **Lottie** - Optional animation support

### WordPress Plugins
- **WooCommerce** - E-commerce and payment processing
- **WordPress Core** - Required for theme and plugin functionality

## 🛠️ Development

### File Structure

**Theme Files:**
- `functions.php` - Theme setup, asset enqueuing, currency detection
- `front-page.php` - Homepage template
- `page-pricing.php` - Pricing page with calculator
- `page-signup.php` - User registration page
- `page-login.php` - User login page
- `assets/css/` - Stylesheets
- `assets/js/` - JavaScript files

**Plugin Files:**
- `nehtw-gateway.php` - Main plugin file
- `includes/class-artly-woocommerce-points.php` - WooCommerce integration
- `includes/class-nehtw-stock-orders.php` - Stock order management
- `assets/js/nehtw-dashboard.js` - React dashboard component

### Code Standards
- Follow WordPress coding standards
- Use WordPress functions for database operations
- Sanitize all user inputs
- Escape all outputs

## 🐛 Troubleshooting

### Price Shows 0 in Cart
1. Clear WooCommerce transients
2. Verify product ID is correct
3. Check browser console for errors
4. Ensure WooCommerce "Coming Soon" mode is disabled

### Currency Not Detecting
- Check IP geolocation API is accessible
- Verify cookie/localStorage is working
- Check browser console for errors

### Transactions Not Showing
- Verify database tables exist
- Check user is logged in
- Review plugin activation logs

## 📝 License

This project is licensed under the GNU General Public License v2 or later.

## 👥 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## 📞 Support

For issues and questions, please open an issue on GitHub.

## 🎯 Roadmap

- [ ] WooCommerce Subscriptions integration (recurring top-ups)
- [ ] Multi-currency support for more currencies (EUR, GBP)
- [ ] Advanced coupon system with bonus points
- [ ] Email notifications for wallet transactions
- [ ] Mobile app API endpoints

---

**Version:** 1.0.7  
**Last Updated:** 2025

