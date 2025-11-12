(function () {
  document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('.artly-my-subscriptions-page');
    if (!root) {
      return;
    }

    var settings = window.artlyMySubscriptionsSettings || {};
    var restBase = typeof settings.restUrl === 'string' ? settings.restUrl : '';
    if (restBase && restBase.slice(-1) !== '/') {
      restBase += '/';
    }
    var nonce = settings.nonce || '';

    // Modal system (reuse from downloads.js pattern)
    function removeExistingModal() {
      var existingModal = document.querySelector('.artly-modal-overlay');
      if (existingModal) {
        existingModal.parentNode.removeChild(existingModal);
      }
    }

    function showModal(title, message, primaryButtonText, onPrimaryClick, secondaryButtonText, onSecondaryClick) {
      removeExistingModal();

      var overlay = document.createElement('div');
      overlay.className = 'artly-modal-overlay';
      overlay.setAttribute('role', 'dialog');
      overlay.setAttribute('aria-modal', 'true');
      overlay.setAttribute('aria-labelledby', 'artly-modal-title');

      var container = document.createElement('div');
      container.className = 'artly-modal-container';

      var header = document.createElement('div');
      header.className = 'artly-modal-header';
      var titleEl = document.createElement('h3');
      titleEl.id = 'artly-modal-title';
      titleEl.textContent = title;
      header.appendChild(titleEl);

      var body = document.createElement('div');
      body.className = 'artly-modal-body';
      var messageEl = document.createElement('p');
      messageEl.textContent = message;
      body.appendChild(messageEl);

      var footer = document.createElement('div');
      footer.className = 'artly-modal-footer';

      if (secondaryButtonText && typeof onSecondaryClick === 'function') {
        var secondaryBtn = document.createElement('button');
        secondaryBtn.className = 'artly-modal-btn-secondary';
        secondaryBtn.type = 'button';
        secondaryBtn.textContent = secondaryButtonText;
        secondaryBtn.addEventListener('click', function () {
          onSecondaryClick();
          closeModal();
        });
        footer.appendChild(secondaryBtn);
      }

      var primaryBtn = document.createElement('button');
      primaryBtn.className = 'artly-modal-btn-primary';
      primaryBtn.type = 'button';
      primaryBtn.textContent = primaryButtonText || 'Close';
      footer.appendChild(primaryBtn);

      container.appendChild(header);
      container.appendChild(body);
      container.appendChild(footer);
      overlay.appendChild(container);
      document.body.appendChild(overlay);

      setTimeout(function () {
        primaryBtn.focus();
      }, 100);

      function closeModal() {
        overlay.classList.add('is-closing');
        setTimeout(function () {
          if (overlay.parentNode) {
            overlay.parentNode.removeChild(overlay);
          }
        }, 200);
        document.removeEventListener('keydown', escapeHandler);
      }

      primaryBtn.addEventListener('click', function () {
        if (typeof onPrimaryClick === 'function') {
          onPrimaryClick();
        }
        closeModal();
      });

      overlay.addEventListener('click', function (event) {
        if (event.target === overlay) {
          closeModal();
        }
      });

      var escapeHandler = function (event) {
        if (event.key === 'Escape' || event.keyCode === 27) {
          closeModal();
        }
      };

      document.addEventListener('keydown', escapeHandler);

      return {
        close: closeModal,
      };
    }

    // Cancel subscription handler
    var cancelButtons = root.querySelectorAll('[data-action="cancel"]');
    cancelButtons.forEach(function (button) {
      button.addEventListener('click', function (event) {
        event.preventDefault();

        var subscriptionId = button.getAttribute('data-subscription-id');
        if (!subscriptionId) {
          return;
        }

        // Show confirmation modal
        showModal(
          'Cancel Subscription',
          'Are you sure you want to cancel your subscription? You will retain access until the end of your current billing period, and any remaining points will stay in your wallet.',
          'Cancel Subscription',
          function () {
            // User confirmed - proceed with cancellation
            button.disabled = true;
            button.classList.add('is-loading');
            button.textContent = 'Cancelling...';

            var headers = {
              'Content-Type': 'application/json',
            };

            if (nonce) {
              headers['X-WP-Nonce'] = nonce;
            }

            var endpoint = restBase.replace('/artly/v1/', '/nehtw/v1/') + 'subscription/cancel';
            if (!endpoint || endpoint.indexOf('undefined') !== -1) {
              endpoint = (window.location.origin || '') + '/wp-json/nehtw/v1/subscription/cancel';
            }

            fetch(endpoint, {
              method: 'POST',
              headers: headers,
              credentials: 'same-origin',
              body: JSON.stringify({
                reason: '',
                cancel_immediately: false,
              }),
            })
              .then(function (response) {
                return response
                  .json()
                  .then(function (data) {
                    return { ok: response.ok, status: response.status, data: data };
                  })
                  .catch(function () {
                    return { ok: response.ok, status: response.status, data: {} };
                  });
              })
              .then(function (result) {
                button.disabled = false;
                button.classList.remove('is-loading');
                button.textContent = 'Cancel Subscription';

                if (!result || !result.ok) {
                  var errorMsg = result && result.data && result.data.message
                    ? result.data.message
                    : 'We couldn\'t cancel your subscription right now. Please try again later or contact support.';
                  showModal('Error', errorMsg, 'Close');
                  return;
                }

                if (result.data && result.data.success) {
                  showModal(
                    'Subscription Cancelled',
                    'Your subscription has been cancelled. You will retain access until the end of your current billing period. Any remaining points will stay in your wallet.',
                    'OK',
                    function () {
                      // Reload page to show updated status
                      window.location.reload();
                    }
                  );
                } else {
                  showModal(
                    'Error',
                    result.data && result.data.message
                      ? result.data.message
                      : 'We couldn\'t cancel your subscription right now. Please try again later.',
                    'Close'
                  );
                }
              })
              .catch(function (error) {
                console.error('Cancel subscription error:', error);
                button.disabled = false;
                button.classList.remove('is-loading');
                button.textContent = 'Cancel Subscription';
                showModal(
                  'Connection Error',
                  'We couldn\'t connect to the server. Please check your internet connection and try again.',
                  'Close'
                );
              });
          },
          'Keep Subscription',
          function () {
            // User cancelled - do nothing
          }
        );
      });
    });
  });
})();

