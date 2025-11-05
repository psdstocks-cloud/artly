(function () {
  document.addEventListener("DOMContentLoaded", function () {
    var modal = document.querySelector('.artly-modal[data-artly-modal="wallet-topup"]');

    if (!modal) return;

    var dialog = modal.querySelector(".artly-modal-dialog");

    var closers = modal.querySelectorAll("[data-artly-modal-close]");

    var openers = document.querySelectorAll("[data-artly-open-modal='wallet-topup']");

    function openModal() {
      modal.classList.add("is-open");
      document.body.classList.add("artly-modal-open");
      modal.setAttribute("aria-hidden", "false");
    }

    function closeModal() {
      modal.classList.remove("is-open");
      document.body.classList.remove("artly-modal-open");
      modal.setAttribute("aria-hidden", "true");
    }

    openers.forEach(function (btn) {
      btn.addEventListener("click", function (e) {
        e.preventDefault();
        openModal();
      });
    });

    closers.forEach(function (btn) {
      btn.addEventListener("click", function (e) {
        e.preventDefault();
        closeModal();
      });
    });

    modal.addEventListener("click", function (e) {
      if (e.target === modal || e.target.classList.contains("artly-modal-backdrop")) {
        closeModal();
      }
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && modal.classList.contains("is-open")) {
        closeModal();
      }
    });
  });
})();

