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

    root.addEventListener('click', function (event) {
      var button = event.target.closest('[data-download-kind]');
      if (!button) {
        return;
      }

      event.preventDefault();

      if (!restBase) {
        console.warn('Download endpoint not configured.');
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
        'Content-Type': 'application/json'
      };

      if (nonce) {
        headers['X-WP-Nonce'] = nonce;
      }

      // Use new re-download endpoint for stock files with history_id
      var endpoint = '';
      var payload = {};
      
      if (kind === 'stock' && historyId) {
        endpoint = restBase.replace('/nehtw/v1/', '/artly/v1/') + 'download-redownload';
        payload = { history_id: parseInt(historyId, 10) };
      } else {
        // Fallback to old endpoint for AI or stock without history_id
        endpoint = restBase + 'download-link';
        payload = { kind: kind, id: id };
      }

      fetch(endpoint, {
        method: 'POST',
        headers: headers,
        body: JSON.stringify(payload),
        credentials: 'same-origin'
      })
        .then(function (response) {
          return response
            .json()
            .then(function (data) {
              return { ok: response.ok, data: data };
            })
            .catch(function () {
              return { ok: response.ok, data: {} };
            });
        })
        .then(function (result) {
          if (!result || !result.ok) {
            var errorMsg = result && result.data && result.data.message 
              ? result.data.message 
              : 'We couldn\'t generate a download link right now. Please try again later.';
            alert(errorMsg);
            button.disabled = false;
            button.classList.remove('is-loading');
            button.textContent = originalText;
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
            alert('We couldn\'t generate a download link right now. Please try again later.');
          }

          button.disabled = false;
          button.classList.remove('is-loading');
          button.textContent = originalText;
        })
        .catch(function (err) {
          console.error('Download error:', err);
          alert('We couldn\'t generate a download link right now. Please try again later.');
          button.disabled = false;
          button.classList.remove('is-loading');
          button.textContent = originalText;
        });
    });
  });
})();
