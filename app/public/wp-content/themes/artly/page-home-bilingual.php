<?php
/**
 * Template Name: Bilingual Homepage
 * 
 * Bilingual (English/Arabic) homepage template with RTL support
 * and enhanced animations.
 */

get_header();
$lang = artly_get_current_language();
$direction = artly_get_language_direction();
?>

<main class="artly-front artly-front-bilingual" dir="<?php echo esc_attr( $direction ); ?>" lang="<?php echo esc_attr( $lang ); ?>">
    <!-- HERO -->
    <section class="artly-hero artly-hero-bilingual">
        <div class="artly-container artly-hero-inner">
            <!-- Left: copy -->
            <div class="artly-hero-copy">
                <p class="artly-eyebrow"><?php esc_html_e( 'For visual teams & solo creators', 'artly' ); ?></p>
                <h1 class="artly-hero-title">
                    <?php esc_html_e( 'Stop Wasting Money on Multiple Stock Subscriptions', 'artly' ); ?>
                </h1>
                <p class="artly-hero-sub">
                    <?php esc_html_e( 'One wallet. Every major stock site. Download in seconds. Re-download forever for free. Save up to 40% on your stock budget.', 'artly' ); ?>
                </p>

                <!-- Social Proof Strip -->
                <div class="artly-hero-social-proof">
                    <div class="artly-proof-item">
                        <span class="artly-proof-icon">⭐</span>
                        <span><strong>4.9/5</strong> <?php esc_html_e( 'from 500+ creators', 'artly' ); ?></span>
                    </div>
                    <div class="artly-proof-item">
                        <span class="artly-proof-icon">✓</span>
                        <span><strong>50,000+</strong> <?php esc_html_e( 'files downloaded', 'artly' ); ?></span>
                    </div>
                    <div class="artly-proof-item">
                        <span class="artly-proof-icon">💳</span>
                        <span><?php esc_html_e( 'No credit card required', 'artly' ); ?></span>
                    </div>
                </div>

                <div class="artly-hero-actions">
                    <a href="<?php echo esc_url( site_url( '/signup/' ) ); ?>" class="artly-btn artly-btn-primary">
                        <?php esc_html_e( 'Start Free 14-Day Trial', 'artly' ); ?>
                        <span class="artly-btn-arrow">→</span>
                    </a>
                    <a href="<?php echo esc_url( site_url( '/pricing/' ) ); ?>" class="artly-btn artly-btn-ghost">
                        <?php esc_html_e( 'View Pricing', 'artly' ); ?>
                    </a>
                </div>
                <p class="artly-hero-meta">
                    <?php esc_html_e( '14-day money-back guarantee · No long-term contracts · Designed for creatives', 'artly' ); ?>
                </p>
            </div>
            <!-- Right: downloader card mock -->
            <div class="artly-hero-card">
                <div class="artly-card-header">
                    <div class="artly-card-dot-row">
                        <span class="artly-dot"></span>
                        <span class="artly-dot"></span>
                        <span class="artly-dot"></span>
                    </div>
                    <div class="artly-card-app">
                        <span class="artly-card-app-title"><?php esc_html_e( 'Artly Studio', 'artly' ); ?></span>
                        <span class="artly-card-app-sub"><?php esc_html_e( 'Stock & AI downloads', 'artly' ); ?></span>
                    </div>
                </div>
                <div class="artly-card-body">
                    <div class="artly-card-row artly-card-row-balance">
                        <div>
                            <p class="artly-card-label"><?php esc_html_e( 'Wallet balance', 'artly' ); ?></p>
                            <p class="artly-card-balance" id="artly-hero-balance">0</p>
                        </div>
                        <div class="artly-card-balance-meta">
                            <span><?php esc_html_e( '1 point ≈ 20 EGP', 'artly' ); ?></span>
                            <span><?php esc_html_e( 'subscriptions & pay-as-you-go', 'artly' ); ?></span>
                        </div>
                    </div>
                    <div class="artly-card-row">
                        <div class="artly-card-row-header">
                            <p class="artly-card-title"><?php esc_html_e( 'Smart downloader', 'artly' ); ?></p>
                            <p class="artly-card-sub">
                                <?php esc_html_e( 'Paste a stock URL. We detect the site & ID automatically.', 'artly' ); ?>
                            </p>
                        </div>
                        <form class="artly-downloader-form" action="javascript:void(0);">
                            <label for="hero-stock-url" class="artly-field-label">
                                <?php esc_html_e( 'Paste stock URL', 'artly' ); ?>
                            </label>
                            <input
                                type="url"
                                id="hero-stock-url"
                                class="artly-input"
                                placeholder="<?php esc_attr_e( 'https://www.shutterstock.com/... or https://stock.adobe.com/...', 'artly' ); ?>"
                            />
                            <button type="submit" class="artly-btn artly-btn-gradient">
                                <?php esc_html_e( 'Generate download link', 'artly' ); ?>
                            </button>
                            <p class="artly-field-hint">
                                <?php esc_html_e( 'Live orders are available once you create a free Artly account.', 'artly' ); ?>
                            </p>
                        </form>
                    </div>
                    <div class="artly-card-row artly-card-row-footer">
                        <div>
                            <p class="artly-card-footer-title"><?php esc_html_e( 'Download history', 'artly' ); ?></p>
                            <p class="artly-card-footer-text">
                                <?php esc_html_e( 'Re-download any past order for free, directly from your dashboard.', 'artly' ); ?>
                            </p>
                        </div>
                        <div>
                            <p class="artly-card-footer-title"><?php esc_html_e( 'Need help?', 'artly' ); ?></p>
                            <p class="artly-card-footer-text">
                                <?php esc_html_e( 'Our team speaks "design". Share your link and we\'ll help you get the file.', 'artly' ); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div><!-- /.artly-hero-card -->
        </div>
    </section>

    <!-- STATS -->
    <section class="artly-section artly-section-stats">
        <div class="artly-container">
            <div class="artly-stats-grid">
                <div class="artly-stat-item" data-animate>
                    <p class="artly-stat-number" data-value="50000">0</p>
                    <p class="artly-stat-label"><?php esc_html_e( 'Downloads', 'artly' ); ?></p>
                </div>
                <div class="artly-stat-item" data-animate>
                    <p class="artly-stat-number" data-value="500">0</p>
                    <p class="artly-stat-label"><?php esc_html_e( 'Happy Creators', 'artly' ); ?></p>
                </div>
                <div class="artly-stat-item" data-animate>
                    <p class="artly-stat-number" data-value="40">0</p>
                    <p class="artly-stat-label"><?php esc_html_e( 'Average Savings', 'artly' ); ?> %</p>
                </div>
                <div class="artly-stat-item" data-animate>
                    <p class="artly-stat-number" data-value="6">0</p>
                    <p class="artly-stat-label"><?php esc_html_e( 'Stock Sites Supported', 'artly' ); ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- TESTIMONIALS -->
    <section class="artly-section artly-section-testimonials">
        <div class="artly-container">
            <header class="artly-section-header">
                <p class="artly-eyebrow"><?php esc_html_e( 'Loved by creatives', 'artly' ); ?></p>
                <h2><?php esc_html_e( 'See why 500+ teams trust Artly', 'artly' ); ?></h2>
            </header>

            <div class="artly-testimonials-grid">
                <article class="artly-testimonial-card" data-animate>
                    <div class="artly-testimonial-stars">★★★★★</div>
                    <p class="artly-testimonial-text">
                        "<?php esc_html_e( 'Artly cut our stock budget by 35%. We used to have 4 different subscriptions. Now it\'s all in one place. Game changer.', 'artly' ); ?>"
                    </p>
                    <div class="artly-testimonial-author">
                        <div class="artly-testimonial-avatar">SM</div>
                        <div>
                            <p class="artly-testimonial-name"><?php esc_html_e( 'Sarah Martinez', 'artly' ); ?></p>
                            <p class="artly-testimonial-role"><?php esc_html_e( 'Creative Director, Brand Studio', 'artly' ); ?></p>
                        </div>
                    </div>
                </article>

                <article class="artly-testimonial-card" data-animate>
                    <div class="artly-testimonial-stars">★★★★★</div>
                    <p class="artly-testimonial-text">
                        "<?php esc_html_e( 'Finally, no more hunting for that file I downloaded 3 months ago. Download history is brilliant. Re-download any time for free!', 'artly' ); ?>"
                    </p>
                    <div class="artly-testimonial-author">
                        <div class="artly-testimonial-avatar">AK</div>
                        <div>
                            <p class="artly-testimonial-name"><?php esc_html_e( 'Ahmed Khaled', 'artly' ); ?></p>
                            <p class="artly-testimonial-role"><?php esc_html_e( 'Freelance Designer, Cairo', 'artly' ); ?></p>
                        </div>
                    </div>
                </article>

                <article class="artly-testimonial-card" data-animate>
                    <div class="artly-testimonial-stars">★★★★★</div>
                    <p class="artly-testimonial-text">
                        "<?php esc_html_e( 'The Arabic support and RTL layout are perfect. First stock tool that actually works for our bilingual team.', 'artly' ); ?>"
                    </p>
                    <div class="artly-testimonial-author">
                        <div class="artly-testimonial-avatar">LA</div>
                        <div>
                            <p class="artly-testimonial-name"><?php esc_html_e( 'Layla Al-Sayed', 'artly' ); ?></p>
                            <p class="artly-testimonial-role"><?php esc_html_e( 'Art Director, Digital Agency', 'artly' ); ?></p>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <!-- HOW IT WORKS -->
    <section class="artly-section artly-section-steps">
        <div class="artly-container">
            <header class="artly-section-header">
                <h2><?php esc_html_e( 'How Artly works', 'artly' ); ?></h2>
                <p><?php esc_html_e( 'Your visual assets flow through one wallet. A typical project looks like this.', 'artly' ); ?></p>
            </header>
            <div class="artly-steps-grid">
                <article class="artly-step-card" data-animate>
                    <div class="artly-step-icon-wrapper">
                        <svg class="artly-step-icon-svg" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M21 18v1c0 1.1-.9 2-2 2H5c-1.11 0-2-.9-2-2V5c0-1.1.89-2 2-2h14c1.1 0 2 .9 2 2v1h-9c-1.11 0-2 .9-2 2v8c0 1.1.89 2 2 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>
                        </svg>
                        <span class="artly-step-number">1</span>
                    </div>
                    <h3><?php esc_html_e( 'Sign Up Free', 'artly' ); ?></h3>
                    <p>
                        <?php esc_html_e( 'Create your account in 30 seconds. No credit card required.', 'artly' ); ?>
                    </p>
                </article>
                <article class="artly-step-card" data-animate>
                    <div class="artly-step-icon-wrapper">
                        <svg class="artly-step-icon-svg" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/>
                        </svg>
                        <span class="artly-step-number">2</span>
                    </div>
                    <h3><?php esc_html_e( 'Add Points', 'artly' ); ?></h3>
                    <p>
                        <?php esc_html_e( 'Top up your wallet with points. 1 point = ~20 EGP. Pay only for what you use.', 'artly' ); ?>
                    </p>
                </article>
                <article class="artly-step-card" data-animate>
                    <div class="artly-step-icon-wrapper">
                        <svg class="artly-step-icon-svg" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
                        </svg>
                        <span class="artly-step-number">3</span>
                    </div>
                    <h3><?php esc_html_e( 'Download Instantly', 'artly' ); ?></h3>
                    <p>
                        <?php esc_html_e( 'Paste any stock URL. We handle the rest. Re-download forever for free.', 'artly' ); ?>
                    </p>
                </article>
            </div>
        </div>
    </section>

    <!-- FEATURES GRID -->
    <section class="artly-section artly-section-benefits">
        <div class="artly-container">
            <header class="artly-section-header">
                <h2><?php esc_html_e( 'Why teams switch to Artly', 'artly' ); ?></h2>
            </header>
            <div class="artly-benefits-grid">
                <article class="artly-benefit" data-animate>
                    <h3><?php esc_html_e( 'One wallet, all sites', 'artly' ); ?></h3>
                    <p>
                        <?php esc_html_e( 'Single balance for Shutterstock, Adobe Stock, Freepik & more.', 'artly' ); ?>
                    </p>
                </article>
                <article class="artly-benefit" data-animate>
                    <h3><?php esc_html_e( 'Smart URL detection', 'artly' ); ?></h3>
                    <p>
                        <?php esc_html_e( 'Paste any stock URL, we auto-detect site & ID.', 'artly' ); ?>
                    </p>
                </article>
                <article class="artly-benefit artly-benefit-featured" data-animate>
                    <span class="artly-benefit-badge"><?php esc_html_e( 'Most Popular', 'artly' ); ?></span>
                    <h3><?php esc_html_e( 'Download history', 'artly' ); ?></h3>
                    <p>
                        <?php esc_html_e( 'Re-download any file for free, anytime.', 'artly' ); ?>
                    </p>
                </article>
                <article class="artly-benefit" data-animate>
                    <h3><?php esc_html_e( 'Pay as you go', 'artly' ); ?></h3>
                    <p>
                        <?php esc_html_e( 'No subscriptions. Top up points when you need them.', 'artly' ); ?>
                    </p>
                </article>
                <article class="artly-benefit" data-animate>
                    <h3><?php esc_html_e( 'RTL & dark mode', 'artly' ); ?></h3>
                    <p>
                        <?php esc_html_e( 'Arabic/English switch with right-to-left layouts.', 'artly' ); ?>
                    </p>
                </article>
                <article class="artly-benefit" data-animate>
                    <h3><?php esc_html_e( 'Licensing friendly', 'artly' ); ?></h3>
                    <p>
                        <?php esc_html_e( 'Files fetched from official providers with your licenses.', 'artly' ); ?>
                    </p>
                </article>
            </div>
        </div>
    </section>

    <!-- SUPPORTED STOCK SITES -->
    <section class="artly-section artly-section-sites">
        <div class="artly-container">
            <header class="artly-section-header">
                <h2><?php esc_html_e( 'Supported Stock Sites', 'artly' ); ?></h2>
                <p><?php esc_html_e( 'Works with all major stock providers', 'artly' ); ?></p>
            </header>
            <div class="artly-sites-carousel" id="artly-sites-carousel">
                <?php
                $sites_config = function_exists( 'nehtw_gateway_get_stock_sites_config' )
                    ? nehtw_gateway_get_stock_sites_config()
                    : array();
                
                $major_sites = array( 'shutterstock', 'adobestock', 'freepik', 'envato', 'istock', 'depositphotos' );
                
                foreach ( $major_sites as $site_key ) {
                    if ( isset( $sites_config[ $site_key ] ) ) {
                        $site = $sites_config[ $site_key ];
                        $label = ! empty( $site['label'] ) ? $site['label'] : ucfirst( $site_key );
                        $url = ! empty( $site['url'] ) ? $site['url'] : '#';
                        ?>
                        <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer" class="artly-site-logo" data-site="<?php echo esc_attr( $site_key ); ?>">
                            <span class="artly-site-logo-text"><?php echo esc_html( $label ); ?></span>
                        </a>
                        <?php
                    }
                }
                ?>
            </div>
        </div>
    </section>

    <!-- PRICING TEASER -->
    <section class="artly-section artly-section-pricing-strip">
        <div class="artly-container artly-pricing-strip">
            <div class="artly-pricing-strip-content">
                <p class="artly-eyebrow"><?php esc_html_e( 'Transparent pricing', 'artly' ); ?></p>
                <h2><?php esc_html_e( 'Plans that scale with your team', 'artly' ); ?></h2>

                <!-- Price Preview -->
                <div class="artly-price-preview">
                    <div class="artly-price-option">
                        <p class="artly-price-label"><?php esc_html_e( 'Starter', 'artly' ); ?></p>
                        <p class="artly-price-amount">500 <?php esc_html_e( 'EGP', 'artly' ); ?><span>/<?php esc_html_e( 'month', 'artly' ); ?></span></p>
                        <p class="artly-price-points">25 <?php esc_html_e( 'points', 'artly' ); ?> · ~25 <?php esc_html_e( 'downloads', 'artly' ); ?></p>
                    </div>
                    <div class="artly-price-option artly-price-option-popular">
                        <span class="artly-price-badge"><?php esc_html_e( 'Most Popular', 'artly' ); ?></span>
                        <p class="artly-price-label"><?php esc_html_e( 'Pro', 'artly' ); ?></p>
                        <p class="artly-price-amount">1,500 <?php esc_html_e( 'EGP', 'artly' ); ?><span>/<?php esc_html_e( 'month', 'artly' ); ?></span></p>
                        <p class="artly-price-points">100 <?php esc_html_e( 'points', 'artly' ); ?> · ~100 <?php esc_html_e( 'downloads', 'artly' ); ?></p>
                    </div>
                    <div class="artly-price-option">
                        <p class="artly-price-label"><?php esc_html_e( 'Team', 'artly' ); ?></p>
                        <p class="artly-price-amount"><?php esc_html_e( 'Custom', 'artly' ); ?></p>
                        <p class="artly-price-points"><?php esc_html_e( 'Unlimited downloads', 'artly' ); ?></p>
                    </div>
                </div>

                <p class="artly-pricing-guarantee">
                    💳 <?php esc_html_e( 'No credit card required', 'artly' ); ?> · <?php esc_html_e( '14-day money-back guarantee', 'artly' ); ?>
                </p>
            </div>

            <div class="artly-pricing-strip-cta">
                <a href="<?php echo esc_url( site_url( '/signup/' ) ); ?>" class="artly-btn artly-btn-primary">
                    <?php esc_html_e( 'Start Free Trial', 'artly' ); ?>
                    <span class="artly-btn-arrow">→</span>
                </a>
                <a href="<?php echo esc_url( site_url( '/pricing/' ) ); ?>" class="artly-pricing-details-link">
                    <?php esc_html_e( 'View detailed pricing', 'artly' ); ?> →
                </a>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section class="artly-section artly-section-faq">
        <div class="artly-container">
            <header class="artly-section-header">
                <h2><?php esc_html_e( 'Frequently asked questions', 'artly' ); ?></h2>
                <p><?php esc_html_e( 'Everything you need to know about Artly', 'artly' ); ?></p>
            </header>

            <div class="artly-faq-grid">
                <details class="artly-faq-item">
                    <summary class="artly-faq-question">
                        <?php esc_html_e( 'How does the point system work?', 'artly' ); ?>
                        <span class="artly-faq-icon">+</span>
                    </summary>
                    <div class="artly-faq-answer">
                        <p><?php esc_html_e( '1 point = ~20 EGP and gets you one standard stock download. Premium assets may require more points. Points never expire, and you can reload anytime.', 'artly' ); ?></p>
                    </div>
                </details>

                <details class="artly-faq-item">
                    <summary class="artly-faq-question">
                        <?php esc_html_e( 'Which stock sites do you support?', 'artly' ); ?>
                        <span class="artly-faq-icon">+</span>
                    </summary>
                    <div class="artly-faq-answer">
                        <p><?php esc_html_e( 'We support Shutterstock, Adobe Stock, Freepik, iStock, Envato Elements, and more. We\'re constantly adding new providers.', 'artly' ); ?></p>
                    </div>
                </details>

                <details class="artly-faq-item">
                    <summary class="artly-faq-question">
                        <?php esc_html_e( 'Can I really re-download files for free?', 'artly' ); ?>
                        <span class="artly-faq-icon">+</span>
                    </summary>
                    <div class="artly-faq-answer">
                        <p><?php esc_html_e( 'Yes! Once you download a file, it\'s saved in your history forever. Re-download it any time without spending additional points.', 'artly' ); ?></p>
                    </div>
                </details>

                <details class="artly-faq-item">
                    <summary class="artly-faq-question">
                        <?php esc_html_e( 'Is there a free trial?', 'artly' ); ?>
                        <span class="artly-faq-icon">+</span>
                    </summary>
                    <div class="artly-faq-answer">
                        <p><?php esc_html_e( 'Absolutely! Start with a 14-day free trial. No credit card required to sign up. Try all features risk-free.', 'artly' ); ?></p>
                    </div>
                </details>

                <details class="artly-faq-item">
                    <summary class="artly-faq-question">
                        <?php esc_html_e( 'What if I need a refund?', 'artly' ); ?>
                        <span class="artly-faq-icon">+</span>
                    </summary>
                    <div class="artly-faq-answer">
                        <p><?php esc_html_e( 'We offer a 14-day money-back guarantee. If you\'re not happy, contact us and we\'ll issue a full refund, no questions asked.', 'artly' ); ?></p>
                    </div>
                </details>

                <details class="artly-faq-item">
                    <summary class="artly-faq-question">
                        <?php esc_html_e( 'Do you offer team/agency plans?', 'artly' ); ?>
                        <span class="artly-faq-icon">+</span>
                    </summary>
                    <div class="artly-faq-answer">
                        <p><?php esc_html_e( 'Yes! We have special pricing for teams of 5+ users. Contact us for custom quotes with volume discounts and dedicated support.', 'artly' ); ?></p>
                    </div>
                </details>
            </div>

            <div class="artly-faq-footer">
                <p><?php esc_html_e( 'Still have questions?', 'artly' ); ?></p>
                <a href="<?php echo esc_url( site_url( '/contact/' ) ); ?>" class="artly-btn artly-btn-ghost"><?php esc_html_e( 'Contact Support', 'artly' ); ?></a>
            </div>
        </div>
    </section>

    <!-- STORY / BRAND -->
    <section class="artly-section artly-section-story">
        <div class="artly-container">
            <header class="artly-section-header">
                <h2><?php esc_html_e( 'Built by designers who were tired of messy stock workflows', 'artly' ); ?></h2>
            </header>
            <div class="artly-story-body">
                <p>
                    <?php esc_html_e( 'Artly started as an internal tool for our own studio. We loved premium stock libraries, but hated tracking credits, invoices and logins for every site on every project.', 'artly' ); ?>
                </p>
                <p>
                    <?php esc_html_e( 'So we built one wallet, one dashboard and one download flow for the whole team — and decided to open it to other artists, studios and agencies who felt the same pain.', 'artly' ); ?>
                </p>
                <p class="artly-story-signoff">— <?php esc_html_e( 'Team Artly', 'artly' ); ?></p>
            </div>
            <div class="artly-story-cta">
                <a href="<?php echo esc_url( site_url( '/signup/' ) ); ?>" class="artly-btn artly-btn-primary">
                    <?php esc_html_e( 'Start Your Free Trial Today', 'artly' ); ?>
                    <span class="artly-btn-arrow">→</span>
                </a>
            </div>
        </div>
    </section>
</main>

<?php
get_footer();

