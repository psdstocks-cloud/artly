/* global nehtwGatewaySettings, wp */
(function (wp, settings) {
    const { createElement: h, useState } = wp.element;
    const { render } = wp.element;
    const apiFetch = wp.apiFetch;

    // Simple helper used by some rules in the extractor.
    function idMapping(source, arr) {
        // For our purposes we just join the parts with a dash.
        // This matches what the backend expects.
        return arr.join("-");
    }

    /**
     * ====== URL → { source, id, url } EXTRACTOR ======
     * This is your big pattern list from the docs.
     */
    const idExtractor = function (str) {
        const sourceMatch = [
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

    /**
     * ========== React component ==========
     */
    function StockOrderApp() {
        const [url, setUrl] = useState("");
        const [loading, setLoading] = useState(false);
        const [error, setError] = useState("");
        const [result, setResult] = useState(null);

        async function onSubmit(event) {
            event.preventDefault();
            setError("");
            setResult(null);

            const trimmed = url.trim();
            if (!trimmed) {
                setError("Please paste a stock URL first.");
                return;
            }

            const info = idExtractor(trimmed);
            if (!info || !info.source || !info.id) {
                setError("Could not detect provider / ID from this URL. Maybe the site is not supported yet.");
                return;
            }

            setLoading(true);
            try {
                const response = await apiFetch({
                    path: "/nehtw/v1/stock-order",
                    method: "POST",
                    data: {
                        site: info.source,
                        stock_id: info.id,
                        source_url: info.url,
                        cost_points: settings.defaultCostPoints || 5,
                    },
                });

                setResult({
                    type: "success",
                    payload: response,
                    extractor: info,
                });
                setUrl("");
            } catch (err) {
                const message =
                    (err && err.message) ||
                    "Unexpected error while calling the Nehtw REST endpoint.";
                setError(message);
            } finally {
                setLoading(false);
            }
        }

        return h(
            "div",
            { className: "nehtw-react-box" },
            h(
                "form",
                { onSubmit: onSubmit },
                h(
                    "label",
                    { htmlFor: "nehtw-stock-url" },
                    "Paste stock URL:"
                ),
                h("br"),
                h("input", {
                    id: "nehtw-stock-url",
                    type: "url",
                    value: url,
                    onChange: function (e) {
                        setUrl(e.target.value);
                    },
                    placeholder:
                        "https://www.shutterstock.com/... or https://stock.adobe.com/...",
                    style: {
                        width: "100%",
                        maxWidth: "600px",
                        marginTop: "4px",
                        marginBottom: "8px",
                    },
                }),
                h("br"),
                h(
                    "button",
                    {
                        type: "submit",
                        className: "button button-primary",
                        disabled: loading,
                    },
                    loading ? "Placing order…" : "Place order from this URL"
                )
            ),
            error &&
                h(
                    "div",
                    {
                        style: {
                            marginTop: "10px",
                            padding: "8px",
                            background: "#fbeaea",
                            border: "1px solid #dc3232",
                        },
                    },
                    error
                ),
            result &&
                h(
                    "div",
                    {
                        style: {
                            marginTop: "10px",
                            padding: "8px",
                            background: "#e5f5e0",
                            border: "1px solid #46b450",
                        },
                    },
                    h("p", null, "Order created successfully."),
                    result.extractor &&
                        h(
                            "p",
                            null,
                            "Detected ",
                            h("strong", null, result.extractor.source),
                            " with ID ",
                            h("strong", null, result.extractor.id)
                        ),
                    result.payload &&
                        h(
                            "p",
                            null,
                            "Task ID: ",
                            h("strong", null, result.payload.task_id || "(none)")
                        ),
                    result.payload &&
                        h(
                            "p",
                            null,
                            "New balance: ",
                            h("strong", null, String(result.payload.new_balance))
                        )
                )
        );
    }

    function mount() {
        const container = document.getElementById("nehtw-gateway-react-app");
        if (!container) return;
        render(h(StockOrderApp), container);
    }

    if (
        document.readyState === "complete" ||
        document.readyState === "interactive"
    ) {
        mount();
    } else {
        document.addEventListener("DOMContentLoaded", mount);
    }
})(window.wp || {}, window.nehtwGatewaySettings || {});
