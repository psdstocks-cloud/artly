(function () {
  document.addEventListener("DOMContentLoaded", function () {
    var root = document.querySelector(".artly-transactions-page");
    if (!root) return;

    var filterGroup = root.querySelector(".transactions-filter-group");
    if (!filterGroup) return;

    filterGroup.addEventListener("click", function (event) {
      var pill = event.target.closest(".transactions-filter-pill");
      if (!pill) return;

      var type = pill.getAttribute("data-type") || "all";

      var url = new URL(window.location.href);
      if (type === "all") {
        url.searchParams.delete("type");
      } else {
        url.searchParams.set("type", type);
      }
      url.searchParams.delete("p");

      window.location.href = url.toString();
    });
  });
})();

