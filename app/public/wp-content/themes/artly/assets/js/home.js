/**
 * Artly Homepage - GSAP Animations & Currency Toggle
 */

(function() {
    'use strict';

    // Check for reduced motion preference
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Wait for DOM
    document.addEventListener('DOMContentLoaded', function() {
        
        // Initialize GSAP animations if available
        if (typeof gsap !== 'undefined' && typeof ScrollTrigger !== 'undefined' && !prefersReducedMotion) {
            gsap.registerPlugin(ScrollTrigger);
            document.documentElement.classList.add('gsap-loaded');
            initAnimations();
        } else {
            // If GSAP is not available, ensure all cards are visible
            document.querySelectorAll('.artly-glass-card, .artly-step-card').forEach(card => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
                card.style.visibility = 'visible';
            });
        }

        // Initialize currency toggle
        initCurrencyToggle();

        // Initialize FAQ accordions
        initFAQ();
    });

    /**
     * GSAP Animations
     */
    function initAnimations() {
        // Refresh ScrollTrigger to recalculate positions
        ScrollTrigger.refresh();

        // Hero animations
        const heroTimeline = gsap.timeline({
            defaults: { ease: 'power3.out' },
            delay: 0.2 // Small delay for smoother initial load
        });

        // Set initial state for hero elements
        gsap.set('.artly-hero-title', { y: 50, opacity: 0 });
        gsap.set('.artly-hero-subtitle', { y: 40, opacity: 0 });
        gsap.set('.artly-hero-cta-row .artly-btn', { y: 30, opacity: 0 });
        gsap.set('.artly-hero-trust-row li', { y: 20, opacity: 0 });
        gsap.set('.artly-hero-visual', { y: 40, opacity: 0 });

        heroTimeline
            .to('.artly-hero-title', {
                y: 0,
                opacity: 1,
                duration: 1,
                ease: 'power3.out'
            })
            .to('.artly-hero-subtitle', {
                y: 0,
                opacity: 1,
                duration: 0.9,
                ease: 'power3.out'
            }, '-=0.5')
            .to('.artly-hero-cta-row .artly-btn', {
                y: 0,
                opacity: 1,
                duration: 0.8,
                stagger: 0.15,
                ease: 'power3.out'
            }, '-=0.4')
            .to('.artly-hero-trust-row li', {
                y: 0,
                opacity: 1,
                duration: 0.7,
                stagger: 0.1,
                ease: 'power3.out'
            }, '-=0.3')
            .to('.artly-hero-visual', {
                y: 0,
                opacity: 1,
                duration: 1,
                ease: 'power3.out'
            }, '-=0.6');

        // Section reveal animations - cards appear smoothly on scroll down and stay visible
        const sections = document.querySelectorAll('.artly-section');
        sections.forEach((section, index) => {
            if (index === 0) return; // Skip hero

            const cards = section.querySelectorAll('.artly-glass-card:not(.artly-step-card)');
            if (cards.length === 0) return;

            // Set initial state (hidden)
            gsap.set(cards, {
                y: 40,
                opacity: 0,
                clearProps: false
            });

            // Animate in on scroll
            gsap.to(cards, {
                scrollTrigger: {
                    trigger: section,
                    start: 'top 85%',
                    end: 'top 50%',
                    toggleActions: 'play none none none', // Only play once, don't reverse
                    once: true, // Only animate once
                    onEnter: function() {
                        // Force visibility on enter
                        cards.forEach(card => {
                            card.style.opacity = '1';
                            card.style.transform = 'translateY(0)';
                        });
                    }
                },
                y: 0,
                opacity: 1,
                duration: 0.8,
                stagger: 0.15,
                ease: 'power3.out',
                onComplete: function() {
                    // Ensure cards are visible after animation
                    cards.forEach(card => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                        card.style.visibility = 'visible';
                    });
                }
            });
        });

        // Stagger cards in grids - smooth reveal (including step cards)
        const stepsGrid = document.querySelector('.artly-steps-grid');
        if (stepsGrid && stepsGrid.children.length > 0) {
            const stepCards = Array.from(stepsGrid.children);
            
            // Set initial state (hidden)
            gsap.set(stepCards, {
                y: 50,
                opacity: 0,
                clearProps: false
            });

            // Animate in on scroll
            gsap.to(stepCards, {
                scrollTrigger: {
                    trigger: stepsGrid,
                    start: 'top 85%',
                    end: 'top 50%',
                    toggleActions: 'play none none none',
                    once: true,
                    onEnter: function() {
                        // Force visibility on enter
                        stepCards.forEach(card => {
                            card.style.opacity = '1';
                            card.style.transform = 'translateY(0)';
                        });
                    }
                },
                y: 0,
                opacity: 1,
                duration: 0.9,
                stagger: 0.15,
                ease: 'power3.out',
                onComplete: function() {
                    // Ensure cards are visible after animation
                    stepCards.forEach(card => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                        card.style.visibility = 'visible';
                    });
                }
            });
        }

        // Other card grids
        const cardGrids = document.querySelectorAll('.artly-benefits-grid, .artly-pricing-teaser-grid, .artly-testimonials, .artly-faq-list');
        cardGrids.forEach(grid => {
            const children = Array.from(grid.children);
            if (children.length === 0) return;

            // Set initial state (hidden)
            gsap.set(children, {
                y: 50,
                opacity: 0,
                clearProps: false
            });

            // Animate in on scroll
            gsap.to(children, {
                scrollTrigger: {
                    trigger: grid,
                    start: 'top 85%',
                    end: 'top 50%',
                    toggleActions: 'play none none none',
                    once: true,
                    onEnter: function() {
                        // Force visibility on enter
                        children.forEach(child => {
                            child.style.opacity = '1';
                            child.style.transform = 'translateY(0)';
                        });
                    }
                },
                y: 0,
                opacity: 1,
                duration: 0.9,
                stagger: 0.12,
                ease: 'power3.out',
                onComplete: function() {
                    // Ensure cards are visible after animation
                    children.forEach(child => {
                        child.style.opacity = '1';
                        child.style.transform = 'translateY(0)';
                        child.style.visibility = 'visible';
                    });
                }
            });
        });
    }

    /**
     * Currency Toggle Integration
     */
    function initCurrencyToggle() {
        // Listen for currency change events from header.js
        window.addEventListener('artly:currencyChanged', function(e) {
            updatePricingCards();
        });

        // Also listen for storage events (if currency changes in another tab)
        window.addEventListener('storage', function(e) {
            if (e.key === 'artly_currency') {
                updatePricingCards();
            }
        });

        // Initial update
        updatePricingCards();
    }

    /**
     * Update pricing cards with current currency
     */
    function updatePricingCards() {
        const currency = localStorage.getItem('artly_currency') || 'EGP';
        const conversionRate = 0.020; // 1 EGP = 0.020 USD

        document.querySelectorAll('.artly-price-value').forEach(priceEl => {
            const egpAmount = parseFloat(priceEl.dataset.egp) || 0;
            const usdAmount = parseFloat(priceEl.dataset.usd) || 0;

            if (currency === 'USD') {
                priceEl.textContent = '$' + usdAmount.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            } else {
                priceEl.textContent = egpAmount.toLocaleString('en-US', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0
                }) + ' EGP';
            }
        });
    }

    /**
     * FAQ Accordion Enhancement
     */
    function initFAQ() {
        const faqItems = document.querySelectorAll('.artly-faq-item');
        
        faqItems.forEach(item => {
            const summary = item.querySelector('summary');
            
            summary.addEventListener('click', function(e) {
                // Close other open items (optional - remove if you want multiple open)
                // faqItems.forEach(otherItem => {
                //     if (otherItem !== item && otherItem.hasAttribute('open')) {
                //         otherItem.removeAttribute('open');
                //     }
                // });
            });
        });
    }

    /**
     * Smooth scroll for anchor links
     */
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href === '#') return;
            
            const target = document.querySelector(href);
            if (target) {
                e.preventDefault();
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    /**
     * Header scroll behavior (compact on scroll)
     */
    let lastScroll = 0;
    const header = document.querySelector('.artly-site-header');
    
    if (header) {
        window.addEventListener('scroll', function() {
            const currentScroll = window.pageYOffset;
            
            if (currentScroll > 100) {
                header.style.padding = '0.5rem 0';
            } else {
                header.style.padding = '';
            }
            
            lastScroll = currentScroll;
        }, { passive: true });
    }

})();

