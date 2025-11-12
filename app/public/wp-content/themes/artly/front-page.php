<?php

/**
 * Front page template for the Artly theme.
 */
get_header();
?>

<main class="artly-front">
    <!-- HERO -->
    <section class="artly-hero">
        <div class="artly-container artly-hero-inner">
            <!-- Left: copy -->
            <div class="artly-hero-copy">
                <p class="artly-eyebrow">For visual teams &amp; solo creators</p>
                <h1 class="artly-hero-title">
                    Stop Wasting Money on<br>
                    Multiple Stock Subscriptions
                </h1>
                <p class="artly-hero-sub">
                    One wallet. Every major stock site. Download in seconds.
                    Re-download forever for free. Save up to 40% on your stock budget.
                </p>

                <!-- Social Proof Strip -->
                <div class="artly-hero-social-proof">
                    <div class="artly-proof-item">
                        <span class="artly-proof-icon">⭐</span>
                        <span><strong>4.9/5</strong> from 500+ creators</span>
                    </div>
                    <div class="artly-proof-item">
                        <span class="artly-proof-icon">✓</span>
                        <span><strong>50,000+</strong> files downloaded</span>
                    </div>
                    <div class="artly-proof-item">
                        <span class="artly-proof-icon">💳</span>
                        <span>No credit card required</span>
                    </div>
                </div>

                <div class="artly-hero-actions">
                    <a href="<?php echo esc_url( site_url( '/signup/' ) ); ?>" class="artly-btn artly-btn-primary">
                        Start Free 14-Day Trial
                        <span class="artly-btn-arrow">→</span>
                    </a>
                    <a href="<?php echo esc_url( site_url( '/pricing/' ) ); ?>" class="artly-btn artly-btn-ghost">
                        View pricing
                    </a>
                </div>
                <p class="artly-hero-meta">
                    14-day money-back guarantee · No long-term contracts · Designed for creatives
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
                        <span class="artly-card-app-title">Artly Studio</span>
                        <span class="artly-card-app-sub">Stock &amp; AI downloads</span>
                    </div>
                </div>
                <div class="artly-card-body">
                    <div class="artly-card-row artly-card-row-balance">
                        <div>
                            <p class="artly-card-label">Wallet balance</p>
                            <p class="artly-card-balance">0</p>
                        </div>
                        <div class="artly-card-balance-meta">
                            <span>1 point ≈ 20&nbsp;EGP</span>
                            <span>subscriptions &amp; pay-as-you-go</span>
                        </div>
                    </div>
                    <div class="artly-card-row">
                        <div class="artly-card-row-header">
                            <p class="artly-card-title">Smart downloader</p>
                            <p class="artly-card-sub">
                                Paste a stock URL. We detect the site &amp; ID automatically.
                            </p>
                        </div>
                        <form class="artly-downloader-form" action="javascript:void(0);">
                            <label for="hero-stock-url" class="artly-field-label">
                                Paste stock URL
                            </label>
                            <input
                                type="url"
                                id="hero-stock-url"
                                class="artly-input"
                                placeholder="https://www.shutterstock.com/... or https://stock.adobe.com/..."
                            />
                            <button type="submit" class="artly-btn artly-btn-gradient">
                                Generate download link
                            </button>
                            <p class="artly-field-hint">
                                Live orders are available once you create a free Artly account.
                            </p>
                        </form>
                    </div>
                    <div class="artly-card-row artly-card-row-footer">
                        <div>
                            <p class="artly-card-footer-title">Download history</p>
                            <p class="artly-card-footer-text">
                                Re-download any past order for free, directly from your dashboard.
                            </p>
                        </div>
                        <div>
                            <p class="artly-card-footer-title">Need help?</p>
                            <p class="artly-card-footer-text">
                                Our team speaks "design". Share your link and we'll help you get the file.
                            </p>
                        </div>
                    </div>
                </div>
            </div><!-- /.artly-hero-card -->
        </div>
    </section>

    <!-- TESTIMONIALS -->
    <section class="artly-section artly-section-testimonials">
        <div class="artly-container">
            <header class="artly-section-header">
                <p class="artly-eyebrow">Loved by creatives</p>
                <h2>See why 500+ teams trust Artly</h2>
            </header>

            <div class="artly-testimonials-grid">
                <article class="artly-testimonial-card" data-animate>
                    <div class="artly-testimonial-stars">★★★★★</div>
                    <p class="artly-testimonial-text">
                        "Artly cut our stock budget by 35%. We used to have 4 different
                        subscriptions. Now it's all in one place. Game changer."
                    </p>
                    <div class="artly-testimonial-author">
                        <div class="artly-testimonial-avatar">SM</div>
                        <div>
                            <p class="artly-testimonial-name">Sarah Martinez</p>
                            <p class="artly-testimonial-role">Creative Director, Brand Studio</p>
                        </div>
                    </div>
                </article>

                <article class="artly-testimonial-card" data-animate>
                    <div class="artly-testimonial-stars">★★★★★</div>
                    <p class="artly-testimonial-text">
                        "Finally, no more hunting for that file I downloaded 3 months ago.
                        Download history is brilliant. Re-download any time for free!"
                    </p>
                    <div class="artly-testimonial-author">
                        <div class="artly-testimonial-avatar">AK</div>
                        <div>
                            <p class="artly-testimonial-name">Ahmed Khaled</p>
                            <p class="artly-testimonial-role">Freelance Designer, Cairo</p>
                        </div>
                    </div>
                </article>

                <article class="artly-testimonial-card" data-animate>
                    <div class="artly-testimonial-stars">★★★★★</div>
                    <p class="artly-testimonial-text">
                        "The Arabic support and RTL layout are perfect. First stock tool
                        that actually works for our bilingual team."
                    </p>
                    <div class="artly-testimonial-author">
                        <div class="artly-testimonial-avatar">LA</div>
                        <div>
                            <p class="artly-testimonial-name">Layla Al-Sayed</p>
                            <p class="artly-testimonial-role">Art Director, Digital Agency</p>
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
                <h2>How Artly works</h2>
                <p>Your visual assets flow through one wallet. A typical project looks like this.</p>
            </header>
            <div class="artly-steps-grid">
                <article class="artly-step-card" data-animate>
                    <div class="artly-step-icon-wrapper">
                        <svg class="artly-step-icon-svg" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M21 18v1c0 1.1-.9 2-2 2H5c-1.11 0-2-.9-2-2V5c0-1.1.89-2 2-2h14c1.1 0 2 .9 2 2v1h-9c-1.11 0-2 .9-2 2v8c0 1.1.89 2 2 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>
                        </svg>
                        <span class="artly-step-number">1</span>
                    </div>
                    <h3>Load points once</h3>
                    <p>
                        Choose a monthly package or custom points. Get one invoice and one wallet for all your stock downloads.
                    </p>
                </article>
                <article class="artly-step-card" data-animate>
                    <div class="artly-step-icon-wrapper">
                        <svg class="artly-step-icon-svg" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/>
                        </svg>
                        <span class="artly-step-number">2</span>
                    </div>
                    <h3>Paste any stock URL</h3>
                    <p>
                        Copy links from supported stock sites and paste them into the downloader. We detect the provider &amp; ID for you.
                    </p>
                </article>
                <article class="artly-step-card" data-animate>
                    <div class="artly-step-icon-wrapper">
                        <svg class="artly-step-icon-svg" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
                        </svg>
                        <span class="artly-step-number">3</span>
                    </div>
                    <h3>Download &amp; re-download</h3>
                    <p>
                        We fetch the file using your wallet. Need it again later? Re-download from your history without paying extra points.
                    </p>
                </article>
            </div>
        </div>
    </section>

    <!-- WHO IT'S FOR -->
    <section class="artly-section artly-section-personas">
        <div class="artly-container artly-personas-layout">
            <div class="artly-personas-visual">
                <div class="artly-personas-card">
                    <p class="artly-personas-tag">Dashboard preview</p>
                    <p class="artly-personas-title">Made for visual people</p>
                    <p class="artly-personas-text">
                        Dark UI, glassmorphism and clean typography so your tools feel as polished as your work.
                    </p>
                </div>
            </div>
            <div class="artly-personas-list">
                <article class="artly-persona">
                    <h3>Solo creators</h3>
                    <p>
                        You design, illustrate or animate on your own. Artly keeps all your stock downloads and invoices in one place.
                    </p>
                </article>
                <article class="artly-persona">
                    <h3>Studios</h3>
                    <p>
                        Share one wallet across your team, stay on top of costs, and stop re-buying the same assets for new projects.
                    </p>
                </article>
                <article class="artly-persona">
                    <h3>Agencies</h3>
                    <p>
                        Centralize client work, keep a full audit of downloads, and give producers a single place to manage licenses.
                    </p>
                </article>
            </div>
        </div>
    </section>

    <!-- BENEFITS GRID -->
    <section class="artly-section artly-section-benefits">
        <div class="artly-container">
            <header class="artly-section-header">
                <h2>Why teams switch to Artly</h2>
            </header>
            <div class="artly-benefits-grid">
                <article class="artly-benefit" data-animate>
                    <h3>Multi-stock compatible</h3>
                    <p>
                        Works with top stock providers. One flow whether you grab assets from Shutterstock, Adobe Stock, Freepik and more.
                    </p>
                </article>
                <article class="artly-benefit" data-animate>
                    <h3>One visual wallet</h3>
                    <p>
                        Load points once and use them across providers. No more scattered credits and surprise renewals.
                    </p>
                </article>
                <article class="artly-benefit artly-benefit-featured" data-animate>
                    <span class="artly-benefit-badge">Most Popular</span>
                    <h3>Free re-downloads</h3>
                    <p>
                        You already paid for the asset. Re-download past orders from your history without spending extra points.
                    </p>
                </article>
                <article class="artly-benefit" data-animate>
                    <h3>RTL &amp; dark-mode ready</h3>
                    <p>
                        Arabic/English language switch, right-to-left layouts and dark UI built in from day one.
                    </p>
                </article>
                <article class="artly-benefit" data-animate>
                    <h3>Licensing friendly</h3>
                    <p>
                        Files are fetched from official providers using your own licenses and rules — we just simplify the workflow.
                    </p>
                </article>
                <article class="artly-benefit" data-animate>
                    <h3>Support that "gets" design</h3>
                    <p>
                        Talk to a team that works with creatives every day. Share your link and we'll help you fix failed downloads.
                    </p>
                </article>
            </div>
        </div>
    </section>

    <!-- PRICING TEASER -->
    <section class="artly-section artly-section-pricing-strip">
        <div class="artly-container artly-pricing-strip">
            <div class="artly-pricing-strip-content">
                <p class="artly-eyebrow">Transparent pricing</p>
                <h2>Plans that scale with your team</h2>

                <!-- Price Preview -->
                <div class="artly-price-preview">
                    <div class="artly-price-option">
                        <p class="artly-price-label">Starter</p>
                        <p class="artly-price-amount">500 EGP<span>/month</span></p>
                        <p class="artly-price-points">25 points · ~25 downloads</p>
                    </div>
                    <div class="artly-price-option artly-price-option-popular">
                        <span class="artly-price-badge">Most Popular</span>
                        <p class="artly-price-label">Pro</p>
                        <p class="artly-price-amount">1,500 EGP<span>/month</span></p>
                        <p class="artly-price-points">100 points · ~100 downloads</p>
                    </div>
                    <div class="artly-price-option">
                        <p class="artly-price-label">Team</p>
                        <p class="artly-price-amount">Custom</p>
                        <p class="artly-price-points">Unlimited downloads</p>
                    </div>
                </div>

                <p class="artly-pricing-guarantee">
                    💳 No credit card required · 14-day money-back guarantee
                </p>
            </div>

            <div class="artly-pricing-strip-cta">
                <a href="<?php echo esc_url( site_url( '/signup/' ) ); ?>" class="artly-btn artly-btn-primary">
                    Start Free Trial
                    <span class="artly-btn-arrow">→</span>
                </a>
                <a href="<?php echo esc_url( site_url( '/pricing/' ) ); ?>" class="artly-pricing-details-link">
                    View detailed pricing →
                </a>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section class="artly-section artly-section-faq">
        <div class="artly-container">
            <header class="artly-section-header">
                <h2>Frequently asked questions</h2>
                <p>Everything you need to know about Artly</p>
            </header>

            <div class="artly-faq-grid">
                <details class="artly-faq-item">
                    <summary class="artly-faq-question">
                        How does the point system work?
                        <span class="artly-faq-icon">+</span>
                    </summary>
                    <div class="artly-faq-answer">
                        <p>1 point = ~20 EGP and gets you one standard stock download. Premium assets may require more points. Points never expire, and you can reload anytime.</p>
                    </div>
                </details>

                <details class="artly-faq-item">
                    <summary class="artly-faq-question">
                        Which stock sites do you support?
                        <span class="artly-faq-icon">+</span>
                    </summary>
                    <div class="artly-faq-answer">
                        <p>We support Shutterstock, Adobe Stock, Freepik, iStock, Envato Elements, and more. We're constantly adding new providers.</p>
                    </div>
                </details>

                <details class="artly-faq-item">
                    <summary class="artly-faq-question">
                        Can I really re-download files for free?
                        <span class="artly-faq-icon">+</span>
                    </summary>
                    <div class="artly-faq-answer">
                        <p>Yes! Once you download a file, it's saved in your history forever. Re-download it any time without spending additional points.</p>
                    </div>
                </details>

                <details class="artly-faq-item">
                    <summary class="artly-faq-question">
                        Is there a free trial?
                        <span class="artly-faq-icon">+</span>
                    </summary>
                    <div class="artly-faq-answer">
                        <p>Absolutely! Start with a 14-day free trial. No credit card required to sign up. Try all features risk-free.</p>
                    </div>
                </details>

                <details class="artly-faq-item">
                    <summary class="artly-faq-question">
                        What if I need a refund?
                        <span class="artly-faq-icon">+</span>
                    </summary>
                    <div class="artly-faq-answer">
                        <p>We offer a 14-day money-back guarantee. If you're not happy, contact us and we'll issue a full refund, no questions asked.</p>
                    </div>
                </details>

                <details class="artly-faq-item">
                    <summary class="artly-faq-question">
                        Do you offer team/agency plans?
                        <span class="artly-faq-icon">+</span>
                    </summary>
                    <div class="artly-faq-answer">
                        <p>Yes! We have special pricing for teams of 5+ users. Contact us for custom quotes with volume discounts and dedicated support.</p>
                    </div>
                </details>
            </div>

            <div class="artly-faq-footer">
                <p>Still have questions?</p>
                <a href="<?php echo esc_url( site_url( '/contact/' ) ); ?>" class="artly-btn artly-btn-ghost">Contact Support</a>
            </div>
        </div>
    </section>

    <!-- STORY / BRAND -->
    <section class="artly-section artly-section-story">
        <div class="artly-container">
            <header class="artly-section-header">
                <h2>Built by designers who were tired of messy stock workflows</h2>
            </header>
            <div class="artly-story-body">
                <p>
                    Artly started as an internal tool for our own studio. We loved premium stock libraries, but hated tracking
                    credits, invoices and logins for every site on every project.
                </p>
                <p>
                    So we built one wallet, one dashboard and one download flow for the whole team — and decided to open it
                    to other artists, studios and agencies who felt the same pain.
                </p>
                <p class="artly-story-signoff">— Team Artly</p>
            </div>
        </div>
    </section>
</main>

<?php
get_footer();