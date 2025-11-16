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
                <p class="artly-hero-eyebrow">Modern workspace for stock &amp; AI visuals</p>
                <h1 class="artly-hero-title">One wallet for every stock website</h1>
                <p class="artly-hero-sub">
                    Artly lets you buy points once and spend them across top stock sites. Paste a link, get the file, and keep every download organised for your team — in Egypt and worldwide.
                </p>

                <div class="artly-hero-actions">
                    <a href="<?php echo esc_url( site_url( '/signup/' ) ); ?>" class="artly-btn artly-btn-primary">
                        Start free in minutes
                        <span class="artly-btn-arrow">→</span>
                    </a>
                    <a href="<?php echo esc_url( site_url( '/pricing/' ) ); ?>" class="artly-btn artly-btn-ghost">
                        View pricing
                    </a>
                </div>

                <p class="artly-hero-meta">
                    Pay in EGP or USD · Works with leading stock providers · Built for agencies, teams and freelancers
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
                        <p><span class="artly-console-prompt">$</span> paste https://www.shutterstock.com/image-photo/emerald-couch-pillows...</p>
                        <p><span class="artly-console-label">site</span> shutterstock · <span class="artly-console-label">size</span> XL · <span class="artly-console-label">cost</span> 3 pts</p>
                        <p><span class="artly-console-success">✔</span> order created · processing…</p>
                        <p><span class="artly-console-download">↓</span> download-ready.jpg · saved to your history</p>
                    </div>

                    <div class="artly-console-footer">
                        <span>Example workflow. Live orders available after sign-up.</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- TRUST STRIP -->
    <section class="artly-section artly-trusted">
        <div class="artly-container">
            <p class="artly-trusted-label">Trusted by people who ship visuals every day</p>
            <div class="artly-trusted-logos">
                <span class="artly-logo-pill">Design studios</span>
                <span class="artly-logo-pill">Marketing teams</span>
                <span class="artly-logo-pill">Content creators</span>
                <span class="artly-logo-pill">Freelancers &amp; agencies</span>
            </div>
        </div>
    </section>

    <!-- FEATURES -->
    <section class="artly-section artly-features">
        <div class="artly-container">
            <div class="artly-section-header">
                <h2>All your stock and AI visuals in one place</h2>
                <p>Stop juggling logins and subscriptions. Artly centralises your stock downloads, AI images, and billing into a single, clean workspace.</p>
            </div>

            <div class="artly-feature-grid">
                <article class="artly-feature-card">
                    <h3>One wallet, many providers</h3>
                    <p>Load your Artly wallet once and let your team spend points across multiple stock websites. No more separate balances, surprise invoices, or asking “whose card is this?”.</p>
                </article>
                <article class="artly-feature-card">
                    <h3>Paste a link, get the file</h3>
                    <p>Copy a URL from your favourite stock site, paste it into Artly, and we handle the rest. We detect the site, size, and cost, place the order, and give you a clean download link.</p>
                </article>
                <article class="artly-feature-card">
                    <h3>Every download, in one history</h3>
                    <p>Keep a searchable history of everything your team downloads. Re-download files without paying twice, see who used which asset, and stay safe on licensing.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- HOW IT WORKS -->
    <section class="artly-section artly-how-it-works">
        <div class="artly-container">
            <div class="artly-section-header">
                <h2>How Artly fits into your day</h2>
                <p>You already know how to copy a link. The rest is our job.</p>
            </div>

            <div class="artly-steps-grid">
                <div class="artly-step-card">
                    <span class="artly-step-number">1</span>
                    <h3>Paste a stock URL</h3>
                    <p>Choose an image, video, or vector from a supported stock website and paste the link into Artly’s downloader.</p>
                </div>
                <div class="artly-step-card">
                    <span class="artly-step-number">2</span>
                    <h3>We handle the order</h3>
                    <p>Artly talks to the provider’s API, confirms the cost in points, and creates a secure order using your shared wallet balance.</p>
                </div>
                <div class="artly-step-card">
                    <span class="artly-step-number">3</span>
                    <h3>Download and reuse anytime</h3>
                    <p>Grab the file from your dashboard in seconds. It stays in your history, so your team can re-download it later without extra cost.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- PERSONAS -->
    <section class="artly-section artly-personas">
        <div class="artly-container">
            <div class="artly-section-header">
                <h2>Built for the way creative teams actually work</h2>
                <p>Whether you’re solo in Cairo or running a global studio, Artly helps you move faster with less chaos.</p>
            </div>

            <div class="artly-persona-grid">
                <article class="artly-persona-card">
                    <h3>Agencies</h3>
                    <ul>
                        <li>Share one wallet across multiple client projects</li>
                        <li>Track stock usage per client for easy invoicing</li>
                        <li>Keep a clean audit trail for every download</li>
                    </ul>
                </article>

                <article class="artly-persona-card">
                    <h3>Marketing teams</h3>
                    <ul>
                        <li>Give your team access without sharing card details</li>
                        <li>Keep campaigns on brand with a shared asset history</li>
                        <li>See what’s being used across regions and channels</li>
                    </ul>
                </article>

                <article class="artly-persona-card">
                    <h3>Freelancers &amp; creators</h3>
                    <ul>
                        <li>Avoid 3–4 different subscriptions just to get one image</li>
                        <li>Charge clients fairly with clear download history</li>
                        <li>Keep all your assets backed up in one simple place</li>
                    </ul>
                </article>
            </div>
        </div>
    </section>

    <!-- PRICING TEASER -->
    <section class="artly-section artly-pricing-teaser">
        <div class="artly-container artly-pricing-inner">
            <div>
                <h2>Clear pricing for Egypt and worldwide</h2>
                <p>Top up a wallet or use subscriptions. Egyptian users can pay in EGP, international teams in USD — same features, same speed.</p>
            </div>
            <div>
                <a href="<?php echo esc_url( site_url( '/pricing/' ) ); ?>" class="artly-btn artly-btn-primary">
                    Explore plans &amp; points
                </a>
            </div>
        </div>
    </section>

    <!-- FINAL CTA -->
    <section class="artly-section artly-final-cta">
        <div class="artly-container artly-final-cta-inner">
            <div>
                <h2>Ready to un-mess your stock downloads?</h2>
                <p>Start with a small wallet, invite your team, and see how much time you save on every project. No long-term contracts, cancel anytime.</p>
            </div>
            <div class="artly-final-cta-actions">
                <a href="<?php echo esc_url( site_url( '/signup/' ) ); ?>" class="artly-btn artly-btn-primary">
                    Get started free
                </a>
                <a href="<?php echo esc_url( site_url( '/stock-order/' ) ); ?>" class="artly-btn artly-btn-ghost">
                    Try link downloader
                </a>
            </div>
        </div>
    </section>
</main>

<?php
get_footer();

