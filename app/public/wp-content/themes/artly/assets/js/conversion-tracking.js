/**
 * Artly Conversion Tracking & Analytics
 * Tracks key user interactions for conversion optimization
 */

(function() {
    'use strict';

    // Check if gtag is available (Google Analytics)
    const hasGtag = typeof gtag !== 'undefined';
    const hasFbq = typeof fbq !== 'undefined'; // Facebook Pixel

    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        initCTATracking();
        initScrollDepthTracking();
        initTimeOnPageTracking();
        initFormTracking();
        initExitIntentTracking();
    });

    /**
     * Track CTA button clicks
     */
    function initCTATracking() {
        // Hero CTA tracking
        const heroCTA = document.querySelector('.artly-hero-actions .artly-btn-primary');
        if (heroCTA) {
            heroCTA.addEventListener('click', function() {
                trackEvent('CTA', 'click', 'Hero Start Free Trial');
            });
        }

        // Pricing CTA tracking
        const pricingCTA = document.querySelector('.artly-pricing-strip-cta .artly-btn-primary');
        if (pricingCTA) {
            pricingCTA.addEventListener('click', function() {
                trackEvent('CTA', 'click', 'Pricing Start Free Trial');
            });
        }

        // View Pricing button
        const viewPricingBtns = document.querySelectorAll('.artly-btn-ghost[href*="pricing"]');
        viewPricingBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                trackEvent('CTA', 'click', 'View Pricing');
            });
        });

        // Newsletter signup
        const newsletterForm = document.querySelector('.artly-newsletter-form');
        if (newsletterForm) {
            newsletterForm.addEventListener('submit', function() {
                trackEvent('Newsletter', 'signup', 'Footer Newsletter');
            });
        }

        // Contact support button
        const contactBtn = document.querySelector('.artly-faq-footer .artly-btn');
        if (contactBtn) {
            contactBtn.addEventListener('click', function() {
                trackEvent('Support', 'click', 'FAQ Contact Support');
            });
        }
    }

    /**
     * Track scroll depth
     */
    function initScrollDepthTracking() {
        const scrollDepths = [25, 50, 75, 100];
        const tracked = {};

        window.addEventListener('scroll', throttle(function() {
            const scrollPercent = Math.round((window.scrollY + window.innerHeight) / document.body.scrollHeight * 100);

            scrollDepths.forEach(function(depth) {
                if (scrollPercent >= depth && !tracked[depth]) {
                    tracked[depth] = true;
                    trackEvent('Engagement', 'scroll', depth + '% Scroll');
                }
            });
        }, 500));
    }

    /**
     * Track time on page
     */
    function initTimeOnPageTracking() {
        const startTime = Date.now();
        const milestones = [30, 60, 120, 300]; // seconds
        const tracked = {};

        setInterval(function() {
            const timeSpent = Math.floor((Date.now() - startTime) / 1000);

            milestones.forEach(function(milestone) {
                if (timeSpent >= milestone && !tracked[milestone]) {
                    tracked[milestone] = true;
                    trackEvent('Engagement', 'time_on_page', milestone + ' seconds');
                }
            });
        }, 10000); // Check every 10 seconds

        // Track on page unload
        window.addEventListener('beforeunload', function() {
            const totalTime = Math.round((Date.now() - startTime) / 1000);
            trackEvent('Engagement', 'total_time', totalTime + ' seconds');
        });
    }

    /**
     * Track form interactions
     */
    function initFormTracking() {
        // Track demo form focus
        const demoInput = document.querySelector('#hero-stock-url');
        if (demoInput) {
            let focused = false;
            demoInput.addEventListener('focus', function() {
                if (!focused) {
                    focused = true;
                    trackEvent('Demo', 'interact', 'Hero Demo Input Focus');
                }
            });
        }

        // Track newsletter input focus
        const newsletterInput = document.querySelector('.artly-newsletter-input');
        if (newsletterInput) {
            let focused = false;
            newsletterInput.addEventListener('focus', function() {
                if (!focused) {
                    focused = true;
                    trackEvent('Newsletter', 'interact', 'Newsletter Input Focus');
                }
            });
        }
    }

    /**
     * Track FAQ interactions
     */
    document.querySelectorAll('.artly-faq-item').forEach(function(item) {
        item.addEventListener('toggle', function() {
            if (item.open) {
                const question = item.querySelector('.artly-faq-question').textContent.trim();
                trackEvent('FAQ', 'open', question.substring(0, 50)); // First 50 chars
            }
        });
    });

    /**
     * Exit intent tracking (desktop only)
     */
    function initExitIntentTracking() {
        let tracked = false;

        document.addEventListener('mouseleave', function(e) {
            if (e.clientY < 0 && !tracked && window.innerWidth > 768) {
                tracked = true;
                trackEvent('Engagement', 'exit_intent', 'Mouse Left Viewport');

                // Optional: Show exit intent popup here
                // showExitIntentPopup();
            }
        });
    }

    /**
     * Helper: Track event
     */
    function trackEvent(category, action, label) {
        // Google Analytics
        if (hasGtag) {
            gtag('event', action, {
                'event_category': category,
                'event_label': label
            });
        }

        // Facebook Pixel
        if (hasFbq) {
            fbq('trackCustom', category + '_' + action, {
                label: label
            });
        }

        // Console log for debugging (remove in production)
        console.log('Track:', category, action, label);
    }

    /**
     * Helper: Throttle function
     */
    function throttle(func, wait) {
        let timeout;
        return function() {
            const context = this;
            const args = arguments;
            if (!timeout) {
                timeout = setTimeout(function() {
                    timeout = null;
                    func.apply(context, args);
                }, wait);
            }
        };
    }

})();
