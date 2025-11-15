(function () {
  document.addEventListener("DOMContentLoaded", function () {
    var header = document.querySelector(".artly-site-header");
    var toggle = document.querySelector(".artly-header-toggle");
    var backdrop = document.querySelector(".artly-menu-backdrop");
    var nav = document.querySelector(".artly-header-nav");
    if (!header || !toggle) return;

    function closeMenu() {
      header.classList.remove("is-open");
      if (backdrop) {
        backdrop.style.display = "none";
      }
    }

    function openMenu() {
      header.classList.add("is-open");
      if (backdrop) {
        backdrop.style.display = "block";
        // Trigger reflow for animation
        void backdrop.offsetWidth;
        backdrop.style.opacity = "1";
      }
    }

    toggle.addEventListener("click", function (e) {
      e.stopPropagation();
      if (header.classList.contains("is-open")) {
        closeMenu();
      } else {
        openMenu();
      }
    });

    // Close menu when clicking backdrop
    if (backdrop) {
      backdrop.addEventListener("click", function () {
        closeMenu();
      });
    }

    // Close menu when clicking on a menu link
    if (nav) {
      var menuLinks = nav.querySelectorAll("a");
      menuLinks.forEach(function (link) {
        link.addEventListener("click", function () {
          // Small delay to allow navigation
          setTimeout(function () {
            closeMenu();
          }, 100);
        });
      });
    }

    // Close menu on ESC key
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && header.classList.contains("is-open")) {
        closeMenu();
      }
    });

    // Dropdown menu functionality for "Supported Websites"
    var dropdownTrigger = document.getElementById("websites-menu-trigger");
    var dropdownMenu = document.getElementById("websites-menu-dropdown");
    var dropdownItem = dropdownTrigger ? dropdownTrigger.closest(".artly-header-menu-item--has-dropdown") : null;

    if (dropdownTrigger && dropdownMenu && dropdownItem) {
      // Toggle dropdown on click
      dropdownTrigger.addEventListener("click", function (e) {
        e.stopPropagation();
        var isOpen = dropdownItem.classList.contains("is-open");
        
        // Close all other dropdowns
        document.querySelectorAll(".artly-header-menu-item--has-dropdown.is-open").forEach(function (item) {
          if (item !== dropdownItem) {
            item.classList.remove("is-open");
            var trigger = item.querySelector(".artly-header-menu-dropdown-trigger");
            if (trigger) {
              trigger.setAttribute("aria-expanded", "false");
            }
          }
        });

        // Toggle current dropdown
        if (isOpen) {
          dropdownItem.classList.remove("is-open");
          dropdownTrigger.setAttribute("aria-expanded", "false");
        } else {
          dropdownItem.classList.add("is-open");
          dropdownTrigger.setAttribute("aria-expanded", "true");
        }
      });

      // Close dropdown when clicking outside
      document.addEventListener("click", function (e) {
        if (!dropdownItem.contains(e.target)) {
          dropdownItem.classList.remove("is-open");
          dropdownTrigger.setAttribute("aria-expanded", "false");
        }
      });

      // Close dropdown on ESC key
      document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && dropdownItem.classList.contains("is-open")) {
          dropdownItem.classList.remove("is-open");
          dropdownTrigger.setAttribute("aria-expanded", "false");
          dropdownTrigger.focus();
        }
      });

      // Keyboard navigation within dropdown
      var dropdownLinks = dropdownMenu.querySelectorAll(".artly-header-menu-dropdown-link");
      if (dropdownLinks.length > 0) {
        dropdownLinks.forEach(function (link, index) {
          link.addEventListener("keydown", function (e) {
            var nextIndex;
            if (e.key === "ArrowDown") {
              e.preventDefault();
              nextIndex = index < dropdownLinks.length - 1 ? index + 1 : 0;
              dropdownLinks[nextIndex].focus();
            } else if (e.key === "ArrowUp") {
              e.preventDefault();
              nextIndex = index > 0 ? index - 1 : dropdownLinks.length - 1;
              dropdownLinks[nextIndex].focus();
            } else if (e.key === "Home") {
              e.preventDefault();
              dropdownLinks[0].focus();
            } else if (e.key === "End") {
              e.preventDefault();
              dropdownLinks[dropdownLinks.length - 1].focus();
            }
          });
        });
      }
    }

    // Currency toggle handler
    var currencyToggle = document.getElementById("currencyToggle");
    var currencyDisplay = document.getElementById("currencyDisplay");
    var footerCurrencyDisplay = document.getElementById("footerCurrencyDisplay");
    
    if (currencyToggle && currencyDisplay) {
      // Get initial currency from localStorage or default to EGP
      var currency = localStorage.getItem("artly_currency") || "EGP";
      updateCurrencyDisplay();

      currencyToggle.addEventListener("click", function () {
        currency = currency === "EGP" ? "USD" : "EGP";
        localStorage.setItem("artly_currency", currency);
        
        // Set cookie for server-side preference (1 year expiry)
        var expiryDate = new Date();
        expiryDate.setTime(expiryDate.getTime() + 365 * 24 * 60 * 60 * 1000);
        document.cookie = "artly_currency=" + currency + ";path=/;expires=" + expiryDate.toUTCString() + ";SameSite=Lax";
        
        updateCurrencyDisplay();
        
        // Dispatch custom event for other scripts to listen
        window.dispatchEvent(new CustomEvent("artly:currencyChanged", { detail: { currency: currency } }));
      });

      function updateCurrencyDisplay() {
        currency = localStorage.getItem("artly_currency") || "EGP";
        if (currencyDisplay) {
          currencyDisplay.textContent = currency;
        }
        if (footerCurrencyDisplay) {
          footerCurrencyDisplay.textContent = currency;
        }
      }

      // Listen for currency changes from other pages
      window.addEventListener("storage", function (e) {
        if (e.key === "artly_currency") {
          updateCurrencyDisplay();
        }
      });
    }
  });
})();
