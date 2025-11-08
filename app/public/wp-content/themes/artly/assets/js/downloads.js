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

    // ========== RETRY HELPER ==========

    /**
     * Perform a fetch with retry and exponential backoff for transient errors.
     *
     * @param {string} endpoint - The REST endpoint URL.
     * @param {Object} options - Fetch options (method, headers, body, etc.).
     * @param {number} maxAttempts - Total attempts (e.g., 3).
     * @param {function} onAttemptState - Optional callback(attempt, maxAttempts) to update UI.
     * @returns {Promise<{ ok: boolean, status: number, data: any }>}
     */
    function fetchWithRetry(endpoint, options, maxAttempts, onAttemptState) {
      var attempt = 1;

      function attemptRequest() {
        if (typeof onAttemptState === 'function') {
          onAttemptState(attempt, maxAttempts);
        }

        return fetch(endpoint, options)
          .then(function (response) {
            var statusCode = response.status;

            return response
              .json()
              .catch(function () {
                return {};
              })
              .then(function (data) {
                return {
                  ok: response.ok,
                  status: statusCode,
                  data: data || {},
                };
              });
          })
          .catch(function (err) {
            // Treat network error as pseudo-response with status 0
            return {
              ok: false,
              status: 0,
              error: err,
              data: {},
            };
          })
          .then(function (result) {
            // Decide whether to retry
            var status = result.status;

            // Success: no retry needed
            if (result.ok && status >= 200 && status < 300) {
              return result;
            }

            // Non-retriable errors (4xx, including 409, 429, etc.)
            if (status >= 400 && status < 500 && status !== 0) {
              return result;
            }

            // Transient error: 5xx or network error (status 0)
            if (attempt >= maxAttempts) {
              return result;
            }

            // Exponential backoff: 1s, 2s, 4s
            var delay = Math.pow(2, attempt - 1) * 1000;

            return new Promise(function (resolve) {
              setTimeout(function () {
                attempt++;
                resolve(attemptRequest());
              }, delay);
            });
          });
      }

      return attemptRequest();
    }

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
    var currentTab = 'all';

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        var target = tab.getAttribute('data-downloads-tab');
        currentTab = target || 'all';
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

        // Apply filters after tab change
        applyFiltersAndSort();
      });
    });

    // ========== SEARCH / FILTER / SORT ==========

    var searchInput = root.querySelector('[data-downloads-search]');
    var statusSelect = root.querySelector('[data-downloads-status-filter]');
    var sortSelect = root.querySelector('[data-downloads-sort]');
    var allItems = Array.prototype.slice.call(
      root.querySelectorAll('.artly-download-item')
    );

    var currentSearch = '';
    var currentStatus = '';
    var currentSort = 'date_desc';

    // Get all list containers
    var listContainers = Array.prototype.slice.call(lists);

    function applyFiltersAndSort() {
      if (allItems.length === 0) {
        return;
      }

      // Process each list container separately
      listContainers.forEach(function (container) {
        var listType = container.getAttribute('data-downloads-list');
        var isVisible =
          currentTab === 'all' ||
          (currentTab === 'stock' && listType === 'stock') ||
          (currentTab === 'ai' && listType === 'ai');

        // Skip hidden containers
        if (!isVisible || container.style.display === 'none') {
          return;
        }

        var listElement = container.querySelector('.downloads-list');
        if (!listElement) {
          return;
        }

        // Get items in this list
        var itemsInList = Array.prototype.slice.call(
          listElement.querySelectorAll('.artly-download-item')
        );

        if (itemsInList.length === 0) {
          return;
        }

        // 1) Filter by search
        itemsInList.forEach(function (item) {
          var text = (currentSearch || '').trim();
          if (!text) {
            item.__matchesSearch = true;
          } else {
            var title = (item.getAttribute('data-download-title') || '').toLowerCase();
            var provider = (item.getAttribute('data-download-provider') || '').toLowerCase();
            var remoteId = (item.getAttribute('data-download-remote-id') || '').toLowerCase();
            var url = (item.getAttribute('data-download-url') || '').toLowerCase();

            var haystack = [title, provider, remoteId, url].join(' ');
            item.__matchesSearch = haystack.indexOf(text) !== -1;
          }
        });

        // 2) Filter by status
        itemsInList.forEach(function (item) {
          if (!currentStatus) {
            item.__matchesStatus = true;
          } else {
            var status = (item.getAttribute('data-download-status') || '').toLowerCase();
            item.__matchesStatus = status === currentStatus;
          }
        });

        // 3) Determine visible items
        var visibleItems = itemsInList.filter(function (item) {
          return item.__matchesSearch && item.__matchesStatus;
        });

        // 4) Sort visible items
        visibleItems.sort(function (a, b) {
          var aDate = parseInt(a.getAttribute('data-download-date-ts') || '0', 10);
          var bDate = parseInt(b.getAttribute('data-download-date-ts') || '0', 10);
          var aProvider = (a.getAttribute('data-download-provider') || '').toLowerCase();
          var bProvider = (b.getAttribute('data-download-provider') || '').toLowerCase();
          var aStatus = (a.getAttribute('data-download-status') || '').toLowerCase();
          var bStatus = (b.getAttribute('data-download-status') || '').toLowerCase();
          var aPoints = parseFloat(a.getAttribute('data-download-points') || '0');
          var bPoints = parseFloat(b.getAttribute('data-download-points') || '0');

          switch (currentSort) {
            case 'date_asc':
              return aDate - bDate;
            case 'provider_asc':
              if (aProvider < bProvider) return -1;
              if (aProvider > bProvider) return 1;
              return 0;
            case 'provider_desc':
              if (aProvider > bProvider) return -1;
              if (aProvider < bProvider) return 1;
              return 0;
            case 'status_asc':
              if (aStatus < bStatus) return -1;
              if (aStatus > bStatus) return 1;
              return 0;
            case 'status_desc':
              if (aStatus > bStatus) return -1;
              if (aStatus < bStatus) return 1;
              return 0;
            case 'points_asc':
              return aPoints - bPoints;
            case 'points_desc':
              return bPoints - aPoints;
            case 'date_desc':
            default:
              return bDate - aDate;
          }
        });

        // 5) Hide all items first
        itemsInList.forEach(function (item) {
          item.style.display = 'none';
        });

        // 6) Append in new order & show
        visibleItems.forEach(function (item) {
          listElement.appendChild(item);
          item.style.display = '';
        });
      });
    }

    // Attach listeners
    if (searchInput) {
      searchInput.addEventListener('input', function () {
        currentSearch = searchInput.value.trim().toLowerCase();
        applyFiltersAndSort();
      });
    }

    if (statusSelect) {
      statusSelect.addEventListener('change', function () {
        currentStatus = statusSelect.value.trim().toLowerCase();
        applyFiltersAndSort();
      });
    }

    if (sortSelect) {
      sortSelect.addEventListener('change', function () {
        currentSort = sortSelect.value || 'date_desc';
        applyFiltersAndSort();
      });
    }

    // Initial run
    applyFiltersAndSort();

    // ========== DOWNLOAD HANDLERS ==========

    root.addEventListener('click', function (event) {
      var button = event.target.closest('[data-download-kind]');
      if (!button) {
        return;
      }

      event.preventDefault();

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

      // Determine endpoint and payload
      var endpoint = '';
      var payload = {};

      if (kind === 'stock' && historyId) {
        // Use dedicated redownload endpoint if provided by PHP
        if (settings.redownloadUrl) {
          endpoint = settings.redownloadUrl;
        } else if (restBase) {
          // Fallback: try to transform nehtw/v1 -> artly/v1 safely
          // e.g. "/wp-json/nehtw/v1/" => "/wp-json/artly/v1/"
          endpoint = restBase.replace(/nehtw\/v1\/?$/, 'artly/v1/') + 'download-redownload';
        } else {
          endpoint = '';
        }

        payload = { history_id: parseInt(historyId, 10) };
      } else {
        // Legacy single download endpoint (AI or stock without history id)
        endpoint = restBase ? restBase + 'download-link' : '';
        payload = { kind: kind, id: id };
      }

      // Defensive guard: don't call fetch with an empty endpoint
      if (!endpoint) {
        console.warn('Artly downloads: endpoint is not configured.');
        button.disabled = false;
        button.classList.remove('is-loading');
        button.textContent = originalText;
        showModal(
          'Download Error',
          'The download system is not properly configured. Please contact support.',
          'Close'
        );
        return;
      }

      var fetchOptions = {
        method: 'POST',
        headers: headers,
        body: JSON.stringify(payload),
        credentials: 'same-origin',
      };

      fetchWithRetry(
        endpoint,
        fetchOptions,
        3,
        function (attempt, maxAttempts) {
          // Update button label to show retry count
          if (attempt === 1) {
            button.textContent = 'Generating link…';
          } else {
            button.textContent = 'Generating link… (retry ' + attempt + '/' + maxAttempts + ')';
          }
        }
      )
        .then(function (result) {
          // Restore button base state
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

          var status = result.status;
          var data = result.data || {};

          // 409: Download not ready
          if (status === 409) {
            showModal(
              'Download not ready',
              data.message || 'This file is still processing. Please try again shortly.',
              'Close'
            );
            return;
          }

          // 404: Not found
          if (status === 404) {
            showModal(
              'Download not found',
              data.message || 'This download could not be found. It may have expired or been removed.',
              'Close'
            );
            return;
          }

          // 401/403: Auth error
          if (status === 401 || status === 403) {
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

          // Server errors (5xx) or network errors (status 0) after all retries
          if (!result.ok || status >= 500 || status === 0) {
            var errorMsg =
              (data && data.message) ||
              'We couldn\'t generate a download link right now. Please try again later.';
            showModal('Server Error', errorMsg, 'Close');
            return;
          }

          // Success: open download
          var downloadUrl = '';
          if (data && data.download_url) {
            downloadUrl = data.download_url;
          } else if (data && data.url) {
            downloadUrl = data.url;
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
        .catch(function (err) {
          // This catch should rarely fire because we handle most in fetchWithRetry
          console.error('Download error:', err);
          button.disabled = false;
          button.classList.remove('is-loading');
          button.textContent = originalText;

          showModal(
            'Connection Error',
            'We couldn\'t connect to the server. Please check your internet connection and try again.',
            'Close'
          );
        });
    });

    // ========== CSV EXPORT ==========

    var exportBtn = root.querySelector('[data-downloads-export]');
    var exportUrl = settings.exportUrl || '';

    if (exportBtn && exportUrl) {
      exportBtn.addEventListener('click', function () {
        // Preserve current query parameters (type, from, to, etc.)
        var params = new URLSearchParams(window.location.search);
        var url = new URL(exportUrl, window.location.origin);
        params.forEach(function (value, key) {
          url.searchParams.append(key, value);
        });

        // Trigger CSV download in a new tab
        window.open(url.toString(), '_blank');
      });
    }
  });
})();