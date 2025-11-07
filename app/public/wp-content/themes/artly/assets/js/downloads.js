(function () {
  document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('.artly-downloads-page');
    if (!root) {
      return;
    }

    var settings = window.artlyDownloadsSettings || {};
    var restBase = typeof settings.restUrl === 'string' ? settings.restUrl : '';
    if (restBase && restBase.slice(-1) !== '/') {
      restBase += '/';
    }
    var nonce = settings.nonce || '';

    // ========== MODAL SYSTEM ==========

    function removeExistingModal() {
      var existingModal = document.querySelector('.artly-modal-overlay');
      if (existingModal) {
        existingModal.parentNode.removeChild(existingModal);
      }
    }

    function showModal(title, message, primaryButtonText, onPrimaryClick) {
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

    // ========== TAB NAVIGATION ==========

    var tabs = root.querySelectorAll('[data-downloads-tab]');
    var lists = root.querySelectorAll('[data-downloads-list]');

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        var target = tab.getAttribute('data-downloads-tab');
        tabs.forEach(function (btn) {
          btn.classList.remove('is-active');
        });
        tab.classList.add('is-active');

        lists.forEach(function (list) {
          var type = list.getAttribute('data-downloads-list');
          if (!target || target === 'all') {
            list.style.display = '';
            return;
          }
          list.style.display = type === target ? '' : 'none';
        });
      });
    });

    // ========== DOWNLOAD HANDLERS ==========

    root.addEventListener('click', function (event) {
      var button = event.target.closest('[data-download-kind]');
      if (!button) {
        return;
      }

      event.preventDefault();

      if (!restBase) {
        console.warn('Download endpoint not configured.');
        showModal(
          'Configuration error',
          'The download system is not properly configured. Please contact support.',
          'Close'
        );
        return;
      }

      var kind = button.getAttribute('data-download-kind');
      var id = button.getAttribute('data-download-id');
      var historyId = button.getAttribute('data-history-id');

      if (!kind || !id) {
        return;
      }

      if (button.disabled) {
        return;
      }

      var originalText = button.textContent;
      button.disabled = true;
      button.classList.add('is-loading');
      button.textContent = 'Generating link…';

      var headers = {
        'Content-Type': 'application/json',
      };

      if (nonce) {
        headers['X-WP-Nonce'] = nonce;
      }

      var endpoint = '';
      var payload = {};

      if (kind === 'stock' && historyId) {
        endpoint = restBase.replace('/nehtw/v1/', '/artly/v1/') + 'download-redownload';
        payload = { history_id: parseInt(historyId, 10) };
      } else {
        endpoint = restBase + 'download-link';
        payload = { kind: kind, id: id };
      }

      fetch(endpoint, {
        method: 'POST',
        headers: headers,
        body: JSON.stringify(payload),
        credentials: 'same-origin',
      })
        .then(function (response) {
          var statusCode = response.status;

          return response
            .json()
            .then(function (data) {
              return { ok: response.ok, status: statusCode, data: data };
            })
            .catch(function () {
              return { ok: response.ok, status: statusCode, data: {} };
            });
        })
        .then(function (result) {
          button.disabled = false;
          button.classList.remove('is-loading');
          button.textContent = originalText;

          if (!result) {
            showModal(
              'Unexpected error',
              'Something went wrong. Please try again in a moment.',
              'Close'
            );
            return;
          }

          if (result.status === 409) {
            showModal(
              'Download not ready',
              (result.data && result.data.message) || 'This file is still processing. Please try again shortly.',
              'Close'
            );
            return;
          }

          if (result.status === 404) {
            showModal(
              'Download not found',
              (result.data && result.data.message) || 'This download could not be found. It may have expired or been removed.',
              'Close'
            );
            return;
          }

          if (result.status === 401 || result.status === 403) {
            showModal(
              'Authentication required',
              'Your session has expired. Please log in again to continue.',
              'Go to login',
              function () {
                window.location.href = '/wp-login.php?redirect_to=' + encodeURIComponent(window.location.href);
              }
            );
            return;
          }

          if (!result.ok || result.status >= 500) {
            var errorMsg = result.data && result.data.message
              ? result.data.message
              : 'We couldn\'t generate a download link right now. Please try again later.';
            showModal('Server error', errorMsg, 'Close');
            return;
          }

          var downloadUrl = '';
          if (result.data && result.data.download_url) {
            downloadUrl = result.data.download_url;
          } else if (result.data && result.data.url) {
            downloadUrl = result.data.url;
          }

          if (downloadUrl) {
            window.open(downloadUrl, '_blank');
          } else {
            showModal(
              'Download error',
              'We couldn\'t generate a download link right now. Please try again later.',
              'Close'
            );
          }
        })
        .catch(function (error) {
          console.error('Download error:', error);
          button.disabled = false;
          button.classList.remove('is-loading');
          button.textContent = originalText;

          showModal(
            'Connection error',
            'We couldn\'t connect to the server. Please check your internet connection and try again.',
            'Close'
          );
        });
    });
  });
})();
