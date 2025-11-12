(function () {
  var button = document.querySelector('[data-buy-points]');
  if (!button) {
    return;
  }

  var settings = window.artlyPricingSettings || {};
  if (!settings.woocommerceActive) {
    return;
  }

  var slider = document.querySelector('[data-points-slider]');
  var manualInput = document.querySelector('#pointsInput');

  function getCurrency() {
    if (window.artlyCurrency && window.artlyCurrency.current) {
      return window.artlyCurrency.current;
    }
    if (settings.userCurrency) {
      return settings.userCurrency;
    }
    return 'EGP';
  }

  function getPoints() {
    var value = 0;
    if (manualInput) {
      value = parseInt(manualInput.value, 10);
      if (!isNaN(value) && value > 0) {
        return value;
      }
    }
    if (slider) {
      value = parseInt(slider.value, 10);
      if (!isNaN(value) && value > 0) {
        return value;
      }
    }
    return 0;
  }

  function getLoginRedirect() {
    var homeUrl = settings.homeUrl || '';
    if (!homeUrl) {
      return '/login/';
    }
    return homeUrl.replace(/\/$/, '') + '/login/?redirect=' + encodeURIComponent(window.location.href);
  }

  function getCartRedirect() {
    if (settings.homeUrl) {
      return settings.homeUrl.replace(/\/$/, '') + '/cart/';
    }
    return '/cart/';
  }

  function showError(message) {
    if (typeof window.showToast === 'function') {
      window.showToast(message, 'error');
      return;
    }
    window.alert(message);
  }

  button.addEventListener('click', function (event) {
    var isLoggedIn = !!settings.isLoggedIn;

    if (!isLoggedIn) {
      event.preventDefault();
      window.location.href = getLoginRedirect();
      return;
    }

    event.preventDefault();

    var points = getPoints();
    if (!points || points <= 0) {
      showError('Please choose how many points you want first.');
      return;
    }

    var currency = (getCurrency() || 'EGP').toUpperCase();

    var nonce = (window.wpApiSettings && window.wpApiSettings.nonce) || '';

    button.setAttribute('data-loading', 'true');
    button.classList.add('is-loading');

    fetch('/wp-json/artly/v1/points/add-to-cart', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': nonce
      },
      body: JSON.stringify({
        points: points,
        currency: currency
      })
    })
      .then(function (response) {
        return response.json().then(function (data) {
          return { ok: response.ok, status: response.status, data: data };
        });
      })
      .then(function (result) {
        button.removeAttribute('data-loading');
        button.classList.remove('is-loading');

        var data = result && result.data ? result.data : {};

        if (!result || !result.ok || !data.success) {
          var message = (data && data.message) ? data.message : 'Could not add to cart';
          showError(message);
          return;
        }

        window.location.href = data.redirect || getCartRedirect();
      })
      .catch(function () {
        button.removeAttribute('data-loading');
        button.classList.remove('is-loading');
        showError('Network error. Please try again.');
      });
  });
})();
