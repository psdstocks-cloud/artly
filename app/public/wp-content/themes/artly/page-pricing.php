<?php
/**
 * Template Name: Pricing
 * Description: Dynamic point pricing + subscriptions.
 */

get_header();
?>

<main id="primary" class="site-main artly-pricing-page">

    <!-- HERO -->
    <section class="pricing-hero">
        <!-- Pricing Toolbar -->
        <div class="pricing-toolbar">
            <button id="currencyToggle" class="currency-btn" aria-label="Toggle currency" title="Toggle currency">
                <span id="currencyDisplay">EGP</span>
            </button>
            <button id="modeToggle" class="mode-btn" aria-label="Toggle theme" title="Toggle light/dark mode">
                <span id="modeIcon">🌞</span>
            </button>
        </div>
        
        <p class="eyebrow">Simple, Flexible Credits</p>
        <h1>Choose Your Pricing Model</h1>
        <p class="sub">
            Select a ready-made plan or customize your own — scale up as your creative needs grow.
        </p>
    </section>

    <!-- TAB SWITCHER -->
    <div class="pricing-tabs-wrapper">
        <div class="pricing-tabs" role="tablist">
            <button 
                class="pricing-tab active" 
                data-tab="dynamic" 
                role="tab" 
                aria-selected="true"
                aria-controls="dynamic-pricing-panel"
            >
                <svg class="tab-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M2 4h12M2 8h12M2 12h8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                <span>Pay As You Go</span>
            </button>
            <button 
                class="pricing-tab" 
                data-tab="subscriptions" 
                role="tab" 
                aria-selected="false"
                aria-controls="subscriptions-panel"
            >
                <svg class="tab-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="3" y="2" width="10" height="12" rx="1" stroke="currentColor" stroke-width="1.5"/>
                    <path d="M3 6h10" stroke="currentColor" stroke-width="1.5"/>
                </svg>
                <span>Monthly Subscriptions</span>
            </button>
        </div>
    </div>

    <!-- TAB CONTENT -->
    <div class="pricing-content-wrapper">
        <!-- SECTION 1: DYNAMIC PRICING (Pay As You Go) -->
        <section 
            id="dynamic-pricing-panel" 
            class="pricing-tab-content active" 
            role="tabpanel" 
            aria-labelledby="dynamic-tab"
        >
            <div class="pricing-calculator-wrapper">
                <div class="pricing-calculator">
                    <h2>How many points do you need?</h2>
                    <p class="section-subtitle">Choose your points and validity period. The more you buy, the less you pay per point!</p>

                    <div class="pricing-grid">
                        <div class="pricing-controls">
                            <p class="hint">
                                Drag the slider or enter a number directly. Billing is monthly — cancel any time.
                            </p>

                            <div class="tier-labels">
                                <span>Starter</span>
                                <span>Creator</span>
                                <span>Studio</span>
                                <span>Agency</span>
                                <span>Enterprise</span>
                            </div>

                            <input id="pointsRange" type="range" min="1" max="500" step="1" value="120" data-points-slider />

                            <div class="points-input-row">
                                <label for="pointsInput">Points per month</label>
                                <input id="pointsInput" type="number" min="1" max="500" step="1" value="120" />
                                <span class="unit">points / month</span>
                            </div>
                        </div>

                        <div class="pricing-summary">
                            <p class="badge" id="tierBadge">Studio · Most popular</p>
                            <p class="tagline" id="tierTagline">Perfect for busy designers and small studios.</p>

                            <dl class="summary-list">
                                <div>
                                    <dt>Price per point</dt>
                                    <dd id="pricePerPoint"><span class="price-value">$0.32</span> / point</dd>
                                </div>
                                <div>
                                    <dt>Monthly total</dt>
                                    <dd id="monthlyTotal">$38.40 billed monthly</dd>
                                </div>
                                <div>
                                    <dt>You save</dt>
                                    <dd id="youSave">$9.60 vs base pricing</dd>
                                </div>
                            </dl>

                            <a id="pricingCTA" href="<?php echo esc_url( home_url( '/signup/' ) ); ?>" class="cta-button" data-buy-points>
                                Subscribe to <span id="ctaPoints">120</span> points / month
                            </a>

                            <p class="pricing-note">
                                You must be logged in to complete checkout.
                            </p>

                            <p class="footnote">
                                No long-term contracts. Upgrade or downgrade any time from your dashboard.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- SECTION 2: MONTHLY SUBSCRIPTIONS -->
        <section 
            id="subscriptions-panel" 
            class="pricing-tab-content" 
            role="tabpanel" 
            aria-labelledby="subscriptions-tab"
        >
            <div class="subscription-plans">
                <div class="section-header">
                    <h2>Ready-made Monthly Packages</h2>
                    <p class="section-subtitle">Pick a pre-made subscription tier and start downloading instantly.</p>
                </div>

                <div class="plans-grid">
            <?php
            // Load subscription plans from backend
            $backend_plans = array();
            if ( function_exists( 'nehtw_gateway_get_subscription_plans' ) ) {
                $backend_plans = nehtw_gateway_get_subscription_plans();
            }

            // Transform backend plans to frontend format
            $plans = array();
            foreach ( $backend_plans as $backend_plan ) {
                $plan_key = isset( $backend_plan['key'] ) ? $backend_plan['key'] : '';
                $plan_name = isset( $backend_plan['name'] ) ? $backend_plan['name'] : '';
                $plan_points = isset( $backend_plan['points'] ) ? floatval( $backend_plan['points'] ) : 0;
                $plan_desc = isset( $backend_plan['description'] ) ? $backend_plan['description'] : '';
                $plan_highlight = ! empty( $backend_plan['highlight'] );
                $product_id = isset( $backend_plan['product_id'] ) ? intval( $backend_plan['product_id'] ) : 0;
                
                // Parse price_label to extract EGP and USD prices
                $price_label = isset( $backend_plan['price_label'] ) ? $backend_plan['price_label'] : '';
                $price_egp = null;
                $price_usd = null;
                
                // Try to extract prices from price_label (format: "EGP 750 / month" or "$35 / month")
                if ( ! empty( $price_label ) ) {
                    // Try to match EGP price
                    if ( preg_match( '/EGP\s*([\d,]+)/i', $price_label, $egp_matches ) ) {
                        $price_egp = floatval( str_replace( ',', '', $egp_matches[1] ) );
                    }
                    // Try to match USD price
                    if ( preg_match( '/\$?\s*([\d,]+)/', $price_label, $usd_matches ) ) {
                        $price_usd = floatval( str_replace( ',', '', $usd_matches[1] ) );
                    }
                }
                
                // Get product URL if product_id exists
                $product_url = '';
                if ( $product_id > 0 && function_exists( 'get_permalink' ) ) {
                    $product = wc_get_product( $product_id );
                    if ( $product && $product->is_purchasable() ) {
                        $product_url = get_permalink( $product_id );
                    }
                }
                
                // Only include plans with valid data
                if ( ! empty( $plan_name ) && $plan_points > 0 ) {
                    $plans[] = array(
                        'name' => $plan_name,
                        'points' => $plan_points,
                        'price_egp' => $price_egp,
                        'price_usd' => $price_usd,
                        'desc' => $plan_desc,
                        'highlight' => $plan_highlight,
                        'product_id' => $product_id,
                        'product_url' => $product_url,
                        'key' => $plan_key,
                    );
                }
            }

            // If no plans from backend, show empty state
            if ( empty( $plans ) ) : ?>
                <p class="pricing-empty">
                    <?php esc_html_e( 'No subscription plans available at this time. Please check back later.', 'artly' ); ?>
                </p>
            <?php else :
                foreach ($plans as $plan): ?>
                <div class="plan-card <?php echo $plan['highlight'] ? 'highlight' : ''; ?>" 
                     data-price-egp="<?php echo $plan['price_egp'] ? esc_attr( $plan['price_egp'] ) : ''; ?>"
                     data-price-usd="<?php echo $plan['price_usd'] ? esc_attr( $plan['price_usd'] ) : ''; ?>"
                     data-product-id="<?php echo $plan['product_id'] > 0 ? esc_attr( $plan['product_id'] ) : ''; ?>"
                     data-product-url="<?php echo ! empty( $plan['product_url'] ) ? esc_attr( $plan['product_url'] ) : ''; ?>">
                    <?php if ( $plan['highlight'] ): ?>
                        <div class="badge">Most Popular</div>
                    <?php endif; ?>
                    <h3 class="plan-title"><?php echo esc_html( $plan['name'] ); ?></h3>
                    <p class="plan-points"><?php echo esc_html( number_format_i18n( $plan['points'], 0 ) ); ?> points / month</p>
                    <p class="plan-price">
                        <?php if ( $plan['price_egp'] || $plan['price_usd'] ): ?>
                            <?php if ( $plan['price_egp'] ): ?>
                                <span class="price-amount" data-currency="egp"><?php echo number_format_i18n( $plan['price_egp'], 0 ); ?> EGP</span>
                            <?php endif; ?>
                            <?php if ( $plan['price_usd'] ): ?>
                                <span class="price-amount" data-currency="usd" style="display: none;">$<?php echo number_format_i18n( $plan['price_usd'], 0 ); ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            Custom
                        <?php endif; ?>
                    </p>
                    <p class="plan-desc"><?php echo esc_html( $plan['desc'] ); ?></p>
                    <?php if ( ! empty( $plan['product_url'] ) ): ?>
                        <a href="<?php echo esc_url( $plan['product_url'] ); ?>" class="plan-btn pricing-cta-btn" data-plan-key="<?php echo esc_attr( $plan['key'] ); ?>">Subscribe to <?php echo esc_html( $plan['name'] ); ?></a>
                    <?php else: ?>
                        <span class="plan-btn pricing-cta-btn" style="opacity: 0.6; cursor: not-allowed;">Plan Unavailable</span>
                    <?php endif; ?>
                </div>
            <?php 
                endforeach;
            endif; ?>
                </div>
            </div>
        </section>
    </div>

    <!-- FAQ SECTION -->
    <section class="pricing-faq">
        <div class="container">
            <h2>Frequently Asked Questions</h2>
            
            <details class="faq-item">
                <summary>How do points work?</summary>
                <div class="faq-content">
                    <p>Each download costs points depending on the source. The more points you buy, the cheaper each one becomes. Points are deducted from your wallet when you download assets.</p>
                </div>
            </details>

            <details class="faq-item">
                <summary>Do unused points roll over?</summary>
                <div class="faq-content">
                    <p>Yes, all unused points remain in your wallet as long as your account is active. There's no expiration date on purchased points.</p>
                </div>
            </details>

            <details class="faq-item">
                <summary>Can I cancel my subscription?</summary>
                <div class="faq-content">
                    <p>You can cancel anytime from your dashboard. You'll keep access until your billing cycle ends, and any remaining points will stay in your wallet.</p>
                </div>
            </details>

            <details class="faq-item">
                <summary>Do you offer refunds?</summary>
                <div class="faq-content">
                    <p>Refunds are available only for unused subscriptions within 7 days of purchase. Contact our support team for assistance.</p>
                </div>
            </details>

            <details class="faq-item">
                <summary>Which stock sites are supported?</summary>
                <div class="faq-content">
                    <p>We support major stock providers including Shutterstock, Adobe Stock, Freepik, and more. Paste any supported stock URL and we'll handle the download automatically.</p>
                </div>
            </details>

            <details class="faq-item">
                <summary>Can I share my wallet with my team?</summary>
                <div class="faq-content">
                    <p>Yes! Studio and Agency plans support team sharing. You can invite team members and manage access levels from your dashboard.</p>
                </div>
            </details>

            <div class="faq-cta">
                <p>Still have questions?</p>
                <a href="<?php echo esc_url( home_url( '/support/' ) ); ?>" class="artly-btn artly-btn-ghost">Contact us</a>
            </div>
        </div>
    </section>

</main>

<?php
get_footer();