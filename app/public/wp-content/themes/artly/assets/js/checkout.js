/**
 * Artly Checkout Page Enhancements
 * Smooth interactions and UX improvements for the checkout page
 */

(function () {
  'use strict';

  // Check if we're on the checkout page
  if (!document.body.classList.contains('woocommerce-checkout')) {
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

    // Enhance form fields
    enhanceFormFields();

    // Add loading state to place order button
    enhancePlaceOrderButton();

    // Smooth scroll to errors
    scrollToErrors();

    // Auto-focus first empty required field
    autoFocusFirstEmpty();

    // Add visual feedback for form interactions
    addFormFeedback();
  }

  /**
   * Enhance form fields with better UX
   */
  function enhanceFormFields() {
    const inputs = document.querySelectorAll('.artly-checkout-section input, .artly-checkout-section select, .artly-checkout-section textarea');

    inputs.forEach((input) => {
      // Add focus/blur classes for styling
      input.addEventListener('focus', function () {
        this.closest('.woocommerce-form-row')?.classList.add('is-focused');
      });

      input.addEventListener('blur', function () {
        this.closest('.woocommerce-form-row')?.classList.remove('is-focused');
        // Validate on blur
        if (this.hasAttribute('required') && !this.value.trim()) {
          this.closest('.woocommerce-form-row')?.classList.add('has-error');
        } else {
          this.closest('.woocommerce-form-row')?.classList.remove('has-error');
        }
      });

      // Real-time validation feedback
      input.addEventListener('input', function () {
        if (this.closest('.woocommerce-form-row')?.classList.contains('has-error')) {
          if (this.value.trim()) {
            this.closest('.woocommerce-form-row')?.classList.remove('has-error');
          }
        }
      });
    });
  }

  /**
   * Add loading state to place order button
   */
  function enhancePlaceOrderButton() {
    const placeOrderBtn = document.getElementById('place_order');
    if (!placeOrderBtn) {
      return;
    }

    const form = document.querySelector('.woocommerce-checkout');
    if (!form) {
      return;
    }

    form.addEventListener('submit', function (e) {
      // Only add loading if form is valid
      if (form.checkValidity()) {
        placeOrderBtn.disabled = true;
        placeOrderBtn.style.opacity = '0.7';
        placeOrderBtn.style.cursor = 'not-allowed';

        // Add loading text
        const originalText = placeOrderBtn.textContent;
        placeOrderBtn.innerHTML = '<span>' + (placeOrderBtn.dataset.loadingText || 'Processing...') + '</span>';

        // Re-enable after timeout (in case of validation error)
        setTimeout(() => {
          if (placeOrderBtn.disabled) {
            placeOrderBtn.disabled = false;
            placeOrderBtn.style.opacity = '';
            placeOrderBtn.style.cursor = '';
            placeOrderBtn.textContent = originalText;
          }
        }, 5000);
      }
    });
  }

  /**
   * Smooth scroll to first error on form submission
   */
  function scrollToErrors() {
    const form = document.querySelector('.woocommerce-checkout');
    if (!form) {
      return;
    }

    // Watch for error messages
    const observer = new MutationObserver((mutations) => {
      const error = document.querySelector('.woocommerce-error, .woocommerce-form-row.has-error');
      if (error) {
        setTimeout(() => {
          error.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
          });
        }, 100);
      }
    });

    observer.observe(form, {
      childList: true,
      subtree: true,
    });

    // Also check on form submit
    form.addEventListener('submit', function () {
      setTimeout(() => {
        const error = document.querySelector('.woocommerce-error, .woocommerce-form-row.has-error');
        if (error) {
          error.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
          });
        }
      }, 300);
    });
  }

  /**
   * Auto-focus first empty required field
   */
  function autoFocusFirstEmpty() {
    const requiredInputs = Array.from(
      document.querySelectorAll('.artly-checkout-section input[required], .artly-checkout-section select[required], .artly-checkout-section textarea[required]')
    );

    const firstEmpty = requiredInputs.find((input) => !input.value.trim());
    if (firstEmpty) {
      setTimeout(() => {
        firstEmpty.focus();
      }, 300);
    }
  }

  /**
   * Add visual feedback for form interactions
   */
  function addFormFeedback() {
    // Add success state to fields when valid
    const inputs = document.querySelectorAll('.artly-checkout-section input, .artly-checkout-section select');
    inputs.forEach((input) => {
      input.addEventListener('blur', function () {
        if (this.value.trim() && this.checkValidity()) {
          this.closest('.woocommerce-form-row')?.classList.add('is-valid');
        } else {
          this.closest('.woocommerce-form-row')?.classList.remove('is-valid');
        }
      });
    });
  }

  /**
   * Enhance payment method selection
   */
  const paymentMethods = document.querySelectorAll('.wc_payment_methods input[type="radio"]');
  paymentMethods.forEach((radio) => {
    radio.addEventListener('change', function () {
      // Add visual feedback
      document.querySelectorAll('.wc_payment_methods li').forEach((li) => {
        li.classList.remove('is-selected');
      });
      this.closest('li')?.classList.add('is-selected');
    });

    // Set initial state
    if (radio.checked) {
      radio.closest('li')?.classList.add('is-selected');
    }
  });

  /**
   * Keyboard navigation enhancements
   */
  document.addEventListener('keydown', function (e) {
    // Tab navigation: skip to next section on Enter in last field
    if (e.key === 'Enter' && e.target.tagName === 'INPUT' && !e.target.closest('form')?.checkValidity()) {
      e.preventDefault();
      const inputs = Array.from(document.querySelectorAll('input, select, textarea'));
      const currentIndex = inputs.indexOf(e.target);
      if (currentIndex < inputs.length - 1) {
        inputs[currentIndex + 1].focus();
      }
    }
  });

  /**
   * Animate billing info card on load (if subscription)
   */
  const billingInfo = document.querySelector('.artly-billing-info');
  if (billingInfo && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    billingInfo.style.opacity = '0';
    billingInfo.style.transform = 'translateY(20px)';
    setTimeout(() => {
      billingInfo.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
      billingInfo.style.opacity = '1';
      billingInfo.style.transform = 'translateY(0)';
    }, 200);
  }
})();

