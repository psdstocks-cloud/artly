/**
 * Animated Stats Counter
 * Uses GSAP to animate numbers when section enters viewport
 */

(function() {
    'use strict';

    // Check if GSAP is available
    if (typeof gsap === 'undefined' || typeof ScrollTrigger === 'undefined') {
        console.warn('GSAP or ScrollTrigger not loaded. Stats counter will not animate.');
        return;
    }

    // Register ScrollTrigger plugin
    gsap.registerPlugin(ScrollTrigger);

    /**
     * Format number with locale-specific separators
     */
    function formatNumber(value, lang) {
        const num = Math.floor(value);
        if (lang === 'ar') {
            // Use Western numerals for Arabic (more common in tech)
            return num.toLocaleString('en-US');
        }
        return num.toLocaleString('en-US');
    }

    /**
     * Animate stat number
     */
    function animateStatNumber(element) {
        const targetValue = parseFloat(element.dataset.value) || 0;
        const lang = document.documentElement.lang || 'en';
        const suffix = element.textContent.match(/[^0-9]+$/)?.[0] || '';
        const hasPlus = element.textContent.includes('+');
        
        // Clear any existing text
        const baseText = element.textContent.replace(/[\d,]+/, '').trim();
        
        gsap.to(element, {
            innerText: targetValue,
            duration: 2,
            ease: 'power2.out',
            snap: { innerText: 1 },
            onUpdate: function() {
                const currentValue = Math.ceil(this.targets()[0].innerText);
                let formatted = formatNumber(currentValue, lang);
                
                if (hasPlus && currentValue >= targetValue) {
                    formatted += '+';
                }
                
                if (suffix && !hasPlus) {
                    formatted += ' ' + suffix;
                }
                
                element.innerText = formatted;
            },
            onComplete: function() {
                // Ensure final value is set correctly
                let finalValue = formatNumber(targetValue, lang);
                if (hasPlus) {
                    finalValue += '+';
                }
                if (suffix && !hasPlus) {
                    finalValue += ' ' + suffix;
                }
                element.innerText = finalValue;
            }
        });
    }

    /**
     * Initialize stats counter
     */
    function initStatsCounter() {
        const statsSection = document.querySelector('.artly-section-stats');
        if (!statsSection) {
            return;
        }

        const statNumbers = statsSection.querySelectorAll('.artly-stat-number');
        if (statNumbers.length === 0) {
            return;
        }

        // Create ScrollTrigger for stats section
        ScrollTrigger.create({
            trigger: statsSection,
            start: 'top 75%',
            once: true, // Only trigger once
            onEnter: function() {
                statNumbers.forEach(function(statNumber) {
                    animateStatNumber(statNumber);
                });
            }
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initStatsCounter);
    } else {
        initStatsCounter();
    }

})();

