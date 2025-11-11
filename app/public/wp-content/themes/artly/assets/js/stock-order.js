(function () {
  document.addEventListener("DOMContentLoaded", function () {
    var root = document.querySelector(".artly-stock-order-page");
    if (!root) return;

    var config = window.artlyStockOrder || {};
    var sitesConfig = config.sites || {};
    var endpoint = config.endpoint || "";
    var previewEndpoint = config.previewEndpoint || "";
    var walletEndpoint = config.walletEndpoint || "";
    var statusEndpoint = config.statusEndpoint || "";
    var restNonce = config.restNonce || "";
    var downloadEndpointBase = (config.downloadEndpointBase || "/wp-json/artly/v1/stock-orders/").replace(/\/+$/, "/");
    var currentWalletBalance = 0;

    var pollingConfig = {
      downloadRetries: 3,
      retryDelay: 1000
    };

    // ========== STATUS POLLING MANAGER ==========
    var statusConfig = {
      pollInterval: 5000,   // 5s
      maxPollMinutes: 10    // stop after 10 minutes
    };
    var activeOrders = {}; // taskId -> { startedAt: timestamp, element: DOM node, notified: bool }
    var statusPollTimer = null;

    /*
     * Testing Checklist (Manual):
     * 
     * - Place a single order, see result card → watch it move from Queued → Processing → Completed without page refresh.
     * - Confirm toast appears and, if allowed, browser notification.
     * - Confirm clicking "Download now" uses the link updated from webhook/endpoint.
     * - Place a batch order; multiple cards update independently.
     * - Shut down NEHTW webhook temporarily: order should still eventually get completed statuses via existing logic (no fatal errors).
     * - Reload /my-downloads/ and ensure statuses reflect final state when available.
     */

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
          match: /istockphoto\.com\/(.*)gm([0-9]+)(?:-[0-9]+)?/,
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

    // Load wallet info on page load
    function loadWalletInfo() {
      if (!walletEndpoint) return;

      fetch(walletEndpoint, {
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
          if (!data || !data.success) return;

          var balance = typeof data.balance === "number" ? data.balance : 0;
          currentWalletBalance = balance;

          var balanceEl = root.querySelector("[data-wallet-balance]");
          if (balanceEl) {
            balanceEl.textContent = balance.toFixed(1) + " point(s)";
          }

          var billingEl = root.querySelector("[data-wallet-next-billing]");
          if (billingEl) {
            var nextBilling = data.next_billing || null;
            if (nextBilling && nextBilling !== "0000-00-00 00:00:00") {
              try {
                var date = new Date(nextBilling);
                if (!isNaN(date.getTime())) {
                  var formatted = date.toLocaleDateString("en-US", {
                    year: "numeric",
                    month: "short",
                    day: "numeric"
                  });
                  billingEl.textContent = formatted;
                } else {
                  billingEl.textContent = "—";
                }
              } catch (e) {
                billingEl.textContent = "—";
              }
            } else {
              billingEl.textContent = "—";
            }
          }
        })
        .catch(function () {
          // Soft-fail: don't break the page if wallet fetch fails
        });
    }

    // Load wallet info on page load
    loadWalletInfo();

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

    // Batch mode: enhanced previews
    var batchInput = root.querySelector("#stock-order-batch-input");
    var batchPreviewContainer = root.querySelector("[data-stock-order-batch-preview-container]");
    var batchSummary = root.querySelector("[data-stock-order-batch-summary]");
    var selectedCountEl = batchSummary ? batchSummary.querySelector("[data-selected-count]") : null;
    var totalCostEl = batchSummary ? batchSummary.querySelector("[data-total-cost]") : null;
    var submitBatchBtn = root.querySelector("[data-stock-order-submit-batch]");
    var batchItems = [];
    var batchProcessTimer = null;
    var batchLimitWarningShown = false;
    var batchSubmitDefaultLabel = submitBatchBtn ? submitBatchBtn.textContent : "";
    var MAX_BATCH_LINKS = 5;

    if (submitBatchBtn) {
      submitBatchBtn.disabled = true;
    }

    function scheduleProcessBatch(delay) {
      if (!batchInput) return;
      if (batchProcessTimer) {
        window.clearTimeout(batchProcessTimer);
      }
      batchProcessTimer = window.setTimeout(function () {
        batchProcessTimer = null;
        processBatchLinks();
      }, typeof delay === "number" ? delay : 250);
    }

    function clearBatchPreviews() {
      batchItems = [];
      if (batchPreviewContainer) {
        batchPreviewContainer.innerHTML = "";
      }
      if (batchSummary) {
        batchSummary.hidden = true;
      }
      if (selectedCountEl) {
        selectedCountEl.textContent = "0";
      }
      if (totalCostEl) {
        totalCostEl.textContent = "0.0";
      }
      if (submitBatchBtn) {
        submitBatchBtn.disabled = true;
        submitBatchBtn.textContent = batchSubmitDefaultLabel || submitBatchBtn.textContent;
      }
      batchLimitWarningShown = false;
    }

    function processBatchLinks() {
      if (!batchInput || !batchPreviewContainer) return;

      var text = batchInput.value || "";
      var lines = text
        .split(/\r?\n/)
        .map(function (line) {
          return line.trim();
        })
        .filter(function (line) {
          return line.length > 0;
        });

      var seen = {};
      var uniqueUrls = [];
      lines.forEach(function (line) {
        if (!/^https?:\/\//i.test(line)) {
          return;
        }
        if (seen[line]) {
          return;
        }
        seen[line] = true;
        uniqueUrls.push(line);
      });

      if (!uniqueUrls.length) {
        clearBatchPreviews();
        return;
      }

      if (uniqueUrls.length > MAX_BATCH_LINKS) {
        if (!batchLimitWarningShown) {
          showNotification("Only the first 5 links will be processed.", "warning");
        }
        batchLimitWarningShown = true;
      } else {
        batchLimitWarningShown = false;
      }

      uniqueUrls = uniqueUrls.slice(0, MAX_BATCH_LINKS);

      var existingMap = {};
      batchItems.forEach(function (item) {
        existingMap[item.url] = item;
      });

      var newItems = [];

      uniqueUrls.forEach(function (url, index) {
        var existingItem = existingMap[url];
        if (existingItem) {
          existingItem.card.dataset.batchIndex = index;
          newItems.push(existingItem);
          batchPreviewContainer.appendChild(existingItem.card);
        } else {
          var created = addBatchPreviewCard(url, index);
          if (created) {
            newItems.push(created);
          }
        }
      });

      batchItems.forEach(function (item) {
        if (uniqueUrls.indexOf(item.url) === -1 && item.card && item.card.parentNode === batchPreviewContainer) {
          batchPreviewContainer.removeChild(item.card);
        }
      });

      batchItems = newItems;

      if (!batchItems.length) {
        clearBatchPreviews();
        return;
      }

      batchItems.forEach(function (item, index) {
        item.index = index;
        item.card.dataset.batchIndex = index;
        if (item.checkbox) {
          item.checkbox.checked = !item.error && item.selected !== false;
          item.checkbox.disabled = item.loading || item.error;
        }
        if (!item.preview && !item.fetching) {
          fetchPreviewForBatchItem(item.url, index);
        }
        item.card.classList.toggle("is-selected", !item.error && (item.selected !== false));
      });

      if (batchSummary) {
        batchSummary.hidden = false;
      }

      updateBatchSummary();
    }

    function addBatchPreviewCard(url, index) {
      if (!batchPreviewContainer) return null;

      var card = document.createElement("div");
      card.className = "batch-preview-card is-loading is-selected";
      card.setAttribute("data-batch-index", index);

      var checkboxWrap = document.createElement("div");
      checkboxWrap.className = "batch-preview-checkbox";
      var checkbox = document.createElement("input");
      checkbox.type = "checkbox";
      checkbox.checked = true;
      checkbox.disabled = true;
      checkboxWrap.appendChild(checkbox);

      var imageWrap = document.createElement("div");
      imageWrap.className = "batch-preview-image is-loading";
      imageWrap.textContent = "Loading";

      var infoWrap = document.createElement("div");
      infoWrap.className = "batch-preview-info";
      var titleEl = document.createElement("div");
      titleEl.className = "batch-preview-title";
      titleEl.textContent = url;
      var metaEl = document.createElement("div");
      metaEl.className = "batch-preview-meta";
      metaEl.textContent = "Fetching details...";
      var errorEl = document.createElement("div");
      errorEl.className = "batch-preview-error";
      infoWrap.appendChild(titleEl);
      infoWrap.appendChild(metaEl);
      infoWrap.appendChild(errorEl);

      var actionsWrap = document.createElement("div");
      actionsWrap.className = "batch-preview-actions";
      var confirmBtn = document.createElement("button");
      confirmBtn.type = "button";
      confirmBtn.className = "batch-preview-confirm";
      confirmBtn.disabled = true;
      confirmBtn.textContent = "Fetching...";
      var removeBtn = document.createElement("button");
      removeBtn.type = "button";
      removeBtn.className = "batch-preview-remove";
      removeBtn.setAttribute("aria-label", "Remove link");
      removeBtn.innerHTML = "&times;";
      actionsWrap.appendChild(confirmBtn);
      actionsWrap.appendChild(removeBtn);

      card.appendChild(checkboxWrap);
      card.appendChild(imageWrap);
      card.appendChild(infoWrap);
      card.appendChild(actionsWrap);

      batchPreviewContainer.appendChild(card);

      var item = {
        url: url,
        card: card,
        checkbox: checkbox,
        imageEl: imageWrap,
        infoEl: infoWrap,
        titleEl: titleEl,
        metaEl: metaEl,
        errorEl: errorEl,
        confirmBtn: confirmBtn,
        removeBtn: removeBtn,
        selected: true,
        preview: null,
        loading: true,
        fetching: false,
        error: false
      };

      checkbox.addEventListener("change", function () {
        item.selected = checkbox.checked;
        if (item.error) {
          item.selected = false;
          checkbox.checked = false;
        }
        card.classList.toggle("is-selected", item.selected && !item.error);
        updateBatchSummary();
      });

      confirmBtn.addEventListener("click", function () {
        if (confirmBtn.disabled) return;
        var idx = batchItems.indexOf(item);
        if (idx === -1) return;
        orderSingleBatchItem(idx);
      });

      removeBtn.addEventListener("click", function () {
        var idx = batchItems.indexOf(item);
        if (idx === -1) return;
        removeBatchItem(idx);
      });

      return item;
    }

    function fetchPreviewForBatchItem(url, index) {
      if (!previewEndpoint) return;
      var item = batchItems[index];
      if (!item || item.url !== url) return;

      item.fetching = true;
      item.loading = true;
      item.error = false;
      if (item.card) {
        item.card.classList.remove("has-error");
        item.card.classList.add("is-loading");
      }
      if (item.checkbox) {
        item.checkbox.disabled = true;
        item.checkbox.checked = true;
      }
      if (item.imageEl) {
        item.imageEl.classList.add("is-loading");
        item.imageEl.textContent = "Loading";
      }
      if (item.metaEl) {
        item.metaEl.textContent = "Fetching details...";
      }
      if (item.errorEl) {
        item.errorEl.textContent = "";
      }
      if (item.confirmBtn) {
        item.confirmBtn.disabled = true;
        item.confirmBtn.textContent = "Fetching...";
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
            var message = (data && data.message) || "Preview unavailable.";
            throw new Error(message);
          }
          handleBatchItemPreview(index, data);
        })
        .catch(function (err) {
          var friendly = err && err.message ? err.message : "Failed to fetch preview.";
          if (friendly === "unauthorized") {
            friendly = "Your session expired. Please refresh the page and log in again.";
            showNotification(friendly, "error");
          }
          handleBatchItemError(index, friendly);
        });
    }

    function handleBatchItemPreview(index, previewData) {
      var item = batchItems[index];
      if (!item) return;

      item.preview = previewData;
      item.loading = false;
      item.fetching = false;
      item.error = false;
      item.selected = true;

      var siteLabel = previewData.site_label || previewData.site || "";
      var stockId = previewData.stock_id || previewData.id || "";
      var costRaw = typeof previewData.cost_points !== "undefined" ? parseFloat(previewData.cost_points) : 0;
      if (isNaN(costRaw)) {
        costRaw = 0;
      }
      var costText = costRaw.toFixed(1);

      if (item.card) {
        item.card.classList.remove("is-loading");
        item.card.classList.remove("has-error");
        item.card.classList.add("is-selected");
      }
      if (item.checkbox) {
        item.checkbox.disabled = false;
        item.checkbox.checked = true;
      }
      if (item.imageEl) {
        item.imageEl.classList.remove("is-loading");
        item.imageEl.innerHTML = "";
        if (previewData.preview_thumb) {
          var img = document.createElement("img");
          img.src = previewData.preview_thumb;
          img.alt = siteLabel ? siteLabel + " preview" : "Stock preview";
          item.imageEl.appendChild(img);
        } else {
          item.imageEl.textContent = "No preview";
        }
      }
      if (item.titleEl) {
        item.titleEl.textContent = siteLabel && stockId ? siteLabel + " – " + stockId : siteLabel || item.url;
      }
      if (item.metaEl) {
        item.metaEl.innerHTML = "";
        if (siteLabel) {
          var sourceSpan = document.createElement("span");
          sourceSpan.textContent = siteLabel;
          item.metaEl.appendChild(sourceSpan);
        }
        if (stockId) {
          var idSpan = document.createElement("span");
          idSpan.textContent = "#" + stockId;
          item.metaEl.appendChild(idSpan);
        }
        var costSpan = document.createElement("span");
        costSpan.className = "batch-preview-cost";
        costSpan.textContent = costText + " point(s)";
        item.metaEl.appendChild(costSpan);
      }
      if (item.errorEl) {
        item.errorEl.textContent = "";
      }

      if (item.confirmBtn) {
        if (previewData.enough_points === false) {
          item.confirmBtn.disabled = true;
          item.confirmBtn.textContent = "Not enough points";
          if (item.checkbox) {
            item.checkbox.checked = false;
            item.checkbox.disabled = true;
          }
          item.selected = false;
          if (item.card) {
            item.card.classList.remove("is-selected");
          }
        } else {
          item.confirmBtn.disabled = false;
          item.confirmBtn.textContent = "Confirm (" + costText + " pt)";
        }
      }

      updateBatchSummary();
    }

    function handleBatchItemError(index, errorMessage) {
      var item = batchItems[index];
      if (!item) return;

      item.preview = null;
      item.loading = false;
      item.fetching = false;
      item.error = true;
      item.selected = false;

      if (item.card) {
        item.card.classList.remove("is-loading");
        item.card.classList.add("has-error");
        item.card.classList.remove("is-selected");
      }
      if (item.checkbox) {
        item.checkbox.checked = false;
        item.checkbox.disabled = true;
      }
      if (item.imageEl) {
        item.imageEl.classList.remove("is-loading");
        item.imageEl.textContent = "--";
      }
      if (item.metaEl) {
        item.metaEl.textContent = "";
      }
      if (item.errorEl) {
        item.errorEl.textContent = errorMessage || "Preview unavailable.";
      }
      if (item.confirmBtn) {
        item.confirmBtn.disabled = true;
        item.confirmBtn.textContent = "Unavailable";
      }

      updateBatchSummary();
    }

    function updateBatchSummary() {
      var selectedCount = 0;
      var totalCost = 0;

      batchItems.forEach(function (item) {
        if (item.preview && !item.error && item.selected) {
          selectedCount += 1;
          var cost = parseFloat(item.preview.cost_points || 0);
          if (!isNaN(cost)) {
            totalCost += cost;
          }
        }
      });

      if (selectedCountEl) {
        selectedCountEl.textContent = String(selectedCount);
      }
      if (totalCostEl) {
        totalCostEl.textContent = totalCost.toFixed(1);
      }
      if (batchSummary) {
        batchSummary.hidden = batchItems.length === 0;
      }
      if (submitBatchBtn) {
        submitBatchBtn.disabled = !selectedCount || totalCost > currentWalletBalance;
      }
    }

    function removeBatchItem(index) {
      if (index < 0 || index >= batchItems.length) return;
      var item = batchItems[index];
      if (item.card && item.card.parentNode === batchPreviewContainer) {
        batchPreviewContainer.removeChild(item.card);
      }
      batchItems.splice(index, 1);

      if (!batchItems.length) {
        if (batchInput) {
          batchInput.value = "";
        }
        clearBatchPreviews();
      } else {
        if (batchInput) {
          batchInput.value = batchItems.map(function (entry) {
            return entry.url;
          }).join("\n");
        }
        batchItems.forEach(function (entry, idx) {
          entry.card.dataset.batchIndex = idx;
        });
        updateBatchSummary();
      }
    }

    function orderSingleBatchItem(index) {
      var item = batchItems[index];
      if (!item || !item.preview || item.error || item.loading) return;

      var confirmBtn = item.confirmBtn;
      var originalText = confirmBtn ? confirmBtn.textContent : "";
      if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.textContent = "Ordering...";
      }

      var payload = {
        links: [
          {
            url: item.url,
            selected: true
          }
        ]
      };

      submitStockOrder(payload, function (success) {
        if (success) {
          showNotification("Order placed for 1 link.", "success");
          removeBatchItem(index);
        } else if (confirmBtn) {
          confirmBtn.disabled = false;
          confirmBtn.textContent = originalText || "Confirm";
        }
      });
    }

    if (batchInput) {
      batchInput.addEventListener("input", function () {
        scheduleProcessBatch();
      });
      batchInput.addEventListener("paste", function () {
        scheduleProcessBatch(150);
      });
      batchInput.addEventListener("blur", function () {
        scheduleProcessBatch();
      });
    }

    if (submitBatchBtn) {
      submitBatchBtn.addEventListener("click", function () {
        if (submitBatchBtn.disabled) return;

        var selectedItems = batchItems.filter(function (item) {
          return item.preview && !item.error && item.selected;
        });

        if (!selectedItems.length) {
          showNotification("Please select at least one link to order.", "warning");
          return;
        }

        var links = selectedItems.map(function (item) {
          return {
            url: item.url,
            selected: true
          };
        });

        var payload = { links: links };

        submitBatchBtn.disabled = true;
        submitBatchBtn.textContent = "Ordering...";

        submitStockOrder(payload, function (success) {
          submitBatchBtn.disabled = false;
          submitBatchBtn.textContent = batchSubmitDefaultLabel || "Order Selected Links";
          if (success) {
            showNotification("Order placed for selected links.", "success");
            if (batchInput) {
              batchInput.value = "";
            }
            clearBatchPreviews();
          }
        });
      });
    }

    if (batchInput && batchInput.value.trim()) {
      processBatchLinks();
    }

    fetchWalletBalance();

    function normalizeOrderForUI(order, resultEl) {
      var normalized = {
        url: "",
        status: "queued",
        message: "",
        task_id: "",
        order_id: "",
        download_url: "",
        progress: undefined
      };

      order = order || {};

      var existingStatus = resultEl && resultEl.dataset && resultEl.dataset.status ? resultEl.dataset.status : "";
      var rawStatus = "";
      if (typeof order.status === "string") {
        rawStatus = order.status.toLowerCase();
      } else if (typeof order.status === "number") {
        rawStatus = String(order.status);
      }

      if (!rawStatus && existingStatus) {
        rawStatus = existingStatus;
      }

      if (!rawStatus) {
        rawStatus = "queued";
      }

      if (rawStatus === "pending") rawStatus = "queued";
      if (rawStatus === "complete") rawStatus = "completed";
      if (rawStatus === "success") rawStatus = "completed";
      if (rawStatus === "ready_to_download") rawStatus = "ready";

      normalized.status = rawStatus;

      normalized.task_id = order.task_id || (resultEl ? resultEl.getAttribute("data-task-id") || "" : "");
      normalized.order_id = typeof order.order_id !== "undefined" ? order.order_id : order.id || "";

      var existingUrl = "";
      if (resultEl) {
        var existingUrlEl = resultEl.querySelector(".stock-order-result-url");
        if (existingUrlEl) {
          existingUrl = existingUrlEl.textContent || "";
        }
      }

      normalized.url = order.url || order.source_url || existingUrl || "";

      var downloadUrl = "";
      var status = (normalized.status || "").toLowerCase();

      // For already_downloaded we NEVER trust cached links; they must be regenerated via the
      // dedicated download endpoint so we always get a fresh temporary URL.
      if (status !== "already_downloaded") {
        if (typeof order.download_url === "string" && order.download_url) {
          downloadUrl = order.download_url;
        } else if (typeof order.downloadLink === "string" && order.downloadLink) {
          downloadUrl = order.downloadLink;
        } else if (typeof order.download_link === "string" && order.download_link) {
          downloadUrl = order.download_link;
        }

        if (!downloadUrl && resultEl) {
          var existingBtn = resultEl.querySelector(".stock-order-download-btn");
          if (existingBtn) {
            downloadUrl = existingBtn.getAttribute("href") || "";
          }
        }
      }
      normalized.download_url = downloadUrl || "";

      if (typeof order.progress === "number" && !isNaN(order.progress)) {
        normalized.progress = order.progress;
      } else if (resultEl) {
        var progressEl = resultEl.querySelector("[data-progress]");
        if (progressEl) {
          var progressAttr = progressEl.getAttribute("data-progress");
          if (progressAttr) {
            var parsed = parseFloat(progressAttr);
            if (!isNaN(parsed)) {
              normalized.progress = parsed;
            }
          }
        }
      }

      var message = "";
      if (typeof order.message === "string") {
        message = order.message;
      } else if (resultEl) {
        var existingMessageEl = resultEl.querySelector("[data-message]");
        if (existingMessageEl) {
          message = existingMessageEl.textContent || "";
        }
      }

      if (!message) {
        message = getStatusLabel(normalized.status);
      }

      normalized.message = message;

      return normalized;
    }

    function getStatusLabel(status) {
      var key = (status || "").toString().toLowerCase();
      var labels = {
        queued: "Queued...",
        pending: "Pending...",
        processing: "Processing...",
        ready: "Ready",
        completed: "Completed",
        failed: "Failed",
        error: "Error",
        timeout: "Timeout",
        already_downloaded: "Already downloaded",
        insufficient_points: "Not enough points",
        skipped: "Skipped",
        invalid: "Error",
        not_found: "Not found",
        unknown: "Processing..."
      };
      return labels[key] || (status ? status : "Processing...");
    }

    function getStatusProgress(status) {
      switch ((status || "").toLowerCase()) {
        case "queued":
          return 10;
        case "processing":
          return 55;
        case "ready":
          return 90;
        case "completed":
        case "already_downloaded":
          return 100;
        case "failed":
        case "error":
        case "timeout":
          return 100;
        case "insufficient_points":
        case "skipped":
          return 0;
        default:
          return 25;
      }
    }

    function createOrderResultElement(order) {
      var normalized = normalizeOrderForUI(order, null);
      var resultEl = document.createElement("div");
      var statusClass = normalized.status || "queued";
      resultEl.className = "stock-order-result stock-order-result--" + statusClass;
      resultEl.dataset.status = statusClass;

      if (normalized.task_id) {
        resultEl.setAttribute("data-task-id", normalized.task_id);
      }

      var safeUrl = normalized.url ? escapeHtml(normalized.url) : "Stock order";

      resultEl.innerHTML = [
        '<div class="stock-order-result-top">',
        '  <div class="stock-order-result-url">' + safeUrl + "</div>",
        '  <div class="stock-order-result-status" data-status>' + escapeHtml(getStatusLabel(statusClass)) + "</div>",
        "</div>",
        '<div class="stock-order-result-progress">',
        '  <div class="stock-order-result-progress-bar" data-progress style="width:0%"></div>',
        "</div>",
        '<div class="stock-order-result-message" data-message></div>',
        '<div class="stock-order-result-actions" data-actions></div>'
      ].join("");

      updateOrderResult(resultEl, normalized);

      return resultEl;
    }

    function updateOrderResult(resultEl, order) {
      if (!resultEl) return;

      var normalized = normalizeOrderForUI(order, resultEl);
      var status = (normalized.status || "queued").toLowerCase();

      resultEl.className = "stock-order-result stock-order-result--" + status;
      resultEl.dataset.status = status;

      if (normalized.task_id) {
        resultEl.setAttribute("data-task-id", normalized.task_id);
      }

      var statusEl = resultEl.querySelector("[data-status]");
      if (statusEl) {
        statusEl.textContent = getStatusLabel(status);
      }

      var messageEl = resultEl.querySelector("[data-message]");
      if (messageEl) {
        if (normalized.message) {
          messageEl.textContent = normalized.message;
          messageEl.classList.add("is-visible");
        } else {
          messageEl.textContent = "";
          messageEl.classList.remove("is-visible");
        }
      }

      var progressEl = resultEl.querySelector("[data-progress]");
      if (progressEl) {
        var pct = typeof normalized.progress === "number" && !isNaN(normalized.progress)
          ? normalized.progress
          : getStatusProgress(status);
        pct = Math.max(0, Math.min(100, pct));
        progressEl.style.width = pct + "%";
        progressEl.setAttribute("data-progress", String(pct));
      }

      var actionsEl = resultEl.querySelector("[data-actions]");
      if (actionsEl) {
        // For already_downloaded, ALWAYS render button to generate fresh link, never use cached URL
        if (status === "already_downloaded" && normalized.task_id) {
          actionsEl.innerHTML =
            '<button type="button" class="stock-order-result-link stock-order-download-btn" data-generate-download="1">' +
            '<span class="stock-order-download-icon" aria-hidden="true">⬇</span>' +
            '<span>Download now</span>' +
            "</button>";

          var generateBtn = actionsEl.querySelector('[data-generate-download="1"]');
          if (generateBtn) {
            generateBtn.addEventListener("click", function (ev) {
              ev.preventDefault();
              ev.stopPropagation();
              generateDownloadLink(normalized.task_id, resultEl, 0);
            });
          }
        } else if (normalized.download_url && status !== "already_downloaded") {
          // Only render direct link if we have a download_url AND it's not already_downloaded
          actionsEl.innerHTML =
            '<a href="' +
            escapeHtml(normalized.download_url) +
            '" target="_blank" rel="noopener" class="stock-order-result-link stock-order-download-btn">' +
            '<span class="stock-order-download-icon" aria-hidden="true">⬇</span>' +
            '<span>Download now</span>' +
            "</a>";
        } else if (
          status === "completed" ||
          status === "ready" ||
          status === "failed" ||
          status === "error" ||
          status === "timeout"
        ) {
          actionsEl.innerHTML =
            '<a href="' +
            escapeHtml((window.artlyStockOrder && window.artlyStockOrder.historyUrl) || "/my-downloads/") +
            '" class="stock-order-result-link">View in history →</a>';
        } else {
          actionsEl.innerHTML = "";
        }
      }
    }

    function ensureResultsList(container) {
      if (!container) return null;
      var list = container.querySelector(".stock-order-results-list");
      if (!list) {
        list = document.createElement("div");
        list.className = "stock-order-results-list";
        container.appendChild(list);
      }
      return list;
    }

    function generateDownloadLink(taskId, resultEl, retryCount) {
      if (!taskId || !resultEl || !downloadEndpointBase) {
        return;
      }

      retryCount = retryCount || 0;

      updateOrderResult(resultEl, {
        status: "ready",
        progress: 95,
        message: retryCount ? "Finalizing download link..." : "Preparing download link..."
      });

      var headers = {};
      if (restNonce) {
        headers["X-WP-Nonce"] = restNonce;
      }

      // For already_downloaded items, always request fresh link (add ?fresh=true)
      // Check both dataset.status and any existing button data attribute
      var url = downloadEndpointBase + encodeURIComponent(taskId) + "/download";
      var currentStatus = resultEl ? (resultEl.dataset.status || "").toLowerCase() : "";
      var isAlreadyDownloaded = currentStatus === "already_downloaded";
      
      // Also check if the button has data-generate-download attribute (indicates already_downloaded)
      if (!isAlreadyDownloaded && resultEl) {
        var generateBtn = resultEl.querySelector('[data-generate-download="1"]');
        if (generateBtn) {
          isAlreadyDownloaded = true;
        }
      }
      
      if (isAlreadyDownloaded) {
        url += "?fresh=true";
      }
      
      fetch(url, {
        method: "GET",
        headers: headers,
        credentials: "same-origin"
      })
        .then(function (res) {
          if (res.status === 401) {
            updateOrderResult(resultEl, {
              status: "error",
              progress: 100,
              message: "Your session expired. Please refresh the page and log in again."
            });
            throw new Error("unauthorized");
          }

          if (!res.ok) {
            throw new Error("Download HTTP " + res.status);
          }

          return res.json();
        })
        .then(function (data) {
          if (!data || !data.success || !data.download_url) {
            throw new Error((data && data.message) || "Missing download_url");
          }

          updateOrderResult(resultEl, {
            status: (data.status || "completed").toLowerCase(),
            progress: 100,
            message: data.message || "Download ready",
            download_url: data.download_url
          });

          try {
            window.open(data.download_url, "_blank");
          } catch (err) {}
        })
        .catch(function (error) {
          if (error && error.message === "unauthorized") {
            return;
          }

          if (retryCount < pollingConfig.downloadRetries) {
            window.setTimeout(function () {
              generateDownloadLink(taskId, resultEl, retryCount + 1);
            }, pollingConfig.retryDelay);
            return;
          }

          if (window.console && console.warn) {
            console.warn("generateDownloadLink failed:", error);
          }

          updateOrderResult(resultEl, {
            status: "error",
            progress: 100,
            message: "We couldn't generate a download link yet. You can retry from My Downloads."
          });
        });
    }

    function applyWalletBalance(balance) {
      var numericBalance = parseFloat(balance);
      if (isNaN(numericBalance)) {
        numericBalance = 0;
      }
      currentWalletBalance = numericBalance;

      if (typeof window.updateWalletDisplay === "function") {
        window.updateWalletDisplay(numericBalance);
      } else if (typeof window.artlyUpdateWalletBalance === "function") {
        window.artlyUpdateWalletBalance(numericBalance);
      }

      var balanceEls = document.querySelectorAll("[data-artly-wallet-balance]");
      balanceEls.forEach(function (el) {
        el.textContent = numericBalance.toFixed(1);
      });

      var walletBalanceEl = root.querySelector("[data-wallet-balance]");
      if (walletBalanceEl) {
        walletBalanceEl.textContent = numericBalance.toFixed(1);
      }

      updateBatchSummary();
    }

    function fetchWalletBalance() {
      if (!walletEndpoint) {
        return Promise.resolve();
      }

      return fetch(walletEndpoint, {
        method: "GET",
        headers: {
          "X-WP-Nonce": restNonce || ""
        },
        credentials: "same-origin"
      })
        .then(function (res) {
          if (res.status === 401) {
            throw new Error("unauthorized");
          }
          if (!res.ok) {
            throw new Error("http_error");
          }
          return res.json();
        })
        .then(function (data) {
          if (typeof data.balance !== "undefined") {
            applyWalletBalance(data.balance);
          }
          var billingEl = root.querySelector("[data-wallet-next-billing]");
          if (billingEl) {
            billingEl.textContent = data && data.next_billing ? data.next_billing : "--";
          }
          return data;
        })
        .catch(function (err) {
          if (err && err.message === "unauthorized") {
            console.warn("Wallet info request unauthorized.");
          } else {
            console.error("Wallet fetch error:", err);
          }
        });
    }

    // ========== STATUS POLLING FUNCTIONS ==========

    function startStatusPolling() {
      if (!statusEndpoint) return;
      if (Object.keys(activeOrders).length === 0) return;

      if (!statusPollTimer) {
        pollOrderStatuses();
        statusPollTimer = setInterval(pollOrderStatuses, statusConfig.pollInterval);
        
        // Pause/resume when tab is hidden/visible
        document.addEventListener('visibilitychange', handleVisibilityChange, { passive: true });
      }
    }

    function stopStatusPolling() {
      if (statusPollTimer) {
        clearInterval(statusPollTimer);
        statusPollTimer = null;
      }
    }

    function stopStatusPollingIfIdle() {
      if (statusPollTimer && Object.keys(activeOrders).length === 0) {
        stopStatusPolling();
      }
    }

    function handleVisibilityChange() {
      if (document.visibilityState === 'hidden') {
        // Pause polling when tab is hidden
        if (statusPollTimer) {
          clearInterval(statusPollTimer);
          statusPollTimer = null;
        }
      } else if (document.visibilityState === 'visible' && Object.keys(activeOrders).length > 0) {
        // Resume polling when tab becomes visible
        if (!statusPollTimer) {
          startStatusPolling();
        }
      }
    }

    function registerOrderForRealtimeStatus(taskId, cardEl, status) {
      if (!taskId || !cardEl || !statusEndpoint) return;

      var normalizedStatus = (status || '').toLowerCase();
      var finalStatuses = ['completed', 'failed', 'error', 'already_downloaded', 'ready', 'timeout'];
      if (finalStatuses.indexOf(normalizedStatus) !== -1) {
        cardEl.removeAttribute('data-live-tracking');
        return;
      }

      var existing = activeOrders[taskId];
      if (existing) {
        existing.element = cardEl;
        existing.startedAt = existing.startedAt || Date.now();
      } else {
        activeOrders[taskId] = {
          startedAt: Date.now(),
          element: cardEl,
          notified: false
        };
      }

      cardEl.setAttribute('data-live-tracking', '1');
      startStatusPolling();
    }

    function pollOrderStatuses() {
      var taskIds = Object.keys(activeOrders);
      if (!taskIds.length || !statusEndpoint) {
        stopStatusPollingIfIdle();
        return;
      }

      // Drop orders that have been polling too long
      var now = Date.now();
      taskIds.forEach(function (id) {
        var info = activeOrders[id];
        if (!info) return;
        var minutes = (now - info.startedAt) / 60000;
        if (minutes > statusConfig.maxPollMinutes) {
          if (info.element) {
            info.element.removeAttribute('data-live-tracking');
          }
          delete activeOrders[id];
        }
      });

      taskIds = Object.keys(activeOrders);
      if (!taskIds.length) {
        stopStatusPollingIfIdle();
        return;
      }

      fetch(statusEndpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': restNonce || ''
        },
        body: JSON.stringify({ task_ids: taskIds })
      })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (!data || !data.success || !data.orders) return;

          Object.keys(data.orders).forEach(function (taskId) {
            var order = data.orders[taskId];
            var info = activeOrders[taskId];
            if (!info || !info.element) return;

            // Update UI from status
            updateResultElementFromStatus(info.element, order);

            // Attach download link if we have it
            if (order.download_link) {
              attachDownloadLinkToResult(info.element, order.download_link);
            } else if ((order.status === 'ready' || order.status === 'completed') && taskId) {
              // If status is "ready" OR "completed" but no download_link yet, ask backend to generate it
              generateDownloadLink(taskId, info.element, 0);
            }

            // Final states – toast + notification exactly once
            if (order.status === 'completed' || order.status === 'ready') {
              if (!info.notified) {
                info.notified = true;
                showStatusToast(order);
                triggerBrowserNotification(order);
              }
              delete activeOrders[taskId];
            }

            // Failed / error – stop tracking
            if (order.status === 'failed' || order.status === 'error') {
              delete activeOrders[taskId];
            }
          });

          stopStatusPollingIfIdle();
        })
        .catch(function (err) {
          console.error('Status polling failed', err);
        });
    }

    function updateResultElementFromStatus(el, order) {
      if (!el) return;

      var status = order.status || 'processing';
      // Normalize status (pending -> queued for consistency)
      if (status === 'pending') {
        status = 'queued';
      }

      // Update dataset.status attribute
      el.dataset.status = status;

      // Mark as live-tracked if this element is in activeOrders
      var taskIdFromEl = el.getAttribute('data-task-id') || (order.task_id || '');
      var isLive = false;
      if (taskIdFromEl && activeOrders[taskIdFromEl]) {
        isLive = true;
      } else {
        // Also check if this element is referenced in any activeOrders entry
        Object.keys(activeOrders).forEach(function(taskId) {
          if (activeOrders[taskId] && activeOrders[taskId].element === el) {
            isLive = true;
          }
        });
      }
      
      if (isLive) {
        el.setAttribute('data-live', '1');
      } else {
        el.removeAttribute('data-live');
      }

      // Class
      el.className = el.className.replace(/stock-order-result--\w+/g, '').trim();
      el.classList.add('stock-order-result--' + status);

      // Status label - use status_label from API if available, otherwise use getStatusLabel
      var statusEl = el.querySelector('[data-status]');
      if (statusEl) {
        var label = order.status_label || getStatusLabel(status);
        statusEl.textContent = label;
      }

      // Progress bar (very simple heuristic)
      var progressBar = el.querySelector('.stock-order-result-progress-bar');
      if (progressBar) {
        var pct = 10;
        if (status === 'queued' || status === 'pending') pct = 15;
        else if (status === 'processing') pct = 40;
        else if (status === 'ready') pct = 90;
        else if (status === 'completed') pct = 100;
        else if (status === 'failed' || status === 'error') pct = 100;

        progressBar.style.width = pct + '%';
        progressBar.setAttribute('data-progress', String(pct));
      }

      // Update message if provided
      var messageEl = el.querySelector('[data-message]');
      if (messageEl && order.message) {
        messageEl.textContent = order.message;
        messageEl.classList.add('is-visible');
      }
    }

    function attachDownloadLinkToResult(el, url) {
      var actions = el.querySelector('[data-actions]');
      if (!actions) return;

      // If there's already a download button, just update href
      var btn = actions.querySelector('.stock-order-download-btn');
      if (btn) {
        btn.href = url;
        btn.target = '_blank';
        btn.rel = 'noopener';
        return;
      }

      // Otherwise create one
      var a = document.createElement('a');
      a.href = url;
      a.target = '_blank';
      a.rel   = 'noopener';
      a.className = 'stock-order-result-link stock-order-download-btn';
      a.innerHTML =
        '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:inline-block;vertical-align:middle;margin-right:6px">' +
        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>' +
        '</svg>' +
        'Download now';

      actions.appendChild(a);
    }

    function showStatusToast(order) {
      if (!order) return;

      var site = order.site || 'stock site';
      var stockId = order.stock_id || '';
      var msg = 'Your ' + site;
      if (stockId) {
        msg += ' file ' + stockId;
      }
      msg += ' is ready.';

      var container = document.createElement('div');
      container.className = 'artly-toast artly-toast-success';
      container.textContent = msg;
      document.body.appendChild(container);

      requestAnimationFrame(function () {
        container.classList.add('is-visible');
      });

      setTimeout(function () {
        container.classList.remove('is-visible');
        setTimeout(function () {
          container.remove();
        }, 250);
      }, 5000);
    }

    function triggerBrowserNotification(order) {
      if (!('Notification' in window)) return;

      var message = 'Your Artly download is ready.';
      if (order && order.site && order.stock_id) {
        message = 'Your ' + order.site + ' file ' + order.stock_id + ' is ready to download.';
      }

      if (Notification.permission === 'granted') {
        new Notification('Artly download ready', { body: message });
      } else if (Notification.permission === 'default') {
        Notification.requestPermission().then(function (perm) {
          if (perm === 'granted') {
            new Notification('Artly download ready', { body: message });
          }
        });
      }
    }

    // ========== END STATUS POLLING FUNCTIONS ==========

    function submitStockOrder(payload, callback) {
      var callbackFn = typeof callback === "function" ? callback : null;
      if (!endpoint) {
        console.warn("Stock order endpoint not configured.");
        if (callbackFn) {
          callbackFn(false);
        }
        return;
      }

      var resultsEl = root.querySelector("[data-stock-order-results]");
      var resultsList = null;
      if (resultsEl) {
        resultsEl.innerHTML = "";
        resultsList = ensureResultsList(resultsEl);
      }

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
          if (res.status === 401) {
            throw new Error("unauthorized");
          }
          if (!res.ok) {
            throw new Error("http_error");
          }
          return res.json();
        })
        .then(function (data) {
          var orders = [];
          if (data) {
            if (Array.isArray(data.orders)) {
              orders = data.orders;
            } else if (Array.isArray(data.links)) {
              orders = data.links;
            }
          }

          if (!orders.length) {
            showError("Unexpected response. Please try again.");
            if (callbackFn) {
              callbackFn(false, data);
            }
            return;
          }

          orders.forEach(function (order) {
            var normalized = normalizeOrderForUI(order, null);
            var card = createOrderResultElement(normalized);

            if (resultsList) {
              resultsList.appendChild(card);
            }

            // For already_downloaded, never use cached download_url - force fresh link generation
            if (normalized.download_url && normalized.status !== "already_downloaded") {
              updateOrderResult(card, {
                status: normalized.status || "completed",
                message: normalized.message || "Ready to download",
                progress: 100,
                download_url: normalized.download_url
              });
              return;
            }

            if (normalized.status === "ready") {
              generateDownloadLink(normalized.task_id, card, 0);
              return;
            }

            if (normalized.status === "completed") {
              updateOrderResult(card, normalized);
              return;
            }
            
            // For already_downloaded, update the card but don't auto-generate link
            // User must click "Download now" button to get fresh link
            if (normalized.status === "already_downloaded") {
              updateOrderResult(card, normalized);
              return;
            }

            // Register order for real-time status polling using unified system
            if (normalized.task_id) {
              registerOrderForRealtimeStatus(normalized.task_id, card, normalized.status);
            }

            // Handle case where order has no task_id (shouldn't happen, but graceful fallback)
            if (!normalized.task_id && (normalized.status === "queued" || normalized.status === "pending")) {
              updateOrderResult(card, {
                status: "error",
                progress: 100,
                message: "We couldn't track this download. Please check your history."
              });
            }
          });

          var balance = typeof data.new_balance !== "undefined" ? data.new_balance : data.balance;
          if (typeof balance !== "undefined") {
            applyWalletBalance(balance);
          }
          fetchWalletBalance();

          if (callbackFn) {
            callbackFn(true, data);
          }
        })
        .catch(function (err) {
          console.error("Stock order error:", err);
          var friendlyMessage = "Failed to submit order. Please try again.";
          if (err && err.message === "unauthorized") {
            friendlyMessage = "Your session expired. Please refresh the page and log in again.";
          }
          showError(friendlyMessage);
          showNotification(friendlyMessage, "error");
          if (callbackFn) {
            callbackFn(false, err);
          }
        })
        .finally(function () {
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

    // ========== LEGACY POLLING CODE (NOT USED BY /stock-order/ PAGE) ==========
    // The following functions (pollTimer, activeOrderIds, pollStockOrderStatus, updateOrderProgressUI)
    // use a different polling system based on order.id (numeric) and a different endpoint format.
    // They are kept here for potential use by other pages, but /stock-order/ uses the unified
    // real-time polling system above (activeOrders, statusPollTimer, pollOrderStatuses).
    // ============================================================================

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

    function mapStatusToProgress(status, percentage) {
      switch (status) {
        case "pending":
          return 10;
        case "queued":
          return 10;
        case "processing":
          if (typeof percentage === "number" && percentage >= 0 && percentage <= 100) {
            return Math.max(10, Math.min(90, percentage));
          }
          return 50;
        case "completed":
        case "already_downloaded":
        case "ready":
          return 100;
        case "failed":
          return 100;
        default:
          return 40;
      }
    }

    function mapStatusToText(status) {
      switch (status) {
        case "pending":
          return "Queued…";
        case "queued":
          return "Queued…";
        case "processing":
          return "Processing";
        case "completed":
        case "ready":
          return "Completed";
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

      var percentage = order.percentage !== undefined ? order.percentage : null;
      var pct = mapStatusToProgress(order.status, percentage);
      var label = mapStatusToText(order.status);
      
      if (order.status === "processing" && percentage !== null) {
        label = percentage + "%";
      }

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

      var list = ensureResultsList(resultsEl);
      if (!list) return;

      var card = createOrderResultElement({
        url: "Stock order",
        status: "error",
        message: message,
        progress: 100
      });

      list.appendChild(card);
    }

    function showNotification(message, type) {
      if (!document || !document.body) return;
      var validTypes = ["success", "error", "warning", "info"];
      var notificationType = typeof type === "string" && validTypes.indexOf(type) !== -1 ? type : "info";
      var note = document.createElement("div");
      note.className = "artly-notification artly-notification-" + notificationType;
      note.textContent = message;

      var existing = document.querySelectorAll(".artly-notification").length;
      note.style.top = 24 + existing * 72 + "px";

      document.body.appendChild(note);

      window.requestAnimationFrame(function () {
        note.classList.add("is-visible");
      });

      window.setTimeout(function () {
        note.classList.remove("is-visible");
        window.setTimeout(function () {
          if (note && note.parentNode) {
            note.parentNode.removeChild(note);
          }
        }, 320);
      }, 4000);
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

    // Show inline preview card
    function showInlinePreview(preview) {
      var previewContainer = root.querySelector("[data-stock-order-preview]");
      if (!previewContainer) return;

      var siteLabel = preview.site_label || preview.site || "";
      var stockId = preview.stock_id || "";
      var costValue = preview.cost_points || 0;
      var thumbUrl = preview.preview_thumb || "";

      var title = siteLabel + " – " + stockId;
      var costText = "Cost: " + costValue + " point(s)";
      var confirmText = "Confirm order (" + costValue + " point" + (costValue !== 1 ? "s" : "") + ")";

      previewContainer.innerHTML =
        '<div class="stock-order-preview-card-inline">' +
        '<img src="' + escapeHtml(thumbUrl || "/wp-content/themes/artly/assets/img/placeholder.svg") + '" alt="Preview" />' +
        '<div class="stock-order-preview-info-inline">' +
        '<h4>' + escapeHtml(title) + "</h4>" +
        "<p>" + escapeHtml(costText) + "</p>" +
        '<button type="button" class="stock-order-preview-confirm-inline" data-preview-confirm-inline ' +
        (preview.enough_points ? "" : "disabled") + ">" +
        escapeHtml(confirmText) +
        "</button>" +
        "</div>" +
        "</div>";

      previewContainer.classList.add("is-visible");

      var confirmBtn = previewContainer.querySelector("[data-preview-confirm-inline]");
      if (confirmBtn) {
        confirmBtn.addEventListener("click", function () {
          if (confirmBtn.disabled) return;

          var url = singleInput ? singleInput.value.trim() : "";
          if (!url) return;

          var payload = {
            links: [
              {
                url: url,
                selected: true
              }
            ]
          };

          previewContainer.classList.remove("is-visible");
          previewContainer.innerHTML = "";
          submitStockOrder(payload);
        });
      }

      if (!preview.enough_points) {
        var infoEl = previewContainer.querySelector(".stock-order-preview-info-inline p");
        if (infoEl) {
          infoEl.textContent = costText + " – You don't have enough points to order this file.";
        }
      }
    }

    // Register any existing in-progress cards on initial load so they keep updating
    var existingCards = root.querySelectorAll('.stock-order-result[data-task-id]');
    existingCards.forEach(function (card) {
      var taskId = card.getAttribute('data-task-id');
      var status = (card.dataset.status || '').toLowerCase();
      if (taskId && (status === 'queued' || status === 'processing' || status === 'pending')) {
        registerOrderForRealtimeStatus(taskId, card, status);
      }
    });

    // Single mode submit WITH inline preview + confirm
    if (singleSubmit && singleInput) {
      singleSubmit.addEventListener("click", function () {
        var url = singleInput.value.trim();
        if (!url) return;

        fetchPreviewForUrl(url)
          .then(function (preview) {
            showInlinePreview(preview);
          })
          .catch(function (err) {
            if (err && err.message === "unauthorized") {
              showError(
                "Your session expired. Please refresh the page and log in again."
              );
            } else {
              showError("Failed to fetch preview. Please check the URL and try again.");
            }
          });
      });
    }

    // Batch mode submit handler is already defined earlier in the file (around line 1231)
    // using submitBatchBtn variable, so this duplicate code has been removed.

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

    // Register existing order cards on page load for real-time status tracking
    // This ensures that if the user refreshes the page while orders are in progress,
    // they still get live updates
    var existingCards = root.querySelectorAll('.stock-order-result[data-task-id]');
    if (existingCards.length > 0 && statusEndpoint) {
      existingCards.forEach(function (card) {
        var taskId = card.getAttribute('data-task-id');
        var status = (card.dataset.status || '').toLowerCase();
        
        // Register non-final statuses for polling
        if (taskId && (status === 'queued' || status === 'processing' || status === 'pending')) {
          registerOrderForRealtimeStatus(taskId, card, status);
        }
      });
      
      // Start polling if we registered any orders
      if (Object.keys(activeOrders).length > 0) {
        startStatusPolling();
      }
    }
  });
})();