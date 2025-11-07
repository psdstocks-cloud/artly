(function () {
  document.addEventListener("DOMContentLoaded", function () {
    var root = document.querySelector(".artly-stock-order-page");
    if (!root) return;

    var config = window.artlyStockOrder || {};
    var sitesConfig = config.sites || {};
    var endpoint = config.endpoint || "";
    var previewEndpoint = config.previewEndpoint || "";
    var restNonce = config.restNonce || "";

    var pollingConfig = {
      interval: 2000,
      maxAttempts: 150,
      downloadRetries: 3,
      retryDelay: 1000
    };

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

      var downloadUrl = order.download_url || order.downloadLink || order.download_link || "";
      if (!downloadUrl && resultEl) {
        var existingBtn = resultEl.querySelector(".stock-order-download-btn");
        if (existingBtn) {
          downloadUrl = existingBtn.getAttribute("href") || "";
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
      var status = normalized.status || "queued";

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
        if (normalized.download_url) {
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
          status === "already_downloaded" ||
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

    function shouldPollOrder(order) {
      if (!order || !order.task_id) return false;
      var status = (order.status || "").toLowerCase();
      return status === "queued" || status === "processing" || status === "pending";
    }

    function pollOrderStatus(taskId, resultEl, attempt) {
      if (!taskId || !resultEl) return;
      attempt = attempt || 0;

      if (attempt >= pollingConfig.maxAttempts) {
        updateOrderResult(resultEl, {
          status: "timeout",
          progress: 100,
          message: "Order took too long to process. Please check your downloads page."
        });
        return;
      }

      var progressPercent = Math.min(90, Math.floor((attempt / pollingConfig.maxAttempts) * 90));
      updateOrderResult(resultEl, { progress: progressPercent });

      fetch("/wp-json/artly/v1/stock-orders/" + encodeURIComponent(taskId) + "/status", {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": restNonce
        },
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
            throw new Error("http_error");
          }

          return res.json();
        })
        .then(function (data) {
          if (!data) {
            throw new Error("invalid_response");
          }

          var status = (data.status || "").toLowerCase();
          var message = data.message || "";

          if (data.success === false && status === "not_found") {
            updateOrderResult(resultEl, {
              status: "error",
              progress: 100,
              message: message || "We couldn't find this download. Please check your history."
            });
            return;
          }

          if (status === "ready" || status === "completed") {
            updateOrderResult(resultEl, {
              status: status === "ready" ? "ready" : "completed",
              progress: 95,
              message: message || "Preparing download link..."
            });
            generateDownloadLink(taskId, resultEl, 0);
            return;
          }

          if (status === "failed" || status === "error") {
            updateOrderResult(resultEl, {
              status: "failed",
              progress: 100,
              message: message || "Order failed. Please try again."
            });
            return;
          }

          updateOrderResult(resultEl, {
            status: status || "processing",
            progress: progressPercent,
            message: message
          });

          window.setTimeout(function () {
            pollOrderStatus(taskId, resultEl, attempt + 1);
          }, pollingConfig.interval);
        })
        .catch(function (error) {
          if (error && error.message === "unauthorized") {
            return;
          }

          window.setTimeout(function () {
            pollOrderStatus(taskId, resultEl, attempt + 1);
          }, pollingConfig.interval);
        });
    }

    function generateDownloadLink(taskId, resultEl, retryCount) {
      if (!taskId || !resultEl) return;
      retryCount = retryCount || 0;

      var prepMessage = retryCount ? "Finalizing download..." : "Preparing download link...";
      var progress = 90 + Math.min(5, retryCount * 3);

      updateOrderResult(resultEl, {
        status: "processing",
        progress: progress,
        message: prepMessage
      });

      fetch("/wp-json/artly/v1/stock-orders/" + encodeURIComponent(taskId) + "/download", {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": restNonce
        },
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
            throw new Error("http_error");
          }

          return res.json();
        })
        .then(function (data) {
          if (data && data.download_url) {
            updateOrderResult(resultEl, {
              status: (data.status || "completed").toLowerCase(),
              progress: 100,
              message: data.message || "Ready to download",
              download_url: data.download_url
            });
            return;
          }

          if (retryCount < pollingConfig.downloadRetries) {
            window.setTimeout(function () {
              generateDownloadLink(taskId, resultEl, retryCount + 1);
            }, pollingConfig.retryDelay);
            return;
          }

          updateOrderResult(resultEl, {
            status: "error",
            progress: 100,
            message:
              (data && data.message) ||
              "Could not generate download link. Please try again from your downloads page."
          });
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

          updateOrderResult(resultEl, {
            status: "error",
            progress: 100,
            message: "Could not generate download link. Please try again from your downloads page."
          });
        });
    }

    function applyWalletBalance(balance) {
      if (typeof window.updateWalletDisplay === "function") {
        window.updateWalletDisplay(balance);
      } else if (typeof window.artlyUpdateWalletBalance === "function") {
        window.artlyUpdateWalletBalance(balance);
      }

      var balanceEls = document.querySelectorAll("[data-artly-wallet-balance]");
      balanceEls.forEach(function (el) {
        el.textContent = balance;
      });
    }

    function submitStockOrder(payload) {
      if (!endpoint) {
        console.warn("Stock order endpoint not configured.");
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
            return;
          }

          orders.forEach(function (order) {
            var normalized = normalizeOrderForUI(order, null);
            var card = createOrderResultElement(normalized);

            if (resultsList) {
              resultsList.appendChild(card);
            }

            if (normalized.download_url) {
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

            if (normalized.status === "completed" || normalized.status === "already_downloaded") {
              updateOrderResult(card, normalized);
              return;
            }

            if (shouldPollOrder(normalized)) {
              pollOrderStatus(normalized.task_id, card, 0);
              return;
            }

            if (!normalized.task_id && normalized.status === "queued") {
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
        })
        .catch(function (err) {
          console.error("Stock order error:", err);
          if (err && err.message === "unauthorized") {
            showError("Your session expired. Please refresh the page and log in again.");
          } else {
            showError("Failed to submit order. Please try again.");
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

