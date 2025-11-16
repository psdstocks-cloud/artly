/**
 * Enhanced Homepage Animations v2
 * GSAP animations for bilingual homepage with parallax and scroll triggers
 */

(function() {
    'use strict';

    // Check if GSAP is available
    if (typeof gsap === 'undefined' || typeof ScrollTrigger === 'undefined') {
        console.warn('GSAP or ScrollTrigger not loaded. Animations will not work.');
        return;
    }

    // Register ScrollTrigger plugin
    gsap.registerPlugin(ScrollTrigger);

    // Check if user prefers reduced motion
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const isMobile = window.innerWidth < 768;

    /**
     * Initialize hero animations
     */
    function initHeroAnimations() {
        const heroSection = document.querySelector('.artly-hero-bilingual');
        if (!heroSection) {
            return;
        }

        // Hero text elements
        const heroTitle = heroSection.querySelector('.artly-hero-title');
        const heroSub = heroSection.querySelector('.artly-hero-sub');
        const heroActions = heroSection.querySelector('.artly-hero-actions');
        const heroProof = heroSection.querySelector('.artly-hero-social-proof');
        const heroCard = heroSection.querySelector('.artly-hero-card');

        if (prefersReducedMotion) {
            // Just fade in without animation
            gsap.set([heroTitle, heroSub, heroActions, heroProof, heroCard], {
                opacity: 1
            });
            return;
        }

        // Stagger fade-in for text elements
        const textElements = [heroTitle, heroSub, heroProof, heroActions].filter(Boolean);
        
        gsap.from(textElements, {
            y: 50,
            opacity: 0,
            duration: 0.8,
            stagger: 0.15,
            ease: 'power3.out',
            delay: 0.2
        });

        // Hero card animation
        if (heroCard) {
            gsap.from(heroCard, {
                y: 60,
                opacity: 0,
                duration: 1,
                ease: 'power3.out',
                delay: 0.4
            });
        }

        // Parallax effect for hero card on scroll
        if (heroCard && !isMobile && !prefersReducedMotion) {
            gsap.to(heroCard, {
                y: 100,
                ease: 'none',
                scrollTrigger: {
                    trigger: heroSection,
                    start: 'top top',
                    end: 'bottom top',
                    scrub: 1
                }
            });
        }
    }

    /**
     * Initialize section reveal animations
     */
    function initSectionReveals() {
        const sections = document.querySelectorAll('.artly-section[data-animate]');
        
        sections.forEach(function(section) {
            ScrollTrigger.create({
                trigger: section,
                start: 'top bottom-=100',
                toggleActions: 'play none none reverse',
                onEnter: function() {
                    gsap.from(section, {
                        y: 60,
                        opacity: 0,
                        duration: 1,
                        ease: 'power3.out'
                    });
                }
            });
        });
    }

    /**
     * Initialize testimonial stagger animation
     */
    function initTestimonialAnimations() {
        const testimonialsGrid = document.querySelector('.artly-testimonials-grid');
        if (!testimonialsGrid) {
            return;
        }

        const testimonialCards = testimonialsGrid.querySelectorAll('.artly-testimonial-card[data-animate]');
        
        if (testimonialCards.length === 0) {
            return;
        }

        ScrollTrigger.create({
            trigger: testimonialsGrid,
            start: 'top 75%',
            once: true,
            onEnter: function() {
                gsap.from(testimonialCards, {
                    y: 40,
                    opacity: 0,
                    duration: 0.6,
                    stagger: 0.15,
                    ease: 'power2.out'
                });
            }
        });
    }

    /**
     * Initialize feature card animations
     */
    function initFeatureAnimations() {
        const benefitsGrid = document.querySelector('.artly-benefits-grid');
        if (!benefitsGrid) {
            return;
        }

        const benefitCards = benefitsGrid.querySelectorAll('.artly-benefit[data-animate]');
        
        if (benefitCards.length === 0) {
            return;
        }

        ScrollTrigger.create({
            trigger: benefitsGrid,
            start: 'top 75%',
            once: true,
            onEnter: function() {
                gsap.from(benefitCards, {
                    y: 30,
                    opacity: 0,
                    duration: 0.5,
                    stagger: 0.1,
                    ease: 'power2.out'
                });
            }
        });
    }

    /**
     * Initialize step card animations
     */
    function initStepAnimations() {
        const stepsGrid = document.querySelector('.artly-steps-grid');
        if (!stepsGrid) {
            return;
        }

        const stepCards = stepsGrid.querySelectorAll('.artly-step-card[data-animate]');
        
        if (stepCards.length === 0) {
            return;
        }

        ScrollTrigger.create({
            trigger: stepsGrid,
            start: 'top 75%',
            once: true,
            onEnter: function() {
                gsap.from(stepCards, {
                    y: 50,
                    opacity: 0,
                    duration: 0.7,
                    stagger: 0.2,
                    ease: 'power3.out'
                });
            }
        });
    }

    /**
     * Initialize live balance display (if user is logged in)
     */
    function initLiveBalance() {
        if (typeof artlyLang === 'undefined' || !artlyLang.isLoggedIn) {
            return;
        }

        const balanceElement = document.getElementById('artly-hero-balance');
        if (!balanceElement) {
            return;
        }

        // Fetch balance from API
        fetch('/wp-json/nehtw/v1/balance/current', {
            credentials: 'include'
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('Failed to fetch balance');
            }
            return response.json();
        })
        .then(function(data) {
            if (data.current_balance !== undefined) {
                balanceElement.textContent = Math.floor(data.current_balance).toLocaleString();
            }
        })
        .catch(function(error) {
            console.warn('Could not fetch balance:', error);
            // Keep default "0" value
        });
    }

    /**
     * Initialize stock URL detection in hero card
     */
    function initStockUrlDetection() {
        const urlInput = document.getElementById('hero-stock-url');
        if (!urlInput) {
            return;
        }

        // Stock site patterns
        const sitePatterns = {
            'shutterstock': /shutterstock\.com/i,
            'adobestock': /stock\.adobe\.com/i,
            'freepik': /freepik\.com/i,
            'envato': /elements\.envato\.com/i,
            'istock': /istockphoto\.com/i,
            'depositphotos': /depositphotos\.com/i
        };

        urlInput.addEventListener('input', function(e) {
            const url = e.target.value.trim();
            
            if (!url) {
                // Clear any site badge
                const existingBadge = urlInput.parentElement.querySelector('.artly-site-badge');
                if (existingBadge) {
                    existingBadge.remove();
                }
                return;
            }

            // Check for matching site
            let detectedSite = null;
            for (const [siteKey, pattern] of Object.entries(sitePatterns)) {
                if (pattern.test(url)) {
                    detectedSite = siteKey;
                    break;
                }
            }

            // Show/hide site badge
            let badge = urlInput.parentElement.querySelector('.artly-site-badge');
            
            if (detectedSite) {
                if (!badge) {
                    badge = document.createElement('div');
                    badge.className = 'artly-site-badge';
                    urlInput.parentElement.insertBefore(badge, urlInput.nextSibling);
                }
                badge.textContent = detectedSite.charAt(0).toUpperCase() + detectedSite.slice(1);
                badge.style.display = 'block';
            } else if (badge) {
                badge.style.display = 'none';
            }
        });
    }

    /**
     * Initialize all animations
     */
    function initAllAnimations() {
        initHeroAnimations();
        initSectionReveals();
        initTestimonialAnimations();
        initFeatureAnimations();
        initStepAnimations();
        initLiveBalance();
        initStockUrlDetection();
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAllAnimations);
    } else {
        initAllAnimations();
    }

    // Refresh ScrollTrigger on window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            ScrollTrigger.refresh();
        }, 250);
    });

})();

