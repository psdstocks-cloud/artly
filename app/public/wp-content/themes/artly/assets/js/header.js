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
