(function () {
  document.addEventListener("DOMContentLoaded", function () {
    var header = document.querySelector(".artly-site-header");
    var toggle = document.querySelector(".artly-header-toggle");
    if (!header || !toggle) return;

    toggle.addEventListener("click", function () {
      header.classList.toggle("is-open");
    });
  });
})();
