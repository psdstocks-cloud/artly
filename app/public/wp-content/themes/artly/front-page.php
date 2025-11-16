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
            <div class="artly-hero-left">
                <p class="artly-hero-eyebrow">Modern stock &amp; AI workspace</p>
                <h1 class="artly-hero-title">
                    One wallet for<br>
                    every stock website.
                </h1>
                <p class="artly-hero-sub">
                    Paste a link from top stock sites, get an instant download. Share points across your whole team, and never lose access to your files.
                </p>

                <div class="artly-hero-actions">
                    <a href="<?php echo esc_url( site_url( '/signup/' ) ); ?>" class="artly-btn artly-btn-primary">
                        Start free trial
                        <span class="artly-btn-arrow">→</span>
                    </a>
                    <a href="<?php echo esc_url( site_url( '/pricing/' ) ); ?>" class="artly-btn artly-btn-ghost">
                        View pricing
                    </a>
                </div>

                <p class="artly-hero-meta">
                    No long-term contracts · Works with major stock providers · Built for teams &amp; solo creators
                </p>
            </div>

            <div class="artly-hero-right">
                <div class="artly-hero-console">
                    <div class="artly-console-header">
                        <span class="artly-dot"></span>
                        <span class="artly-dot"></span>
                        <span class="artly-dot"></span>
                        <span class="artly-console-title">artly stock-cli</span>
                    </div>

                    <div class="artly-console-body">
                        <p><span class="artly-console-prompt">$</span> paste https://www.shutterstock.com/image-photo/...</p>
                        <p><span class="artly-console-label">site</span> shutterstock · <span class="artly-console-label">size</span> XL · <span class="artly-console-label">cost</span> 3 pts</p>
                        <p><span class="artly-console-success">✔</span> order created · ready in a few seconds…</p>
                        <p><span class="artly-console-download">↓</span> download-link.jpg · stored in your history</p>
                    </div>

                    <div class="artly-console-footer">
                        <span>Live orders available after sign up</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- TRUST STRIP -->
    <section class="artly-section artly-trusted">
        <div class="artly-container">
            <p class="artly-trusted-label">Used by teams who ship fast visuals</p>
            <div class="artly-trusted-logos">
                <span class="artly-logo-pill">Design studios</span>
                <span class="artly-logo-pill">Marketing teams</span>
                <span class="artly-logo-pill">Freelancers</span>
                <span class="artly-logo-pill">Agencies</span>
            </div>
        </div>
    </section>

    <!-- FEATURES -->
    <section class="artly-section artly-features">
        <div class="artly-container">
            <div class="artly-section-header">
                <h2>Everything you need in one stock workspace</h2>
                <p>Artly connects to major stock sites, gives you a single wallet, and keeps your downloads organized forever.</p>
            </div>

            <div class="artly-feature-grid">
                <article class="artly-feature-card">
                    <h3>One wallet, many providers</h3>
                    <p>Buy points once and let your whole team spend them on Shutterstock, Adobe Stock, Freepik, and more.</p>
                </article>
                <article class="artly-feature-card">
                    <h3>Instant link → download</h3>
                    <p>Paste any supported stock URL and get the right file in seconds, with full history and re-downloads.</p>
                </article>
                <article class="artly-feature-card">
                    <h3>Built for teams &amp; clients</h3>
                    <p>Share points, track usage, and keep a clean audit log of every download across your projects.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- HOW IT WORKS -->
    <section class="artly-section artly-how-it-works">
        <div class="artly-container">
            <div class="artly-section-header">
                <h2>How Artly works</h2>
                <p>From stock link to final file in three simple steps.</p>
            </div>

            <div class="artly-steps-grid">
                <div class="artly-step-card">
                    <span class="artly-step-number">1</span>
                    <h3>Paste a stock URL</h3>
                    <p>Grab a URL from your favorite provider and drop it into Artly’s downloader.</p>
                </div>
                <div class="artly-step-card">
                    <span class="artly-step-number">2</span>
                    <h3>We handle the order</h3>
                    <p>We detect the site, size, and license, and process the order using your shared wallet.</p>
                </div>
                <div class="artly-step-card">
                    <span class="artly-step-number">3</span>
                    <h3>Download &amp; reuse any time</h3>
                    <p>Your team can re-download the same asset from history without spending extra points.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- PERSONAS -->
    <section class="artly-section artly-personas">
        <div class="artly-container">
            <div class="artly-section-header">
                <h2>Designed for real-world creative teams</h2>
                <p>Whether you’re solo or running a full studio, Artly cleans up your stock workflow.</p>
            </div>

            <div class="artly-persona-grid">
                <article class="artly-persona-card">
                    <h3>Agencies</h3>
                    <ul>
                        <li>Centralize stock spend across clients</li>
                        <li>Control who can download what</li>
                        <li>Export usage for invoicing</li>
                    </ul>
                </article>

                <article class="artly-persona-card">
                    <h3>Marketing teams</h3>
                    <ul>
                        <li>One wallet for campaigns and regions</li>
                        <li>Share links and files with stakeholders</li>
                        <li>Keep brand visuals consistent</li>
                    </ul>
                </article>

                <article class="artly-persona-card">
                    <h3>Freelancers</h3>
                    <ul>
                        <li>Stop juggling multiple subscriptions</li>
                        <li>Track download history by client</li>
                        <li>Protect your margins on small projects</li>
                    </ul>
                </article>
            </div>
        </div>
    </section>

    <!-- PRICING TEASER -->
    <section class="artly-section artly-pricing-teaser">
        <div class="artly-container artly-pricing-inner">
            <div>
                <h2>Simple points, clear pricing</h2>
                <p>Top up your wallet or use subscriptions. Pay only for the stock you actually download.</p>
            </div>
            <div>
                <a href="<?php echo esc_url( site_url( '/pricing/' ) ); ?>" class="artly-btn artly-btn-primary">
                    Explore pricing
                </a>
            </div>
        </div>
    </section>

    <!-- FINAL CTA -->
    <section class="artly-section artly-final-cta">
        <div class="artly-container artly-final-cta-inner">
            <div>
                <h2>Ready to tidy up your stock chaos?</h2>
                <p>Start a free trial and connect your favorite providers in a few minutes.</p>
            </div>
            <div class="artly-final-cta-actions">
                <a href="<?php echo esc_url( site_url( '/signup/' ) ); ?>" class="artly-btn artly-btn-primary">
                    Get started
                </a>
                <a href="<?php echo esc_url( site_url( '/stock-order/' ) ); ?>" class="artly-btn artly-btn-ghost">
                    Try the downloader
                </a>
            </div>
        </div>
    </section>
</main>

<?php
get_footer();

