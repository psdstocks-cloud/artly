/**
 * Artly Cart Page Enhancements
 * Smooth interactions and UX improvements for the cart page
 */

(function () {
  'use strict';

  // Check if we're on the cart page
  if (!document.body.classList.contains('woocommerce-cart')) {
    return;
  }

  // Wait for DOM to be ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  function init() {
    // Respect reduced motion preference
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Enhanced remove item feedback
    enhanceRemoveItems();

    // Smooth scroll to top on page load (if coming from pricing page)
    if (!prefersReducedMotion && document.referrer.includes('/pricing')) {
      setTimeout(() => {
        window.scrollTo({
          top: 0,
          behavior: 'smooth',
        });
      }, 100);
    }

    // Auto-focus coupon input when coupon toggle opens
    const couponToggle = document.querySelector('.coupon-toggle');
    if (couponToggle) {
      couponToggle.addEventListener('toggle', function () {
        if (this.open) {
          const input = this.querySelector('input[type="text"]');
          if (input) {
            setTimeout(() => {
              input.focus();
            }, 100);
          }
        }
      });
    }

    // Add loading state to checkout button
    const checkoutButton = document.querySelector('.btn-primary.btn-glow');
    if (checkoutButton) {
      checkoutButton.addEventListener('click', function (e) {
        // Only add loading if not already navigating
        if (!this.classList.contains('is-loading')) {
          this.classList.add('is-loading');
          this.style.opacity = '0.8';
          this.style.pointerEvents = 'none';

          // Remove loading state after navigation or timeout
          setTimeout(() => {
            this.classList.remove('is-loading');
            this.style.opacity = '';
            this.style.pointerEvents = '';
          }, 3000);
        }
      });
    }
  }

  /**
   * Enhance remove item links with smooth feedback
   */
  function enhanceRemoveItems() {
    const removeLinks = document.querySelectorAll('.artly-cart-card .btn-link[href*="remove_item"]');

    removeLinks.forEach((link) => {
      link.addEventListener('click', function (e) {
        const card = this.closest('.artly-cart-card');
        if (card) {
          // Add fade-out animation
          card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
          card.style.opacity = '0.5';
          card.style.transform = 'translateX(-20px)';

          // If user cancels (browser back), restore
          window.addEventListener(
            'pageshow',
            function restoreCard() {
              if (card) {
                card.style.opacity = '';
                card.style.transform = '';
              }
              window.removeEventListener('pageshow', restoreCard);
            },
            { once: true }
          );
        }
      });
    });
  }

  /**
   * Update cart totals when currency changes (if currency toggle exists)
   */
  function watchCurrencyChanges() {
    // Check if currency toggle exists (from pricing page)
    if (typeof window.artlyCurrency !== 'undefined' && window.artlyCurrency.current) {
      const currencyObserver = new MutationObserver(() => {
        // If currency changes, reload cart to update prices
        const currentCurrency = window.artlyCurrency?.current;
        const storedCurrency = localStorage.getItem('artly_currency');

        if (currentCurrency && storedCurrency && currentCurrency !== storedCurrency) {
          // Optionally reload or update via AJAX
          // For now, just log - full reload handled by WooCommerce
          console.log('Currency changed, cart will update on next interaction');
        }
      });

      // Observe currency changes (if there's a toggle on the page)
      const currencyToggle = document.getElementById('currencyToggle');
      if (currencyToggle) {
        currencyToggle.addEventListener('click', () => {
          // Cart prices will update when WooCommerce recalculates
          setTimeout(() => {
            window.location.reload();
          }, 500);
        });
      }
    }
  }

  // Initialize currency watching if needed
  watchCurrencyChanges();

  /**
   * Add smooth expand/collapse for FAQ items
   */
  const faqItems = document.querySelectorAll('.faq-mini details');
  faqItems.forEach((details) => {
    details.addEventListener('toggle', function () {
      if (this.open && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        const content = this.querySelector('p');
        if (content) {
          content.style.opacity = '0';
          content.style.transform = 'translateY(-10px)';
          setTimeout(() => {
            content.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            content.style.opacity = '1';
            content.style.transform = 'translateY(0)';
          }, 10);
        }
      }
    });
  });

  /**
   * Keyboard navigation enhancements
   */
  document.addEventListener('keydown', function (e) {
    // ESC to close open details
    if (e.key === 'Escape') {
      const openDetails = document.querySelector('.coupon-toggle[open], .faq-mini details[open]');
      if (openDetails) {
        openDetails.open = false;
      }
    }
  });

  /**
   * Add visual feedback for form interactions
   */
  const couponForm = document.querySelector('.artly-coupon-form');
  if (couponForm) {
    const input = couponForm.querySelector('input[type="text"]');
    const button = couponForm.querySelector('button');

    if (input && button) {
      input.addEventListener('input', function () {
        if (this.value.trim().length > 0) {
          button.style.opacity = '1';
          button.style.transform = 'scale(1)';
        } else {
          button.style.opacity = '0.7';
          button.style.transform = 'scale(0.98)';
        }
      });

      // Initial state
      if (input.value.trim().length === 0) {
        button.style.opacity = '0.7';
      }
    }
  }
})();

