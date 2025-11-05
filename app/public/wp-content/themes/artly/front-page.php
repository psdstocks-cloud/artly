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
                    Paste a stock link.<br>
                    Get the file from one wallet.
                </h1>
                <p class="artly-hero-sub">
                    Stop juggling credits on every stock site. Load points once, paste any
                    supported stock URL, and download in seconds — with free re-downloads
                    for past orders.
                </p>
                <div class="artly-hero-actions">
                    <a href="<?php echo esc_url( site_url( '/signup/' ) ); ?>" class="artly-btn artly-btn-primary">
                        Get started free
                    </a>
                    <a href="<?php echo esc_url( site_url( '/pricing/' ) ); ?>" class="artly-btn artly-btn-ghost">
                        View pricing
                    </a>
                </div>
                <p class="artly-hero-meta">
                    No long-term contracts · Pay per point · Designed for creatives 🧡
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

    <!-- HOW IT WORKS -->
    <section class="artly-section artly-section-steps">
        <div class="artly-container">
            <header class="artly-section-header">
                <h2>How Artly works</h2>
                <p>Your visual assets flow through one wallet. A typical project looks like this.</p>
            </header>
            <div class="artly-steps-grid">
                <article class="artly-step-card">
                    <div class="artly-step-icon">①</div>
                    <h3>Load points once</h3>
                    <p>
                        Choose a monthly package or custom points. Get one invoice and one wallet for all your stock downloads.
                    </p>
                </article>
                <article class="artly-step-card">
                    <div class="artly-step-icon">②</div>
                    <h3>Paste any stock URL</h3>
                    <p>
                        Copy links from supported stock sites and paste them into the downloader. We detect the provider &amp; ID for you.
                    </p>
                </article>
                <article class="artly-step-card">
                    <div class="artly-step-icon">③</div>
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
                <article class="artly-benefit">
                    <h3>Multi-stock compatible</h3>
                    <p>
                        Works with top stock providers. One flow whether you grab assets from Shutterstock, Adobe Stock, Freepik and more.
                    </p>
                </article>
                <article class="artly-benefit">
                    <h3>One visual wallet</h3>
                    <p>
                        Load points once and use them across providers. No more scattered credits and surprise renewals.
                    </p>
                </article>
                <article class="artly-benefit">
                    <h3>Free re-downloads</h3>
                    <p>
                        You already paid for the asset. Re-download past orders from your history without spending extra points.
                    </p>
                </article>
                <article class="artly-benefit">
                    <h3>RTL &amp; dark-mode ready</h3>
                    <p>
                        Arabic/English language switch, right-to-left layouts and dark UI built in from day one.
                    </p>
                </article>
                <article class="artly-benefit">
                    <h3>Licensing friendly</h3>
                    <p>
                        Files are fetched from official providers using your own licenses and rules — we just simplify the workflow.
                    </p>
                </article>
                <article class="artly-benefit">
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
            <div>
                <p class="artly-eyebrow">Transparent point pricing</p>
                <h2>See how your price per point drops as you scale.</h2>
                <p>
                    Drag a slider, pick your tier and choose how long points stay valid — all on the pricing page.
                </p>
            </div>
            <div class="artly-pricing-strip-cta">
                <a href="<?php echo esc_url( site_url( '/signup/' ) ); ?>" class="artly-btn artly-btn-primary">
                    Start free trial
                </a>
                <p class="artly-pricing-strip-note">
                    Includes ready-made monthly packages and a dynamic slider for custom needs.
                </p>
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
