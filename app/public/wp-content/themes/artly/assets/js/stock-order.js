(function () {
  document.addEventListener("DOMContentLoaded", function () {
    var root = document.querySelector(".artly-stock-order-page");
    if (!root) return;

    var config = window.artlyStockOrder || {};
    var sitesConfig = config.sites || {};
    var endpoint = config.endpoint || "";
    var previewEndpoint = config.previewEndpoint || "";
    var restNonce = config.restNonce || "";

    // Simple helper used by some rules in the extractor.
    function idMapping(source, arr) {
      // For our purposes we just join the parts with a dash.
      // This matches what the backend expects.
      return arr.join("-");
    }

    /**
     * ====== URL → { source, id, url } EXTRACTOR ======
     * This is the complete pattern list from the original implementation.
     */
    var idExtractor = function (str) {
      var sourceMatch = [
        {
          match: /shutterstock.com(|\/[a-z]*)\/video\/clip-([0-9]*)/,
          result: function (string) {
            var stockSource = "vshutter";
            var stockId = string[2];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /shutterstock.com(.*)music\/(.*)track-([0-9]*)-/,
          result: function (string) {
            var stockSource = "mshutter";
            var stockId = string[3];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /shutterstock\.com\/(.*)(image-vector|image-photo|image-illustration|image|image-generated|editorial)\/([0-9a-zA-Z-_]*)-([0-9a-z]*)/,
          result: function (string) {
            var stockSource = "shutterstock";
            var stockId = string[4];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /shutterstock\.com\/(.*)(image-vector|image-photo|image-illustration|image-generated|editorial)\/([0-9a-z]*)/,
          result: function (string) {
            var stockSource = "shutterstock";
            var stockId = string[3];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /stock\.adobe.com\/(..\/||.....\/)(images|templates|3d-assets|stock-photo|video)\/([a-zA-Z0-9-%.,]*)\/([0-9]*)/,
          result: function (string) {
            var stockSource = "adobestock";
            var stockId = string[4];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /stock\.adobe.com(.*)asset_id=([0-9]*)/,
          result: function (string) {
            var stockSource = "adobestock";
            var stockId = string[2];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /stock\.adobe.com\/(.*)search\/audio\?(k|keywords)=([0-9]*)/,
          result: function (string) {
            var stockSource = "adobestock";
            var stockId = string[3];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /stock\.adobe\.com\/(..\/||.....\/)([0-9]*)/,
          result: function (string) {
            var stockSource = "adobestock";
            var stockId = string[2];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /depositphotos\.com(.*)depositphotos_([0-9]*)(.*)\.jpg/,
          result: function (string) {
            var stockSource = "depositphotos";
            var stockId = string[2];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /depositphotos\.com\/([0-9]*)\/stock-video(.*)/,
          result: function (string) {
            var stockSource = "depositphotos_video";
            var stockId = string[1];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /depositphotos\.com\/([0-9]*)\/(stock-photo|stock-illustration|free-stock)(.*)/,
          result: function (string) {
            var stockSource = "depositphotos";
            var stockId = string[1];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /depositphotos.com(.*)qview=([0-9]*)/,
          result: function (string) {
            var stockSource = "depositphotos";
            var stockId = string[2];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /depositphotos.com(.*)\/(photo|editorial|vector|illustration)\/([0-9a-z-]*)-([0-9]*)/,
          result: function (string) {
            var stockSource = "depositphotos";
            var stockId = string[4];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /123rf\.com\/(photo|free-photo)_([0-9]*)_/,
          result: function (string) {
            var stockSource = "123rf";
            var stockId = string[2];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /123rf\.com\/(.*)mediapopup=([0-9]*)/,
          result: function (string) {
            var stockSource = "123rf";
            var stockId = string[2];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /123rf\.com\/stock-photo\/([0-9]*).html/,
          result: function (string) {
            var stockSource = "123rf";
            var stockId = string[1];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /istockphoto\.com\/(.*)gm([0-9A-Z_]*)-/,
          result: function (string) {
            var stockSource = "istockphoto";
            var stockId = string[2];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /gettyimages\.com\/(.*)\/([0-9]*)/,
          result: function (string) {
            var stockSource = "istockphoto";
            var stockId = string[2];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /freepik.(.*)\/(.*)-?video-?(.*)\/([0-9a-z-]*)_([0-9]*)/,
          result: function (string) {
            var stockSource = "vfreepik";
            var stockId = string[5];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /freepik\.(.*)(.*)_([0-9]*).htm/,
          result: function (string) {
            var stockSource = "freepik";
            var stockId = string[3];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /freepik.com\/(icon|icone)\/(.*)_([0-9]*)/,
          result: function (string) {
            var stockSource = "flaticon";
            var stockId = string[3];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /flaticon.com\/(.*)\/([0-9a-z-]*)_([0-9]*)/,
          result: function (string) {
            var stockSource = "flaticon";
            var stockId = string[3];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /flaticon.com\/(.*)(packs|stickers-pack)\/([0-9a-z-]*)/,
          result: function (string) {
            var stockSource = "flaticonpack";
            var stockId = string[3];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /elements\.envato\.com(.*)\/([0-9a-zA-Z-]*)-([0-9A-Z]*)/,
          result: function (string) {
            var stockSource = "envato";
            var stockId = string[3];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /dreamstime(.*)-image([0-9]*)/,
          result: function (string) {
            var stockSource = "dreamstime";
            var stockId = string[2];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /pngtree\.com(.*)_([0-9]*).html/,
          result: function (string) {
            var stockSource = "pngtree";
            var stockId = string[2];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /vectorstock.com\/([0-9a-zA-Z-]*)\/([0-9a-zA-Z-]*)-([0-9]*)/,
          result: function (string) {
            var stockSource = "vectorstock";
            var stockId = string[3];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /motionarray.com\/([a-zA-Z0-9-]*)\/([a-zA-Z0-9-]*)-([0-9]*)/,
          result: function (string) {
            var stockSource = "motionarray";
            var stockId = string[3];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /(alamy|alamyimages)\.(com|es|de|it|fr)\/(.*)(-|image)([0-9]*).html/,
          result: function (string) {
            var stockSource = "alamy";
            var stockId = string[5];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /motionelements\.com\/(([a-z-]*\/)|)(([a-z-3]*)|(product|davinci-resolve-template))(\/|-)([0-9]*)-/,
          result: function (string) {
            var stockSource = "motionelements";
            var getVar = [3, 7];
            var arr = [];
            for (var i = 0; i < string.length; i++) {
              if (getVar.includes(i)) {
                arr.push(string[i]);
              }
            }
            var stockId = idMapping(stockSource, arr);
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /storyblocks\.com\/(video|images|audio)\/stock\/([0-9a-z-]*)-([0-9a-z_]*)/,
          result: function (string) {
            var stockSource = "storyblocks";
            var stockId = string[3];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /epidemicsound.com\/(.*)tracks?\/([a-zA-Z0-9-]*)/,
          result: function (string) {
            var stockSource = "epidemicsound";
            var getVar = [1, 2];
            var arr = [];
            for (var i = 0; i < string.length; i++) {
              if (getVar.includes(i)) {
                arr.push(string[i]);
              }
            }
            var stockId = idMapping(stockSource, arr);
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /yellowimages\.com\/(stock\/|(.*)p=)([0-9a-z-]*)/,
          result: function (string) {
            var stockSource = "yellowimages";
            var stockId = string[3];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /vecteezy.com\/([\/a-zA-Z-]*)\/([0-9]*)/,
          result: function (string) {
            var stockSource = "vecteezy";
            var stockId = string[2];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /creativefabrica.com\/(.*)product\/([a-z0-9-]*)/,
          result: function (string) {
            var stockSource = "creativefabrica";
            var stockId = string[2];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /lovepik.com\/([a-z]*)-([0-9]*)\//,
          result: function (string) {
            var stockSource = "lovepik";
            var stockId = string[2];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /rawpixel\.com\/image\/([0-9]*)/,
          result: function (string) {
            var stockSource = "rawpixel";
            var stockId = string[1];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /deeezy\.com\/product\/([0-9]*)/,
          result: function (string) {
            var stockSource = "deeezy";
            var stockId = string[1];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /(productioncrate|footagecrate|graphicscrate)\.com\/([a-z0-9-]*)\/([a-zA-Z0-9-_]*)/,
          result: function (string) {
            var stockSource = "footagecrate";
            var stockId = string[3];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /artgrid\.io\/clip\/([0-9]*)\//,
          result: function (string) {
            var stockSource = "artgrid_HD";
            var stockId = string[1];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /pixelsquid.com(.*)-([0-9]*)\?image=(...)/,
          result: function (string) {
            var stockSource = "pixelsquid";
            var getVar = [2, 3];
            var arr = [];
            for (var i = 0; i < string.length; i++) {
              if (getVar.includes(i)) {
                arr.push(string[i]);
              }
            }
            var stockId = idMapping(stockSource, arr);
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /pixelsquid.com(.*)-([0-9]*)/,
          result: function (string) {
            var stockSource = "pixelsquid";
            var stockId = string[2];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /ui8\.net\/(.*)\/(.*)\/([0-9a-zA-Z-]*)/,
          result: function (string) {
            var stockSource = "ui8";
            var stockId = string[3];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /iconscout.com\/((\w{2})\/?$|(\w{2})\/|)([0-9a-z-]*)\/([0-9a-z-_]*)/,
          result: function (string) {
            var stockSource = "iconscout";
            var stockId = string[5];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /designi.com.br\/([0-9a-zA-Z]*)/,
          result: function (string) {
            var stockSource = "designi";
            var stockId = string[1];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /mockupcloud.com\/(product|scene|graphics\/product)\/([a-z0-9-]*)/,
          result: function (string) {
            var stockSource = "mockupcloud";
            var stockId = string[2];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /artlist.io\/(stock-footage|video-templates)\/(.*)\/([0-9]*)/,
          result: function (string) {
            var stockSource = "artlist_footage";
            var getVar = [1, 3];
            var arr = [];
            for (var i = 0; i < string.length; i++) {
              if (getVar.includes(i)) {
                arr.push(string[i]);
              }
            }
            var stockId = idMapping(stockSource, arr);
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /artlist.io\/(sfx|royalty-free-music)\/(.*)\/([0-9]*)/,
          result: function (string) {
            var stockSource = "artlist_sound";
            var getVar = [1, 3];
            var arr = [];
            for (var i = 0; i < string.length; i++) {
              if (getVar.includes(i)) {
                arr.push(string[i]);
              }
            }
            var stockId = idMapping(stockSource, arr);
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /pixeden.com\/([0-9a-z-]*)\/([0-9a-z-]*)/,
          result: function (string) {
            var stockSource = "pixeden";
            var getVar = [1, 2];
            var arr = [];
            for (var i = 0; i < string.length; i++) {
              if (getVar.includes(i)) {
                arr.push(string[i]);
              }
            }
            var stockId = idMapping(stockSource, arr);
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /uplabs.com\/posts\/([0-9a-z-]*)/,
          result: function (string) {
            var stockSource = "uplabs";
            var stockId = string[1];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /pixelbuddha.net\/(premium|)(.*)\/([0-9a-z-]*)/,
          result: function (string) {
            var stockSource = "pixelbuddha";
            var getVar = [1, 2, 3];
            var arr = [];
            for (var i = 0; i < string.length; i++) {
              if (getVar.includes(i)) {
                arr.push(string[i]);
              }
            }
            var stockId = idMapping(stockSource, arr);
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /uihut.com\/designs\/([0-9]*)/,
          result: function (string) {
            var stockSource = "uihut";
            var stockId = string[1];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /craftwork.design\/product\/([0-9a-z-]*)/,
          result: function (string) {
            var stockSource = "craftwork";
            var stockId = string[1];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /baixardesign.com.br\/arquivo\/([0-9a-z]*)/,
          result: function (string) {
            var stockSource = "baixardesign";
            var stockId = string[1];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /soundstripe.com\/(.*)\/([0-9]*)/,
          result: function (string) {
            var stockSource = "soundstripe";
            var getVar = [1, 2];
            var arr = [];
            for (var i = 0; i < string.length; i++) {
              if (getVar.includes(i)) {
                arr.push(string[i]);
              }
            }
            var stockId = idMapping(stockSource, arr);
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /mrmockup.com\/product\/([0-9a-z-]*)/,
          result: function (string) {
            var stockSource = "mrmockup";
            var stockId = string[1];
            return { source: stockSource, id: stockId, url: str };
          },
        },
        {
          match: /designbr\.com\.br\/(.*)modal=([^&]+)/,
          result: function (string) {
            var stockSource = "designbr";
            var stockId = string[2];
            return { source: stockSource, id: stockId, url: str };
          },
        },
      ];

      var item = sourceMatch.find(function (it) {
        return str.match(it.match);
      });

      if (!item) {
        return false;
      }

      var match = str.match(item.match);
      return item.result(match);
    };

    // Helper: Detect site and cost from URL using idExtractor
    function detectFromIdExtractor(url) {
      if (!window.artlyStockOrder || !window.artlyStockOrder.sites) return null;

      var extracted = idExtractor(url);
      if (!extracted) return null;

      var sourceKey = extracted.source;
      var sites = window.artlyStockOrder.sites;
      var cfg = sites[sourceKey] || null;

      return {
        source: sourceKey,
        id: extracted.id,
        url: extracted.url,
        label: cfg && cfg.label ? cfg.label : sourceKey,
        points: cfg && typeof cfg.points !== "undefined" ? cfg.points : 0
      };
    }

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

      var info = detectFromIdExtractor(url);
      if (!info) {
        singleMeta.textContent = "Unsupported or invalid link.";
        singleMeta.classList.add("is-visible");
        return;
      }

      singleMeta.textContent = "Detected: " + info.label + " – " + info.points + " point(s) per link";
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
          var info = detectFromIdExtractor(url);

          if (info) {
            var item = {
              url: url,
              site: info.source,
              label: info.label,
              points: info.points
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
              '  <div class="stock-order-batch-item-meta">' + escapeHtml(info.label) + " – " + info.points + " point(s)</div>",
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

          // Render all results first
          var html = '<ul class="stock-order-results-list">';
          var pendingOrderIds = [];

          links.forEach(function (item, index) {
            var status = item.status || "unknown";
            var orderId = item.order_id || "";
            var safeUrl = item.url || "";
            var message = item.message || "";

            html += '<li class="stock-order-result stock-order-result--' + status + '"';
            if (orderId) {
              html += ' data-order-id="' + orderId + '"';
            }
            html += '>';

            html += '<div class="stock-order-result-top">';
            html += '<div class="stock-order-result-url">' + escapeHtml(safeUrl) + "</div>";
            html +=
              '<span class="stock-order-result-status" data-order-status>' +
              escapeHtml(message || mapStatusToText(status)) +
              "</span>";
            html += "</div>";

            html += '<div class="stock-order-result-progress">';
            html +=
              '<div class="stock-order-result-progress-bar" data-order-progress data-progress-pct="' +
              mapStatusToProgress(status) +
              '"></div>';
            html += "</div>";

            if (orderId) {
              html += '<div class="stock-order-result-actions" data-order-actions>';
              html +=
                '<a href="' +
                escapeHtml(window.artlyStockOrder ? window.artlyStockOrder.historyUrl : "/my-downloads/") +
                '" class="stock-order-result-link">';
              html += "View in history";
              html += "</a>";
              html += "</div>";
            }

            html += "</li>";

            // Collect order IDs for polling
            if (orderId && (status === "queued" || status === "processing")) {
              pendingOrderIds.push(parseInt(orderId, 10));
            }
          });

          html += "</ul>";

          if (resultsEl) {
            resultsEl.innerHTML = html;

            // Initialize progress bar widths
            var progressBars = resultsEl.querySelectorAll("[data-order-progress]");
            progressBars.forEach(function (bar) {
              var pct = parseInt(bar.getAttribute("data-progress-pct") || "0", 10);
              if (pct > 0) {
                bar.style.width = pct + "%";
              }
            });
          }

          // Start polling if we have pending orders
          if (pendingOrderIds.length) {
            startStockOrderPolling(pendingOrderIds);
          }

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

    function fetchPreviewForUrl(url) {
      return new Promise(function (resolve, reject) {
        if (!previewEndpoint) {
          if (singleMeta) {
            singleMeta.textContent = "Preview temporarily unavailable.";
            singleMeta.classList.add("is-visible");
          }
          return reject(new Error("preview_endpoint_missing"));
        }

        var metaEl = singleMeta || root.querySelector("[data-stock-order-single-meta]");
        if (metaEl) {
          metaEl.textContent = "Checking file details...";
          metaEl.classList.add("is-visible");
        }

        fetch(previewEndpoint, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": restNonce || ""
          },
          credentials: "same-origin",
          body: JSON.stringify({ url: url })
        })
          .then(function (res) {
            if (res.status === 401) {
              throw new Error("unauthorized");
            }
            return res.json();
          })
          .then(function (data) {
            if (!data || !data.success) {
              var message = (data && data.message) || "Unsupported or invalid link.";
              throw new Error(message);
            }

            var metaText =
              "Detected: " + data.site_label + " – " + data.cost_points + " point(s) per link";
            if (metaEl) {
              metaEl.textContent = metaText;
              metaEl.classList.add("is-visible");
            }

            resolve(data);
          })
          .catch(function (err) {
            console.error("Preview error:", err);
            var friendly = err && err.message ? err.message : "Failed to preview link.";
            if (friendly === "unauthorized") {
              friendly = "Your session expired. Please refresh the page and log in again.";
            } else if (friendly === "preview_endpoint_missing") {
              friendly = "Preview temporarily unavailable.";
            }

            if (metaEl) {
              metaEl.textContent = friendly;
              metaEl.classList.add("is-visible");
            }

            reject(err);
          });
      });
    }

    // Polling logic
    var pollTimer = null;
    var activeOrderIds = [];

    function startStockOrderPolling(orderIds) {
      // Merge new IDs into active list (avoid duplicates)
      orderIds.forEach(function (id) {
        id = parseInt(id, 10);
        if (!id) return;
        if (activeOrderIds.indexOf(id) === -1) {
          activeOrderIds.push(id);
        }
      });

      if (!activeOrderIds.length) return;

      // If timer already running, just let it continue
      if (pollTimer) return;

      pollTimer = window.setInterval(function () {
        if (!activeOrderIds.length) {
          stopStockOrderPolling();
          return;
        }

        pollStockOrderStatus();
      }, 5000); // every 5 seconds
    }

    function stopStockOrderPolling() {
      if (pollTimer) {
        window.clearInterval(pollTimer);
        pollTimer = null;
      }
      activeOrderIds = [];
    }

    function pollStockOrderStatus() {
      var statusEndpoint = config.statusEndpoint || "";
      if (!statusEndpoint) return;
      if (!activeOrderIds.length) return;

      var url =
        statusEndpoint + "?order_ids[]=" + activeOrderIds.join("&order_ids[]=");

      fetch(url, {
        method: "GET",
        headers: {
          "X-WP-Nonce": restNonce
        },
        credentials: "same-origin"
      })
        .then(function (res) {
          if (!res.ok) throw new Error("http");
          return res.json();
        })
        .then(function (data) {
          if (!data || !Array.isArray(data.orders)) return;

          var finalStates = ["completed", "failed", "already_downloaded"];

          data.orders.forEach(function (order) {
            updateOrderProgressUI(order);

            if (finalStates.indexOf(order.status) !== -1) {
              // Remove from active list
              activeOrderIds = activeOrderIds.filter(function (id) {
                return id !== order.id;
              });
            }
          });

          if (!activeOrderIds.length) {
            stopStockOrderPolling();
          }
        })
        .catch(function () {
          // Soft-fail: do not break UI; just stop polling on repeated failures.
        });
    }

    function mapStatusToProgress(status) {
      switch (status) {
        case "queued":
          return 25;
        case "processing":
          return 60;
        case "completed":
        case "already_downloaded":
          return 100;
        case "failed":
          return 100;
        default:
          return 40;
      }
    }

    function mapStatusToText(status) {
      switch (status) {
        case "queued":
          return "Queued";
        case "processing":
          return "Processing";
        case "completed":
          return "Ready";
        case "already_downloaded":
          return "Already downloaded";
        case "failed":
          return "Failed";
        default:
          return "Pending";
      }
    }

    function updateOrderProgressUI(order) {
      if (!order || !order.id) return;

      var selector = '[data-order-id="' + order.id + '"]';
      var row = root.querySelector(selector);
      if (!row) return;

      var statusEl = row.querySelector("[data-order-status]");
      var barEl = row.querySelector("[data-order-progress]");
      var actionsEl = row.querySelector("[data-order-actions]");

      var pct = mapStatusToProgress(order.status);
      var label = mapStatusToText(order.status);

      if (barEl) {
        barEl.style.width = pct + "%";
        barEl.setAttribute("data-progress-pct", pct);

        row.classList.remove(
          "stock-order-result--queued",
          "stock-order-result--processing",
          "stock-order-result--completed",
          "stock-order-result--already_downloaded",
          "stock-order-result--failed"
        );

        row.classList.add("stock-order-result--" + (order.status || "unknown"));
      }

      if (statusEl) {
        statusEl.textContent = label;
      }

      // If completed with download_url, show direct download link
      if (actionsEl && order.download_url) {
        actionsEl.innerHTML =
          '<a href="' +
          escapeHtml(order.download_url) +
          '" target="_blank" rel="noopener" class="stock-order-result-link">Download file</a>';
      } else if (actionsEl && (order.status === "completed" || order.status === "already_downloaded")) {
        // Show history link if no direct download URL
        if (!actionsEl.querySelector("a")) {
          actionsEl.innerHTML =
            '<a href="' +
            escapeHtml(window.artlyStockOrder ? window.artlyStockOrder.historyUrl : "/my-downloads/") +
            '" class="stock-order-result-link">View in history</a>';
        }
      }
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

    function createPreviewModal(root) {
      var modal = root.querySelector("[data-stock-order-preview-modal]");
      if (modal) return modal;

      modal = document.createElement("div");
      modal.className = "stock-order-preview-overlay";
      modal.setAttribute("data-stock-order-preview-modal", "");

      modal.innerHTML =
        '<div class="stock-order-preview-card">' +
        '  <div class="stock-order-preview-thumb-wrap">' +
        '    <img data-preview-img alt="" class="stock-order-preview-thumb" />' +
        "  </div>" +
        '  <div class="stock-order-preview-info">' +
        '    <div class="stock-order-preview-title" data-preview-title></div>' +
        '    <div class="stock-order-preview-meta">' +
        '      <div data-preview-source></div>' +
        '      <div data-preview-id></div>' +
        '      <div data-preview-cost></div>' +
        '      <div data-preview-balance></div>' +
        "    </div>" +
        '    <div class="stock-order-preview-actions">' +
        '      <button type="button" class="stock-order-preview-confirm" data-preview-confirm></button>' +
        '      <button type="button" class="stock-order-preview-cancel" data-preview-cancel>Cancel</button>' +
        "    </div>" +
        "  </div>" +
        "</div>";

      root.appendChild(modal);
      return modal;
    }

    function openPreviewModal(root, preview, onConfirm) {
      var modal = createPreviewModal(root);
      var img = modal.querySelector("[data-preview-img]");
      var titleEl = modal.querySelector("[data-preview-title]");
      var sourceEl = modal.querySelector("[data-preview-source]");
      var idEl = modal.querySelector("[data-preview-id]");
      var costEl = modal.querySelector("[data-preview-cost]");
      var balEl = modal.querySelector("[data-preview-balance]");
      var confirm = modal.querySelector("[data-preview-confirm]");
      var cancel = modal.querySelector("[data-preview-cancel]");

      var siteLabel = preview.site_label || preview.site || "";
      var stockId = preview.stock_id || "";
      var costValue = preview.cost_points;
      if (typeof costValue === "undefined" || costValue === null || costValue === "") {
        costValue = 0;
      }
      var balanceValue = preview.balance;
      if (typeof balanceValue === "undefined" || balanceValue === null || balanceValue === "") {
        balanceValue = 0;
      }

      titleEl.textContent = (siteLabel ? siteLabel + " " : "") + stockId;
      sourceEl.textContent = "Source: " + siteLabel;
      idEl.textContent = "ID: " + stockId;
      costEl.textContent = "Cost: " + costValue + " point(s)";
      balEl.textContent = "Your balance: " + balanceValue + " point(s)";

      if (preview.preview_thumb) {
        img.src = preview.preview_thumb;
        img.alt = siteLabel ? siteLabel + " preview" : "";
        img.style.display = "";
      } else {
        img.removeAttribute("src");
        img.style.display = "none";
      }

      confirm.textContent = "Confirm order (" + costValue + " point(s))";

      if (!preview.enough_points) {
        confirm.disabled = true;
        costEl.textContent += " – not enough points";
      } else {
        confirm.disabled = false;
      }

      function close() {
        modal.classList.remove("is-visible");
        confirm.onclick = null;
        cancel.onclick = null;
        modal.onclick = null;
        document.removeEventListener("keydown", handleKeydown);
      }

      function handleKeydown(evt) {
        if (evt.key === "Escape") {
          close();
        }
      }

      confirm.onclick = function () {
        if (confirm.disabled) {
          return;
        }
        close();
        if (typeof onConfirm === "function") {
          onConfirm();
        }
      };

      cancel.onclick = function () {
        close();
      };

      modal.onclick = function (evt) {
        if (evt.target === modal) {
          close();
        }
      };

      document.addEventListener("keydown", handleKeydown);

      modal.classList.add("is-visible");
    }

    // Single mode submit WITH preview + confirm
    if (singleSubmit && singleInput) {
      singleSubmit.addEventListener("click", function () {
        var url = singleInput.value.trim();
        if (!url) return;

        fetchPreviewForUrl(url)
          .then(function (preview) {
            openPreviewModal(root, preview, function () {
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
          })
          .catch(function (err) {
            if (err && err.message === "unauthorized") {
              showError(
                "Your session expired. Please refresh the page and log in again."
              );
            }
          });
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

    // Collapsible supported websites list
    var supCard = root.querySelector(".stock-order-supported");
    var supToggle = root.querySelector("[data-stock-order-supported-toggle]");

    if (supCard && supToggle) {
      supToggle.addEventListener("click", function () {
        var expanded = supCard.classList.toggle("is-expanded");
        supToggle.textContent = expanded
          ? "Show fewer websites"
          : "Show all websites";
      });
    }
  });
})();

