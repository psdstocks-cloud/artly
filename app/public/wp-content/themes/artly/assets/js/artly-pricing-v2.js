/**
 * Artly Pricing Page - Enhanced Version
 * Includes: Currency conversion, GSAP animations, light/dark mode, login detection
 */

document.addEventListener('DOMContentLoaded', () => {
    // Check if GSAP is available
    if (typeof gsap === 'undefined' || typeof ScrollTrigger === 'undefined') {
        console.warn('GSAP or ScrollTrigger not loaded');
        // Continue without animations
    } else {
        gsap.registerPlugin(ScrollTrigger);
    }

    // ===== 1. CURRENCY SYSTEM =====
    // Currency conversion rate - get from settings or use default
    // Note: Settings uses EGP per USD (e.g., 50), frontend uses USD per EGP (e.g., 0.020)
    const conversionRateFromSettings = artlyPricingSettings?.conversionRate || null;
    const conversionRate = conversionRateFromSettings !== null 
        ? 1 / conversionRateFromSettings  // Convert from EGP/USD to USD/EGP
        : 0.020; // Default: 1 EGP = 0.020 USD (1 USD = 50 EGP)
    let currency = artlyPricingSettings?.userCurrency || localStorage.getItem('artly_currency') || 'EGP';

    window.artlyCurrency = window.artlyCurrency || {};
    window.artlyCurrency.current = currency;
    
    // Initialize currency from localStorage or PHP
    if (!localStorage.getItem('artly_currency')) {
        localStorage.setItem('artly_currency', currency);
    } else {
        currency = localStorage.getItem('artly_currency');
    }

    const currencyToggle = document.getElementById('currencyToggle');
    const currencyDisplay = document.getElementById('currencyDisplay');
    const body = document.body;

    function convertToUSD(egpAmount) {
        return egpAmount * conversionRate;
    }

    function convertToEGP(usdAmount) {
        return usdAmount / conversionRate;
    }

    function formatCurrency(value, curr = currency) {
        if (curr === 'USD') {
            return '$' + Number(value).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        } else {
            return Number(value).toLocaleString('en-US', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }) + ' EGP';
        }
    }

    function updateAllPrices() {
        // Update plan card prices
        document.querySelectorAll('.plan-card').forEach(card => {
            const priceEgp = card.dataset.priceEgp;
            const priceUsd = card.dataset.priceUsd;
            const priceAmounts = card.querySelectorAll('.price-amount');
            
            if (priceEgp && priceUsd) {
                priceAmounts.forEach(span => {
                    if (span.dataset.currency === 'egp') {
                        span.style.display = currency === 'EGP' ? 'inline' : 'none';
                        if (currency === 'EGP') {
                            span.textContent = formatCurrency(priceEgp, 'EGP');
                        }
                    } else if (span.dataset.currency === 'usd') {
                        span.style.display = currency === 'USD' ? 'inline' : 'none';
                        if (currency === 'USD') {
                            span.textContent = formatCurrency(priceUsd, 'USD');
                        }
                    }
                });
            }
        });
    }

    function updateCurrencyDisplay() {
        if (currencyDisplay) {
            currencyDisplay.textContent = currency;
        }
        window.artlyCurrency.current = currency;
        updateAllPrices();
        // Also update calculator when currency changes
        if (range && input) {
            updateUI(parseInt(range.value, 10) || 120);
        }
    }

    if (currencyToggle) {
        currencyToggle.addEventListener('click', () => {
            currency = currency === 'EGP' ? 'USD' : 'EGP';
            localStorage.setItem('artly_currency', currency);
            
            // Set cookie for server-side preference (1 year expiry)
            const expiryDate = new Date();
            expiryDate.setTime(expiryDate.getTime() + (365 * 24 * 60 * 60 * 1000));
            document.cookie = `artly_currency=${currency};path=/;expires=${expiryDate.toUTCString()};SameSite=Lax`;
            
            updateCurrencyDisplay();
        });
    }

    // ===== 2. TAB SWITCHING (Keep existing functionality) =====
    const tabs = document.querySelectorAll('.pricing-tab');
    const tabPanels = document.querySelectorAll('.pricing-tab-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const targetTab = tab.dataset.tab;

            // Update tab buttons
            tabs.forEach(t => {
                t.classList.remove('active');
                t.setAttribute('aria-selected', 'false');
            });
            tab.classList.add('active');
            tab.setAttribute('aria-selected', 'true');

            // Update tab panels
            tabPanels.forEach(panel => {
                panel.classList.remove('active');
            });
            let targetPanel;
            if (targetTab === 'dynamic') {
                targetPanel = document.getElementById('dynamic-pricing-panel');
            } else {
                targetPanel = document.getElementById(`${targetTab}-panel`);
            }
            if (targetPanel) {
                targetPanel.classList.add('active');
            }
        });
    });

    // ===== 3. DYNAMIC PRICING CALCULATOR (Enhanced with currency) =====
    const range = document.getElementById('pointsRange');
    const input = document.getElementById('pointsInput');
    const pricePerPointEl = document.getElementById('pricePerPoint');
    const monthlyTotalEl = document.getElementById('monthlyTotal');
    const youSaveEl = document.getElementById('youSave');
    const badgeEl = document.getElementById('tierBadge');
    const taglineEl = document.getElementById('tierTagline');
    const ctaPointsEl = document.getElementById('ctaPoints');

    if (!range || !input) return;

    const MIN_POINTS = 1;
    const MAX_POINTS = 500;
    const BASE_PRICE_EGP = 20; // base EGP/point used for "You save"

    function clampPoints(n) {
        if (!Number.isFinite(n)) return 120;
        return Math.min(MAX_POINTS, Math.max(MIN_POINTS, Math.round(n)));
    }

    // Pricing tiers in EGP
    function getPricePerPoint(points) {
        if (points <= 5)   return 20;
        if (points <= 10)  return 17;
        if (points <= 30)  return 15;
        if (points <= 70)  return 14;
        if (points <= 100) return 13;
        if (points <= 250) return 11;
        return 9.5; // 251–500
    }

    function getTierMeta(points) {
        if (points <= 5) {
            return {
                badge: 'Starter · Light use',
                tagline: 'For trying the service or very occasional downloads.'
            };
        }
        if (points <= 30) {
            return {
                badge: 'Creator · For solo makers',
                tagline: 'Great for freelancers and solo creators shipping regularly.'
            };
        }
        if (points <= 100) {
            return {
                badge: 'Studio · Most popular',
                tagline: 'Perfect for busy designers and small studios.'
            };
        }
        if (points <= 250) {
            return {
                badge: 'Agency · Growing teams',
                tagline: 'Ideal for agencies working across multiple clients.'
            };
        }
        return {
            badge: 'Enterprise · High volume',
            tagline: 'For enterprises and resellers with heavy usage.'
        };
    }

    function updateUI(points) {
        const p = clampPoints(points);

        // Sync both controls
        range.value = String(p);
        input.value = String(p);

        const pricePerPointEGP = getPricePerPoint(p);
        const monthlyTotalEGP = pricePerPointEGP * p;
        const savingsEGP = (BASE_PRICE_EGP - pricePerPointEGP) * p;
        const tier = getTierMeta(p);

        // Convert to current currency
        const pricePerPoint = currency === 'USD' ? convertToUSD(pricePerPointEGP) : pricePerPointEGP;
        const monthlyTotal = currency === 'USD' ? convertToUSD(monthlyTotalEGP) : monthlyTotalEGP;
        const savings = currency === 'USD' ? convertToUSD(savingsEGP) : savingsEGP;

        // Update price per point display
        if (pricePerPointEl) {
            const decimals = pricePerPoint % 1 === 0 ? 0 : (currency === 'USD' ? 2 : 1);
            pricePerPointEl.innerHTML =
                `<span class="price-value">${formatCurrency(pricePerPoint, currency)}</span> / point`;
        }

        // Update monthly total
        if (monthlyTotalEl) {
            monthlyTotalEl.textContent = `${formatCurrency(monthlyTotal, currency)} billed monthly`;
        }

        // Update savings
        if (youSaveEl) {
            youSaveEl.textContent =
                savings > 0
                    ? `${formatCurrency(savings, currency)} vs base pricing`
                    : '–';
        }

        // Update tier info
        if (badgeEl) {
            badgeEl.textContent = tier.badge;
        }
        if (taglineEl) {
            taglineEl.textContent = tier.tagline;
        }
        if (ctaPointsEl) {
            ctaPointsEl.textContent = p;
        }
    }

    // Event listeners for calculator
    range.addEventListener('input', () => {
        const value = clampPoints(parseInt(range.value, 10));
        updateUI(value);
    });

    input.addEventListener('input', () => {
        const value = clampPoints(parseInt(input.value, 10));
        updateUI(value);
    });

    input.addEventListener('blur', () => {
        const value = clampPoints(parseInt(input.value, 10));
        updateUI(value);
    });

    // Initial render
    updateUI(parseInt(range.value, 10) || 120);
    updateCurrencyDisplay();

    // ===== 4. GSAP ANIMATIONS =====
    if (typeof gsap !== 'undefined' && typeof ScrollTrigger !== 'undefined') {
        // Check for reduced motion preference
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        
        if (!prefersReducedMotion) {
            // Animate plan cards
            gsap.utils.toArray('.plan-card').forEach((card, i) => {
                gsap.from(card, {
                    scrollTrigger: {
                        trigger: card,
                        start: 'top 85%',
                        toggleActions: 'play none none none'
                    },
                    y: 50,
                    opacity: 0,
                    duration: 0.6,
                    delay: i * 0.15,
                    ease: 'power2.out'
                });
            });

            // Animate calculator box
            const calcBox = document.querySelector('.pricing-calculator');
            if (calcBox) {
                gsap.from(calcBox, {
                    scrollTrigger: {
                        trigger: calcBox,
                        start: 'top 85%',
                        toggleActions: 'play none none none'
                    },
                    y: 40,
                    opacity: 0,
                    duration: 0.7,
                    ease: 'power2.out'
                });
            }

            // Animate FAQ items
            gsap.utils.toArray('.faq-item').forEach((item, i) => {
                gsap.from(item, {
                    scrollTrigger: {
                        trigger: item,
                        start: 'top 85%',
                        toggleActions: 'play none none none'
                    },
                    y: 20,
                    opacity: 0,
                    duration: 0.5,
                    delay: i * 0.1,
                    ease: 'power2.out'
                });
            });

            // Animate hero section
            const hero = document.querySelector('.pricing-hero');
            if (hero) {
                gsap.from('.pricing-hero .eyebrow, .pricing-hero h1, .pricing-hero .sub', {
                    y: 30,
                    opacity: 0,
                    duration: 0.8,
                    stagger: 0.2,
                    ease: 'power3.out'
                });
            }
        }
    }

    // ===== 5. LIGHT/DARK MODE =====
    const modeToggle = document.getElementById('modeToggle');
    const modeIcon = document.getElementById('modeIcon');
    
    function setTheme(mode) {
        body.setAttribute('data-theme', mode);
        localStorage.setItem('artly_theme', mode);
        
        if (modeIcon) {
            modeIcon.textContent = mode === 'light' ? '🌞' : '🌙';
        }
    }

    // Load saved theme or default to light
    const savedTheme = localStorage.getItem('artly_theme') || 'light';
    setTheme(savedTheme);

    if (modeToggle) {
        modeToggle.addEventListener('click', () => {
            const currentTheme = body.getAttribute('data-theme') || 'light';
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            setTheme(newTheme);
        });
    }

    // ===== 6. WOOCOMMERCE CHECKOUT HANDLER =====
    // Note: The actual add-to-cart logic is handled by pricing-woo.js
    // This section only handles non-WooCommerce fallbacks
    const isLoggedIn = artlyPricingSettings?.isLoggedIn || false;
    const homeUrl = artlyPricingSettings?.homeUrl || '/';
    const woocommerceActive = artlyPricingSettings?.woocommerceActive || false;
    const woocommerceProductId = artlyPricingSettings?.woocommerceProductId || 0;
    
    // Main CTA button (calculator checkout)
    // Only handle non-WooCommerce scenarios - pricing-woo.js handles WooCommerce
    const mainCTA = document.getElementById('pricingCTA');
    
    if (mainCTA && !woocommerceActive) {
        // Fallback: login state detection (if WooCommerce not active)
        if (isLoggedIn) {
            mainCTA.textContent = 'Go to Dashboard';
            mainCTA.href = homeUrl + '/my-downloads/';
        } else {
            mainCTA.textContent = mainCTA.textContent || 'Get Started';
            mainCTA.href = homeUrl + '/signup/';
        }
    }
    // If WooCommerce is active, pricing-woo.js will handle the click event

    // Update all plan CTA buttons (subscription plans)
    document.querySelectorAll('.pricing-cta-btn').forEach(btn => {
        // Get product URL from data attribute if available
        const planCard = btn.closest('.plan-card');
        const productUrl = planCard ? planCard.getAttribute('data-product-url') : null;
        const productId = planCard ? planCard.getAttribute('data-product-id') : null;
        
        // If product URL exists, use it (already set in PHP)
        if (productUrl && productUrl.trim() !== '') {
            // Product URL is already set in PHP, just ensure it's correct
            if (btn.tagName === 'A') {
                btn.href = productUrl;
                // Update button text for logged-in users
                if (isLoggedIn) {
                    btn.textContent = 'Subscribe Now';
                }
            }
        } else {
            // No product URL - show unavailable state
            if (btn.tagName === 'A') {
                btn.style.opacity = '0.6';
                btn.style.cursor = 'not-allowed';
                btn.textContent = 'Plan Unavailable';
                btn.href = '#';
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                });
            }
        }
    });

    // ===== 7. SUCCESS NOTIFICATIONS (Toast System) =====
    function showToast(message, type = 'success') {
        // Remove existing toast if any
        const existingToast = document.querySelector('.artly-toast');
        if (existingToast) {
            existingToast.remove();
        }

        // Create toast element
        const toast = document.createElement('div');
        toast.className = `artly-toast artly-toast-${type}`;
        toast.textContent = message;
        
        // Add to body
        document.body.appendChild(toast);
        
        // Trigger animation
        setTimeout(() => {
            toast.classList.add('artly-toast-show');
        }, 10);
        
        // Auto-remove after 4 seconds
        setTimeout(() => {
            toast.classList.remove('artly-toast-show');
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 4000);
    }

    // Check for success message in URL params (from WooCommerce redirect)
    const urlParams = new URLSearchParams(window.location.search);
    const pointsAdded = urlParams.get('points_added');
    if (pointsAdded) {
        showToast(`✅ ${pointsAdded} points added to your wallet!`, 'success');
        // Clean URL
        const newUrl = window.location.pathname;
        window.history.replaceState({}, document.title, newUrl);
    }
});