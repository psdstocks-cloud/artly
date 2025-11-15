(function () {
  'use strict';

  var $ = function (selector, context) {
    return (context || document).querySelector(selector);
  };

  var form = $('.artly-form');
  if (!form) {
    return;
  }

  var btn = $('#artlyLoginBtn');
  var username = $('#username');
  var password = $('#password');
  var remember = form.querySelector('input[name="rememberme"]');
  var toggle = form.querySelector('.toggle-pass');
  var honeypot = form.querySelector('input[name="artly_login_hp"]');
  var errors = $('#artlyLoginErrors');
  var spinner = btn ? btn.querySelector('.btn__spinner') : null;
  var btnLabel = btn ? btn.querySelector('.btn__label') : null;
  var i18n = (window.ARTLY_LOGIN && window.ARTLY_LOGIN.i18n) || {};
  var serverError = (window.ARTLY_LOGIN && window.ARTLY_LOGIN.errors) || '';

  if (errors) {
    if (!errors.textContent.trim().length && serverError) {
      errors.textContent = serverError;
    }
    if (errors.textContent.trim().length) {
      errors.classList.add('is-visible');
      errors.style.display = 'block';
    }
  }

  var setError = function (message) {
    if (!errors || !message) {
      return;
    }
    errors.textContent = message;
    errors.classList.add('is-visible');
    errors.style.display = 'block';
    errors.focus && errors.focus();
  };

  var validate = function () {
    var ok = Boolean(username && username.value.trim() && password && password.value.trim());
    if (btn) {
      btn.disabled = !ok;
      btn.setAttribute('aria-disabled', ok ? 'false' : 'true');
    }
    return ok;
  };

  ['input', 'change'].forEach(function (eventName) {
    if (username) {
      username.addEventListener(eventName, validate);
    }
    if (password) {
      password.addEventListener(eventName, validate);
    }
  });
  validate();

  if (toggle && password) {
    toggle.addEventListener('click', function () {
      var reveal = password.type === 'password';
      password.type = reveal ? 'text' : 'password';
      toggle.setAttribute('aria-pressed', reveal ? 'true' : 'false');
      var label = toggle.querySelector('.toggle-pass__label');
      if (label) {
        label.textContent = reveal ? (i18n.hide || 'Hide') : (i18n.show || 'Show');
      }
      var hideLabel = i18n.hidePassword || (i18n.hide ? i18n.hide + ' password' : 'Hide password');
      var showLabel = i18n.showPassword || (i18n.show ? i18n.show + ' password' : 'Show password');
      toggle.setAttribute('aria-label', reveal ? hideLabel : showLabel);
    });
  }

  if (remember) {
    try {
      var LS_KEY = 'artly-login-remember';
      var stored = localStorage.getItem(LS_KEY);
      if (stored === 'true') {
        remember.checked = true;
      }
      remember.addEventListener('change', function () {
        localStorage.setItem(LS_KEY, remember.checked ? 'true' : 'false');
      });
    } catch (err) {
      // LocalStorage unavailable; safely ignore.
    }
  }

  form.addEventListener('submit', function (event) {
    if (!validate()) {
      event.preventDefault();
      setError(i18n.required || 'Please fill all required fields.');
      return;
    }

    if (honeypot && honeypot.value.trim().length) {
      event.preventDefault();
      setError(i18n.invalid || 'Invalid email or password.');
      return;
    }

    if (btn) {
      btn.setAttribute('aria-busy', 'true');
      btn.disabled = true;
      btn.setAttribute('aria-disabled', 'true');
    }
    if (spinner) {
      spinner.style.display = 'inline-block';
    }
    if (btnLabel) {
      btnLabel.style.opacity = '0.35';
    }
  });

  var prefersReducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (window.gsap && !prefersReducedMotion) {
    window.gsap.from('.artly-login__card', { y: 28, opacity: 0, duration: 0.6, ease: 'power2.out' });
    window.gsap.from('.artly-login__intro > *', {
      y: 18,
      opacity: 0,
      duration: 0.6,
      ease: 'power2.out',
      stagger: 0.08,
      delay: 0.1,
    });
  }
})();