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

      if (!kind || !id) {
        return;
      }

      if (button.disabled) {
        return;
      }

      button.disabled = true;
      button.classList.add('is-loading');

      var payload = {
        kind: kind,
        id: id
      };

      var headers = {
        'Content-Type': 'application/json'
      };

      if (nonce) {
        headers['X-WP-Nonce'] = nonce;
      }

      fetch(restBase + 'download-link', {
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
          if (!result || !result.ok || !result.data || !result.data.url) {
            button.disabled = false;
            button.classList.remove('is-loading');
            return;
          }

          window.location.href = result.data.url;
        })
        .catch(function () {
          button.disabled = false;
          button.classList.remove('is-loading');
        })
        .finally(function () {
          setTimeout(function () {
            button.disabled = false;
            button.classList.remove('is-loading');
          }, 1500);
        });
    });
  });
})();
