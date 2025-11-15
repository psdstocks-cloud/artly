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
      const originalText = $button.text();
      $button.prop('disabled', true).text('Saving...');

      // Re-enable after timeout (in case of validation error)
      setTimeout(function () {
        $button.prop('disabled', false).text(originalText);
      }, 5000);
    });

    // Enhance file card interactions
    $('.file-card').on('mouseenter', function () {
      $(this).css('transform', 'translateY(-4px)');
    }).on('mouseleave', function () {
      $(this).css('transform', 'translateY(0)');
    });
  });
})(jQuery);

