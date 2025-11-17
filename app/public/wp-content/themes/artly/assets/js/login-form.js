/**
 * Artly Login Form Validation & AJAX Submission
 */

(function() {
  'use strict';

  document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.artly-login-form');
    if (!form) return;

    const usernameInput = document.getElementById('artly_username');
    const passwordInput = document.getElementById('artly_password');
    const passwordToggle = document.querySelector('.artly-password-toggle');
    const submitButton = form.querySelector('button[type="submit"]');
    const formErrorsContainer = form.querySelector('.artly-form-errors');

    // Password toggle functionality
    if (passwordToggle) {
      passwordToggle.addEventListener('click', function() {
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
    }

    function showFormErrors(errors) {
      // Remove existing error container
      if (formErrorsContainer) formErrorsContainer.remove();
      
      const errorDiv = document.createElement('div');
      errorDiv.className = 'artly-form-errors';
      errorDiv.setAttribute('role', 'alert');
      errorDiv.innerHTML = `
        <svg class="artly-icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2"/>
          <path d="M10 6v4M10 14h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <div class="artly-form-errors-list">
          <ul>
            ${errors.map(error => `<li>${escapeHtml(error)}</li>`).join('')}
          </ul>
        </div>
      `;
      
      form.insertBefore(errorDiv, form.firstChild);
      errorDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function setLoadingState(loading) {
      if (loading) {
        submitButton.disabled = true;
        submitButton.classList.add('artly-btn-loading');
        submitButton.setAttribute('aria-busy', 'true');
        form.querySelectorAll('input, button').forEach(input => {
          input.disabled = true;
        });
      } else {
        submitButton.disabled = false;
        submitButton.classList.remove('artly-btn-loading');
        submitButton.setAttribute('aria-busy', 'false');
        form.querySelectorAll('input').forEach(input => {
          input.disabled = false;
        });
      }
    }

    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    // Form submission with AJAX
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      
      // Clear previous errors
      if (formErrorsContainer) formErrorsContainer.remove();
      
      // Validate fields
      const errors = [];
      
      if (!usernameInput.value.trim()) {
        errors.push('Username or email is required');
      }
      
      if (!passwordInput.value) {
        errors.push('Password is required');
      }
      
      if (errors.length > 0) {
        showFormErrors(errors);
        return;
      }

      // Get redirect_to from URL parameter if present
      const urlParams = new URLSearchParams(window.location.search);
      const redirectTo = urlParams.get('redirect_to') || form.querySelector('input[name="redirect_to"]')?.value || '';

      // Prepare form data
      const formData = new FormData(form);
      formData.append('action', 'artly_login_ajax');
      if (redirectTo) {
        formData.append('redirect_to', redirectTo);
      }

      // Set loading state
      setLoadingState(true);

      // Submit via AJAX
      fetch(artlyLogin.ajaxurl || form.action, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      })
      .then(response => {
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        return response.json();
      })
      .then(data => {
        setLoadingState(false);
        
        if (data.success) {
          // Redirect on success
          if (data.redirect) {
            window.location.href = data.redirect;
          } else {
            window.location.href = '/my-downloads/';
          }
        } else {
          // Show errors from server
          const errorMessages = data.errors || [data.message] || ['Invalid username or password.'];
          showFormErrors(errorMessages);
          
          // Focus username field
          usernameInput.focus();
        }
      })
      .catch(error => {
        setLoadingState(false);
        console.error('Login error:', error);
        
        // Fallback to regular form submission
        showFormErrors(['Network error. Submitting form normally...']);
        setTimeout(() => {
          form.removeEventListener('submit', arguments.callee);
          form.submit();
        }, 2000);
      });
    });
  });
})();
