(function () {
  document.addEventListener("DOMContentLoaded", function () {
    var root = document.querySelector("[data-artly-onboarding]");
    if (!root) return;

    var cards = root.querySelectorAll(".artly-onboarding-card");
    if (!cards.length) return;

    var currentStep = 1;

    function showStep(step) {
      currentStep = step;
      cards.forEach(function (card) {
        var cardStep = parseInt(card.getAttribute("data-onboarding-step"), 10) || 0;
        if (cardStep === currentStep) {
          card.hidden = false;
        } else {
          card.hidden = true;
        }
      });
    }

    function completeOnboarding() {
      // Optimistically hide UI
      root.style.display = "none";

      if (!window.artlyOnboarding || !window.artlyOnboarding.ajaxUrl) {
        return;
      }

      var payload = new FormData();
      payload.append("action", "artly_onboarding_complete");
      payload.append("_ajax_nonce", window.artlyOnboarding.nonce);

      fetch(window.artlyOnboarding.ajaxUrl, {
        method: "POST",
        body: payload
      }).catch(function () {
        // If this fails, it's still safe: user won't see onboarding again this session.
      });
    }

    // Wire next buttons
    root.addEventListener("click", function (event) {
      var next = event.target.closest("[data-onboarding-next]");
      var skip = event.target.closest("[data-onboarding-skip]");
      var finish = event.target.closest("[data-onboarding-finish]");

      if (next) {
        event.preventDefault();
        showStep(currentStep + 1);
      } else if (skip) {
        event.preventDefault();
        completeOnboarding();
      } else if (finish) {
        event.preventDefault();
        completeOnboarding();
      }
    });

    // Start at step 1
    showStep(1);
  });
})();

