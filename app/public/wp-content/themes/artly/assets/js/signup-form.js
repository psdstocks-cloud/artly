/**
 * Artly Signup Form Validation & AJAX Submission
 */

(function() {
  'use strict';

  document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.artly-signup-form');
    if (!form) return;

    const usernameInput = document.getElementById('artly_username');
    const emailInput = document.getElementById('artly_email');
    const passwordInput = document.getElementById('artly_password');
    const passwordConfirmInput = document.getElementById('artly_password_confirm');
    const passwordToggles = document.querySelectorAll('.artly-password-toggle');
    const submitButton = form.querySelector('button[type="submit"]');
    const formErrorsContainer = form.querySelector('.artly-form-errors');
    const formSuccessContainer = form.querySelector('.artly-form-success');

    // Password toggle functionality
    passwordToggles.forEach(toggle => {
      toggle.addEventListener('click', function() {
        const wrapper = this.closest('.artly-password-wrapper');
        const input = wrapper.querySelector('input');
        
        if (input.type === 'password') {
          input.type = 'text';
          this.setAttribute('aria-label', 'Hide password');
          this.innerHTML = `
            <svg class="artly-icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M2.5 2.5L17.5 17.5M10 4C6 4 2.73 6.11 1 9.5C2.73 12.89 6 15 10 15C14 15 17.27 12.89 19 9.5C17.27 6.11 14 4 10 4Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
              <path d="M10 12.5C11.38 12.5 12.5 11.38 12.5 10C12.5 8.62 11.38 7.5 10 7.5" stroke="currentColor" stroke-width="1.5"/>
            </svg>
          `;
        } else {
          input.type = 'password';
          this.setAttribute('aria-label', 'Show password');
          this.innerHTML = `
            <svg class="artly-icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M10 4C6 4 2.73 6.11 1 9.5C2.73 12.89 6 15 10 15C14 15 17.27 12.89 19 9.5C17.27 6.11 14 4 10 4Z" stroke="currentColor" stroke-width="1.5"/>
              <circle cx="10" cy="9.5" r="2.5" stroke="currentColor" stroke-width="1.5"/>
            </svg>
          `;
        }
      });
    });

    // Real-time validation
    if (usernameInput) {
      usernameInput.addEventListener('blur', validateUsername);
      usernameInput.addEventListener('input', debounce(function() {
        if (this.value.length > 0) {
          validateUsername();
        }
      }, 300));
    }

    if (emailInput) {
      emailInput.addEventListener('blur', validateEmail);
      emailInput.addEventListener('input', debounce(function() {
        if (this.value.length > 0) {
          validateEmail();
        }
      }, 300));
    }

    if (passwordInput) {
      passwordInput.addEventListener('blur', validatePassword);
      passwordInput.addEventListener('input', debounce(function() {
        if (this.value.length > 0) {
          validatePassword();
        }
      }, 300));
    }

    if (passwordConfirmInput) {
      passwordConfirmInput.addEventListener('blur', validatePasswordMatch);
      passwordConfirmInput.addEventListener('input', debounce(function() {
        if (this.value.length > 0) {
          validatePasswordMatch();
        }
      }, 300));
    }

    // Validation functions
    function validateUsername() {
      const value = usernameInput.value.trim();
      const usernamePattern = /^[a-zA-Z0-9_]{4,}$/;
      
      if (value.length === 0) {
        clearFieldError(usernameInput);
        return false;
      }
      
      if (value.length < 4) {
        showFieldError(usernameInput, 'Username must be at least 4 characters');
        return false;
      }
      
      if (!usernamePattern.test(value)) {
        showFieldError(usernameInput, 'Username can only contain letters, numbers, and underscores');
        return false;
      }
      
      clearFieldError(usernameInput);
      showFieldSuccess(usernameInput);
      return true;
    }

    function validateEmail() {
      const value = emailInput.value.trim();
      const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      
      if (value.length === 0) {
        clearFieldError(emailInput);
        return false;
      }
      
      if (!emailPattern.test(value)) {
        showFieldError(emailInput, 'Please enter a valid email address');
        return false;
      }
      
      clearFieldError(emailInput);
      showFieldSuccess(emailInput);
      return true;
    }

    function validatePassword() {
      const value = passwordInput.value;
      
      if (value.length === 0) {
        clearFieldError(passwordInput);
        return false;
      }
      
      if (value.length < 8) {
        showFieldError(passwordInput, 'Password must be at least 8 characters');
        return false;
      }
      
      clearFieldError(passwordInput);
      showFieldSuccess(passwordInput);
      
      // Re-validate password match if confirm field has value
      if (passwordConfirmInput && passwordConfirmInput.value.length > 0) {
        validatePasswordMatch();
      }
      
      return true;
    }

    function validatePasswordMatch() {
      const password = passwordInput.value;
      const confirm = passwordConfirmInput.value;
      
      if (confirm.length === 0) {
        clearFieldError(passwordConfirmInput);
        return false;
      }
      
      if (password !== confirm) {
        showFieldError(passwordConfirmInput, 'Passwords do not match');
        return false;
      }
      
      clearFieldError(passwordConfirmInput);
      showFieldSuccess(passwordConfirmInput);
      return true;
    }

    function showFieldError(input, message) {
      input.classList.add('artly-input-error');
      input.setAttribute('aria-invalid', 'true');
      
      // Remove existing error message
      const existingError = input.parentElement.querySelector('.artly-field-error');
      if (existingError) {
        existingError.remove();
      }
      
      // Add error message
      const errorDiv = document.createElement('div');
      errorDiv.className = 'artly-field-error';
      errorDiv.textContent = message;
      errorDiv.setAttribute('role', 'alert');
      input.parentElement.appendChild(errorDiv);
    }

    function clearFieldError(input) {
      input.classList.remove('artly-input-error');
      input.setAttribute('aria-invalid', 'false');
      
      const errorDiv = input.parentElement.querySelector('.artly-field-error');
      if (errorDiv) {
        errorDiv.remove();
      }
    }

    function showFieldSuccess(input) {
      const existingSuccess = input.parentElement.querySelector('.artly-field-success');
      if (existingSuccess) {
        existingSuccess.remove();
      }
      const successDiv = document.createElement('div');
      successDiv.className = 'artly-field-success';
      successDiv.textContent = 'Looks good!';
      successDiv.setAttribute('role', 'alert');
      input.parentElement.appendChild(successDiv);
    }

    function clearFieldSuccess(input) {
      const successDiv = input.parentElement.querySelector('.artly-field-success');
      if (successDiv) {
        successDiv.remove();
      }
    }

    // Form submission validation
    form.addEventListener('submit', function(e) {
      let isValid = true;

      // Validate all fields
      if (!validateUsername()) isValid = false;
      if (!validateEmail()) isValid = false;
      if (!validatePassword()) isValid = false;
      if (!validatePasswordMatch()) isValid = false;

      // Check terms checkbox
      const termsCheckbox = document.getElementById('artly_terms');
      if (!termsCheckbox.checked) {
        isValid = false;
        showFieldError(termsCheckbox, 'You must agree to the Terms & Conditions');
      }

      if (!isValid) {
        e.preventDefault();
        // Scroll to first error
        const firstError = form.querySelector('.artly-input-error');
        if (firstError) {
          firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
          firstError.focus();
        }
      }
    });
  });
})();
