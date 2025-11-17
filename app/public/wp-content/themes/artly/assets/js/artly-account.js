/**
 * Artly My Account Page Enhancements
 * Smooth interactions and UX improvements
 */

(function ($) {
  'use strict';

  $(document).ready(function () {
    // Smooth scroll for anchor links
    $(document).on('click', 'a[href^="#"]', function (e) {
      const id = $(this).attr('href');
      if (id.length > 1 && $(id).length) {
        e.preventDefault();
        $('html, body').animate(
          {
            scrollTop: $(id).offset().top - 80,
          },
          250
        );
      }
    });

    // Add loading state to form submissions
    $('.woocommerce-EditAccountForm').on('submit', function () {
      const $button = $(this).find('button[type="submit"]');
      const $buttonSpan = $button.find('span');
      const originalText = $buttonSpan.length ? $buttonSpan.text() : $button.text();
      
      $button.prop('disabled', true);
      if ($buttonSpan.length) {
        $buttonSpan.text('Saving...');
      } else {
        $button.text('Saving...');
      }

      // Re-enable after timeout (in case of validation error)
      setTimeout(function () {
        $button.prop('disabled', false);
        if ($buttonSpan.length) {
          $buttonSpan.text(originalText);
        } else {
          $button.text(originalText);
        }
      }, 5000);
    });

    // Enhance file card interactions
    $('.file-card').on('mouseenter', function () {
      $(this).css('transform', 'translateY(-4px)');
    }).on('mouseleave', function () {
      $(this).css('transform', 'translateY(0)');
    });

    // Password toggle functionality for edit account form
    $(document).on('click', '.artly-password-toggle', function (e) {
      e.preventDefault();
      const $button = $(this);
      const targetId = $button.data('target');
      const $input = $('#' + targetId);
      const $eyeIcon = $button.find('.artly-icon-eye');
      const $eyeOffIcon = $button.find('.artly-icon-eye-off');

      if ($input.length) {
        if ($input.attr('type') === 'password') {
          $input.attr('type', 'text');
          $eyeIcon.hide();
          $eyeOffIcon.show();
          $button.attr('aria-label', 'Hide password');
        } else {
          $input.attr('type', 'password');
          $eyeIcon.show();
          $eyeOffIcon.hide();
          $button.attr('aria-label', 'Show password');
        }
      }
    });
  });
})(jQuery);

