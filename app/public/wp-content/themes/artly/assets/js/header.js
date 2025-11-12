(function () {
  document.addEventListener("DOMContentLoaded", function () {
    var header = document.querySelector(".artly-site-header");
    var toggle = document.querySelector(".artly-header-toggle");
    if (!header || !toggle) return;

    toggle.addEventListener("click", function () {
      header.classList.toggle("is-open");
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
