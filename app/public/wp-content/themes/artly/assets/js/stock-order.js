(function () {
  document.addEventListener("DOMContentLoaded", function () {
    var root = document.querySelector(".artly-stock-order-page");
    if (!root) return;

    var config = window.artlyStockOrder || {};
    var sitesConfig = config.sites || {};
    var endpoint = config.endpoint || "";
    var restNonce = config.restNonce || "";

    // Tab switching
    var tabs = root.querySelectorAll(".stock-order-tab");
    var panels = root.querySelectorAll("[data-stock-order-panel]");

    tabs.forEach(function (tab) {
      tab.addEventListener("click", function () {
        var mode = tab.getAttribute("data-mode");
        tabs.forEach(function (t) {
          t.classList.toggle("is-active", t === tab);
        });
        panels.forEach(function (panel) {
          var pMode = panel.getAttribute("data-stock-order-panel");
          panel.hidden = pMode !== mode;
        });
      });
    });

    // Helper: Detect site and cost from URL using full config
    function detectSiteFromUrl(url) {
      if (!url || typeof url !== "string") {
        return null;
      }

      if (!sitesConfig || typeof sitesConfig !== "object") {
        return null;
      }

      try {
        var u = new URL(url);
        var host = u.hostname.toLowerCase();
      } catch (e) {
        return null;
      }

      var match = null;

      Object.keys(sitesConfig).forEach(function (key) {
        var site = sitesConfig[key];
        if (!site || site.enabled === false || !Array.isArray(site.domains)) {
          return;
        }

        site.domains.forEach(function (pattern) {
          if (match) return;
          if (host.indexOf(pattern.toLowerCase()) !== -1) {
            match = {
              key: key,
              label: site.label || key,
              points: parseFloat(site.points || 0)
            };
          }
        });
      });

      return match;
    }

    // Single mode: detect on input
    var singleInput = root.querySelector("#stock-order-single-input");
    var singleMeta = root.querySelector("[data-stock-order-single-meta]");
    var singleSubmit = root.querySelector('[data-stock-order-submit="single"]');

    function updateSingleMeta() {
      var url = (singleInput.value || "").trim();
      if (!url) {
        singleMeta.textContent = "";
        singleMeta.classList.remove("is-visible");
        return;
      }

      var site = detectSiteFromUrl(url);
      if (!site) {
        singleMeta.textContent = "Unsupported or unknown website.";
        singleMeta.classList.add("is-visible");
        return;
      }

      singleMeta.textContent = "Detected: " + site.label + " – " + site.points + " point(s) per link";
      singleMeta.classList.add("is-visible");
    }

    if (singleInput && singleMeta) {
      ["change", "keyup", "blur", "input"].forEach(function (evt) {
        singleInput.addEventListener(evt, updateSingleMeta);
      });
    }

    // Batch mode: scan links
    var batchInput = root.querySelector("#stock-order-batch-input");
    var batchScan = root.querySelector("[data-stock-order-scan]");
    var batchList = root.querySelector("[data-stock-order-batch-list]");
    var batchSubmit = root.querySelector('[data-stock-order-submit="batch"]');

    var batchItems = [];

    if (batchScan && batchInput && batchList) {
      batchScan.addEventListener("click", function () {
        var text = batchInput.value.trim();
        if (!text) return;

        var lines = text.split("\n").filter(function (line) {
          return line.trim().length > 0;
        }).slice(0, 5); // Max 5 links

        batchItems = [];
        batchList.innerHTML = "";

        lines.forEach(function (line) {
          var url = line.trim();
          var detected = detectSiteFromUrl(url);

          if (detected) {
            var item = {
              url: url,
              site: detected.key,
              label: detected.label,
              points: detected.points
            };
            batchItems.push(item);

            var itemEl = document.createElement("div");
            itemEl.className = "stock-order-batch-item";
            itemEl.setAttribute("data-stock-order-batch-item", "");
            itemEl.setAttribute("data-url", url);

            itemEl.innerHTML = [
              '<input type="checkbox" checked />',
              '<div class="stock-order-batch-item-content">',
              '  <div class="stock-order-batch-item-url">' + escapeHtml(url) + "</div>",
              '  <div class="stock-order-batch-item-meta">' + escapeHtml(detected.label) + " – " + detected.points + " point(s)</div>",
              "</div>"
            ].join("");

            batchList.appendChild(itemEl);
          } else {
            // Show unsupported link
            var itemEl = document.createElement("div");
            itemEl.className = "stock-order-batch-item";
            itemEl.setAttribute("data-stock-order-batch-item", "");
            itemEl.setAttribute("data-url", url);

            itemEl.innerHTML = [
              '<input type="checkbox" disabled />',
              '<div class="stock-order-batch-item-content">',
              '  <div class="stock-order-batch-item-url">' + escapeHtml(url) + "</div>",
              '  <div class="stock-order-batch-item-meta" style="color: rgba(248, 113, 113, 0.9);">Unsupported or unknown website</div>',
              "</div>"
            ].join("");

            batchList.appendChild(itemEl);
          }
        });
      });
    }

    // Submit function
    function submitStockOrder(payload) {
      if (!endpoint) {
        console.warn("Stock order endpoint not configured.");
        return;
      }

      var resultsEl = root.querySelector("[data-stock-order-results]");
      if (resultsEl) {
        resultsEl.innerHTML = "";
      }

      // Disable submit buttons
      var submitButtons = root.querySelectorAll(".stock-order-submit");
      submitButtons.forEach(function (btn) {
        btn.disabled = true;
      });

      fetch(endpoint, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": restNonce
        },
        credentials: "same-origin",
        body: JSON.stringify(payload)
      })
        .then(function (res) {
          if (!res.ok) {
            if (res.status === 401) {
              throw new Error("unauthorized");
            }
            throw new Error("http_error");
          }
          return res.json();
        })
        .then(function (data) {
          if (!data || !Array.isArray(data.links)) {
            showError("Unexpected response.");
            return;
          }

          var links = data.links || [];
          var balance = data.balance || 0;

          links.forEach(function (link) {
            showResult(link);
          });

          // Update balance if needed (you could show this somewhere)
          if (balance !== undefined) {
            // Balance updated message could be shown
          }
        })
        .catch(function (err) {
          console.error("Stock order error:", err);
          if (err && err.message === "unauthorized") {
            showError("Your session expired. Please refresh the page and log in again.");
          } else {
            showError("Failed to process orders. Please try again.");
          }
        })
        .finally(function () {
          // Re-enable submit buttons
          submitButtons.forEach(function (btn) {
            btn.disabled = false;
          });
        });
    }

    function showResult(link) {
      var resultsEl = root.querySelector("[data-stock-order-results]");
      if (!resultsEl) return;

      var itemEl = document.createElement("div");
      itemEl.className = "stock-order-result-item";

      var status = link.status || "";
      var message = link.message || "";

      if (status === "queued" || status === "already_downloaded") {
        itemEl.classList.add("stock-order-result-item--success");
      } else if (status === "error" || status === "insufficient_points") {
        itemEl.classList.add("stock-order-result-item--error");
      } else if (status === "skipped") {
        itemEl.classList.add("stock-order-result-item--warning");
      }

      var html = [
        '<div class="stock-order-result-url">' + escapeHtml(link.url || "") + "</div>",
        '<div class="stock-order-result-message">' + escapeHtml(message) + "</div>"
      ];

      if (link.order_id && link.status === "already_downloaded") {
        html.push(
          '<a href="' +
            escapeHtml(window.artlyStockOrder ? window.artlyStockOrder.historyUrl : "/my-downloads/") +
            '" class="stock-order-history-link" style="margin-top: 0.5rem; display: inline-block;">View in history</a>'
        );
      }

      itemEl.innerHTML = html.join("");
      resultsEl.appendChild(itemEl);
    }

    function showError(message) {
      var resultsEl = root.querySelector("[data-stock-order-results]");
      if (!resultsEl) return;

      var itemEl = document.createElement("div");
      itemEl.className = "stock-order-result-item stock-order-result-item--error";
      itemEl.innerHTML =
        '<div class="stock-order-result-message">' + escapeHtml(message) + "</div>";
      resultsEl.appendChild(itemEl);
    }

    function escapeHtml(text) {
      var div = document.createElement("div");
      div.textContent = text;
      return div.innerHTML;
    }

    // Single mode submit
    if (singleSubmit && singleInput) {
      singleSubmit.addEventListener("click", function () {
        var url = singleInput.value.trim();
        if (!url) return;

        var payload = {
          links: [
            {
              url: url,
              selected: true
            }
          ]
        };

        submitStockOrder(payload);
      });
    }

    // Batch mode submit
    if (batchSubmit && batchList) {
      batchSubmit.addEventListener("click", function () {
        var items = batchList.querySelectorAll("[data-stock-order-batch-item]");
        var links = [];

        items.forEach(function (item) {
          var checkbox = item.querySelector('input[type="checkbox"]');
          var url = item.getAttribute("data-url") || "";

          if (url) {
            links.push({
              url: url,
              selected: checkbox ? checkbox.checked : true
            });
          }
        });

        if (!links.length) {
          showError("Please select at least one link to order.");
          return;
        }

        var payload = { links: links };
        submitStockOrder(payload);
      });
    }
  });
})();

