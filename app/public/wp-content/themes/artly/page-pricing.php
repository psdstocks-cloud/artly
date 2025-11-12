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
            $plans = [
                [
                    'name' => 'Starter',
                    'points' => 50,
                    'price_egp' => 750,
                    'price_usd' => 15,
                    'desc' => 'For individuals who only need occasional downloads.',
                    'highlight' => false
                ],
                [
                    'name' => 'Creator',
                    'points' => 120,
                    'price_egp' => 1750,
                    'price_usd' => 35,
                    'desc' => 'Best for solo creators or freelancers building regularly.',
                    'highlight' => true
                ],
                [
                    'name' => 'Studio',
                    'points' => 250,
                    'price_egp' => 3250,
                    'price_usd' => 65,
                    'desc' => 'For studios and small teams with frequent creative needs.',
                    'highlight' => false
                ],
                [
                    'name' => 'Agency',
                    'points' => 400,
                    'price_egp' => 4750,
                    'price_usd' => 95,
                    'desc' => 'Ideal for agencies managing multiple clients and campaigns.',
                    'highlight' => false
                ],
                [
                    'name' => 'Enterprise',
                    'points' => 700,
                    'price_egp' => null,
                    'price_usd' => null,
                    'desc' => 'Custom high-volume solutions for enterprises and resellers.',
                    'highlight' => false
                ],
            ];

            foreach ($plans as $plan): ?>
                <div class="plan-card <?php echo $plan['highlight'] ? 'highlight' : ''; ?>" 
                     data-price-egp="<?php echo $plan['price_egp'] ? esc_attr( $plan['price_egp'] ) : ''; ?>"
                     data-price-usd="<?php echo $plan['price_usd'] ? esc_attr( $plan['price_usd'] ) : ''; ?>">
                    <?php if ( $plan['highlight'] ): ?>
                        <div class="badge">Most Popular</div>
                    <?php endif; ?>
                    <h3 class="plan-title"><?php echo esc_html( $plan['name'] ); ?></h3>
                    <p class="plan-points"><?php echo esc_html( $plan['points'] ); ?> points / month</p>
                    <p class="plan-price">
                        <?php if ( $plan['price_egp'] ): ?>
                            <span class="price-amount" data-currency="egp"><?php echo number_format( $plan['price_egp'] ); ?> EGP</span>
                            <span class="price-amount" data-currency="usd" style="display: none;">$<?php echo number_format( $plan['price_usd'], 0 ); ?></span>
                        <?php else: ?>
                            Custom
                        <?php endif; ?>
                    </p>
                    <p class="plan-desc"><?php echo esc_html( $plan['desc'] ); ?></p>
                    <a href="<?php echo esc_url( home_url( '/signup/' ) ); ?>" class="plan-btn pricing-cta-btn">Choose <?php echo esc_html( $plan['name'] ); ?></a>
                </div>
            <?php endforeach; ?>
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
