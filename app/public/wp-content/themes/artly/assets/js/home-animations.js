/**
 * Artly Homepage Animations with GSAP + ScrollTrigger
 */

(function() {
    'use strict';

    // Wait for DOM and GSAP to be ready
    if (typeof gsap === 'undefined' || typeof ScrollTrigger === 'undefined') {
        console.warn('GSAP or ScrollTrigger not loaded');
        return;
    }

    // Register ScrollTrigger plugin
    gsap.registerPlugin(ScrollTrigger);

    // Set default preferences
    const defaults = {
        duration: 0.8,
        ease: 'power3.out',
        stagger: 0.1,
        start: 'top 85%',
        once: true
    };

    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        initAnimations();
        initParallaxEffects();
        initCardInteractions();
    });

    /**
     * Initialize all scroll-triggered animations
     */
    function initAnimations() {
        // Hero section animations
        animateHero();

        // Section header animations
        animateSectionHeaders();

        // Stagger animations for grids/lists
        animateStaggeredElements();

        // Individual animated elements
        animateElements();
    }

    /**
     * Hero section entrance animations
     */
    function animateHero() {
        const heroCopy = document.querySelector('.artly-hero-copy');
        if (!heroCopy) return;

        // Create timeline for hero
        const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });

        // Animate eyebrow
        tl.from('.artly-eyebrow', {
            y: 30,
            opacity: 0,
            duration: 0.6
        })
        // Animate title
        .from('.artly-hero-title', {
            y: 50,
            opacity: 0,
            duration: 0.8
        }, '-=0.3')
        // Animate subtitle
        .from('.artly-hero-sub', {
            y: 30,
            opacity: 0,
            duration: 0.7
        }, '-=0.4')
        // Animate buttons
        .from('.artly-hero-actions .artly-btn', {
            y: 20,
            opacity: 0,
            duration: 0.6,
            stagger: 0.15
        }, '-=0.3')
        // Animate meta
        .from('.artly-hero-meta', {
            opacity: 0,
            duration: 0.5
        }, '-=0.2');

        // Animate hero card separately
        gsap.from('.artly-hero-card', {
            x: 80,
            opacity: 0,
            duration: 1,
            ease: 'power3.out',
            delay: 0.4
        });

        // Floating animation for card
        gsap.to('.artly-hero-card', {
            y: -15,
            duration: 3,
            ease: 'power1.inOut',
            yoyo: true,
            repeat: -1
        });
    }

    /**
     * Animate section headers
     */
    function animateSectionHeaders() {
        const headers = document.querySelectorAll('.artly-section-header');
        
        headers.forEach((header, index) => {
            gsap.from(header, {
                scrollTrigger: {
                    trigger: header,
                    start: defaults.start,
                    toggleActions: 'play none none none'
                },
                y: 40,
                opacity: 0,
                duration: defaults.duration,
                ease: defaults.ease
            });
        });
    }

    /**
     * Animate staggered elements (benefits, steps, personas)
     */
    function animateStaggeredElements() {
        // Benefits grid
        gsap.utils.toArray('.artly-benefits-grid .artly-benefit').forEach((el, i) => {
            gsap.from(el, {
                scrollTrigger: {
                    trigger: el.closest('.artly-section-benefits'),
                    start: defaults.start,
                    toggleActions: 'play none none none'
                },
                y: 50,
                opacity: 0,
                duration: defaults.duration,
                ease: defaults.ease,
                delay: i * defaults.stagger
            });
        });

        // Steps grid
        gsap.utils.toArray('.artly-steps-grid .artly-step').forEach((el, i) => {
            gsap.from(el, {
                scrollTrigger: {
                    trigger: el.closest('.artly-section-steps'),
                    start: defaults.start,
                    toggleActions: 'play none none none'
                },
                y: 50,
                opacity: 0,
                duration: defaults.duration,
                ease: defaults.ease,
                delay: i * 0.15
            });
        });

        // Personas
        gsap.utils.toArray('.artly-personas-list .artly-persona').forEach((el, i) => {
            gsap.from(el, {
                scrollTrigger: {
                    trigger: el.closest('.artly-section-personas'),
                    start: defaults.start,
                    toggleActions: 'play none none none'
                },
                x: -50,
                opacity: 0,
                duration: defaults.duration,
                ease: defaults.ease,
                delay: i * defaults.stagger
            });
        });
    }

    /**
     * Animate individual elements with data attributes
     */
    function animateElements() {
        const animatedElements = document.querySelectorAll('[data-animate]');

        animatedElements.forEach((el) => {
            const animation = el.getAttribute('data-animate');
            const delay = parseFloat(el.getAttribute('data-delay')) || 0;
            const section = el.closest('[data-scroll-section]');

            let animationProps = {};

            switch (animation) {
                case 'fade-up':
                    animationProps = {
                        y: 50,
                        opacity: 0
                    };
                    break;
                case 'fade-down':
                    animationProps = {
                        y: -50,
                        opacity: 0
                    };
                    break;
                case 'fade-left':
                    animationProps = {
                        x: -50,
                        opacity: 0
                    };
                    break;
                case 'fade-right':
                    animationProps = {
                        x: 50,
                        opacity: 0
                    };
                    break;
                case 'slide-left':
                    animationProps = {
                        x: 100,
                        opacity: 0
                    };
                    break;
                case 'scale-up':
                    animationProps = {
                        scale: 0.9,
                        opacity: 0
                    };
                    break;
                default:
                    animationProps = {
                        opacity: 0
                    };
            }

            gsap.from(el, {
                scrollTrigger: {
                    trigger: section || el,
                    start: defaults.start,
                    toggleActions: defaults.once ? 'play none none none' : 'play reverse play reverse'
                },
                ...animationProps,
                duration: defaults.duration,
                ease: defaults.ease,
                delay: delay
            });
        });
    }

    /**
     * Parallax effects for backgrounds
     */
    function initParallaxEffects() {
        // Parallax on hero background gradient
        gsap.to('.artly-front', {
            scrollTrigger: {
                trigger: '.artly-front',
                start: 'top top',
                end: 'bottom top',
                scrub: true
            },
            backgroundPosition: '50% 100%'
        });

        // Parallax on hero card
        gsap.to('.artly-hero-card', {
            scrollTrigger: {
                trigger: '.artly-hero',
                start: 'top top',
                end: 'bottom top',
                scrub: 1
            },
            y: 100,
            opacity: 0.5
        });
    }

    /**
     * Enhanced card interactions
     */
    function initCardInteractions() {
        const cards = document.querySelectorAll('.artly-benefit, .artly-step, .artly-persona, .artly-hero-card');

        cards.forEach((card) => {
            // Hover scale effect
            card.addEventListener('mouseenter', function() {
                gsap.to(this, {
                    scale: 1.03,
                    y: -5,
                    duration: 0.3,
                    ease: 'power2.out'
                });
            });

            card.addEventListener('mouseleave', function() {
                gsap.to(this, {
                    scale: 1,
                    y: 0,
                    duration: 0.3,
                    ease: 'power2.out'
                });
            });
        });
    }

    // Optional: Lottie animation loader
    function initLottieAnimations() {
        if (typeof lottie === 'undefined') {
            console.warn('Lottie not loaded');
            return;
        }

        // Example: Load a Lottie animation (you'll need a Lottie JSON file)
        const lottieContainer = document.querySelector('.lottie-animation');
        if (lottieContainer) {
            lottie.loadAnimation({
                container: lottieContainer,
                renderer: 'svg',
                loop: true,
                autoplay: true,
                path: get_template_directory_uri() + '/assets/animations/hero-animation.json' // Add your Lottie file
            });
        }
    }

})();