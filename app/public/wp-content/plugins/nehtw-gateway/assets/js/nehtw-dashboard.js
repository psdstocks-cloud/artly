/* global nehtwDashboardSettings, wp */

/**
 * Front-end React dashboard for stock downloads.
 * - Single URL mode (calls /nehtw/v1/stock-order)
 * - Uses the same idExtractor logic you have in the admin prototype.
 */
(function (wp, settings) {
    if (!wp || !wp.element || !wp.apiFetch) {
        // Bail if WordPress JS libs not present.
        return;
    }

    const { createElement: h, useState, useEffect } = wp.element;
    const { render } = wp.element;
    const apiFetch = wp.apiFetch;

    /**
     * ========= URL → { source, id, url } extractor =========
     * Paste your *full* idExtractor implementation here.
     * (Same one you used in the admin React test.)
     *
     * IMPORTANT:
     *  - It must return false if no match.
     *  - Or: { source: 'shutterstock', id: '123456', url: 'https://...' }
     */

    const idExtractor = function (str) {
        const sourceMatch = [
            {
                match: /shutterstock.com(|\/[a-z]*)\/video\/clip-([0-9]*)/,
                result: string => {
                    var stockSource = 'vshutter';
                    var stockId = string[2];
                    return { source: stockSource, id: stockId, url: str };
                }
            },
            {
                match: /shutterstock.com(.*)music\/(.*)track-([0-9]*)-/,
                result: string => {
                    var stockSource = 'mshutter';
                    var stockId = string[3];
                    return { source: stockSource, id: stockId, url: str };
                }
            },
            {
                match: /shutterstock\.com\/(.*)(image-vector|image-photo|image-illustration|image|image-generated|editorial)\/([0-9a-zA-Z-_]*)-([0-9a-z]*)/,
                result: string => {
                    var stockSource = 'shutterstock';
                    var stockId = string[4];
                    return { source: stockSource, id: stockId, url: str };
                }
            },
            {
                match: /shutterstock\.com\/(.*)(image-vector|image-photo|image-illustration|image-generated|editorial)\/([0-9a-z]*)/,
                result: string => {
                    var stockSource = 'shutterstock';
                    var stockId = string[3];
                    return { source: stockSource, id: stockId, url: str };
                }
            },
            {
                match: /stock\.adobe.com\/(..\/||.....\/)(images|templates|3d-assets|stock-photo|video)\/([a-zA-Z0-9-%.,]*)\/([0-9]*)/,
                result: string => {
                    var stockSource = 'adobestock';
                    var stockId = string[4];
                    return { source: stockSource, id: stockId, url: str };
                }
            },
            {
                match: /stock\.adobe.com(.*)asset_id=([0-9]*)/,
                result: string => {
                    var stockSource = 'adobestock';
                    var stockId = string[2];
                    return { source: stockSource, id: stockId, url: str };
                }
            },
            {
                match: /stock\.adobe.com\/(.*)search\/audio\?(k|keywords)=([0-9]*)/,
                result: string => {
                    var stockSource = 'adobestock';
                    var stockId = string[3];
                    return { source: stockSource, id: stockId, url: str };
                }
            },
            {
                match: /stock\.adobe\.com\/(..\/||.....\/)([0-9]*)/,
                result: string => {
                    var stockSource = 'adobestock';
                    var stockId = string[2];
                    return { source: stockSource, id: stockId, url: str };
                }
            },
            {
                match: /depositphotos\.com(.*)depositphotos_([0-9]*)(.*)\.jpg/,
                result: string => {
                    var stockSource = 'depositphotos';
                    var stockId = string[2];
                    return { source: stockSource, id: stockId, url: str };
                }
            },
            {
                match: /depositphotos\.com\/([0-9]*)\/stock-video(.*)/,
                result: string => {
                    var stockSource = 'depositphotos_video';
                    var stockId = string[1];
                    return { source: stockSource, id: stockId, url: str };
                }
            },
            {
                match: /depositphotos\.com\/([0-9]*)\/(stock-photo|stock-illustration|free-stock)(.*)/,
                result: string => {
                    var stockSource = 'depositphotos';
                    var stockId = string[1];
                    return { source: stockSource, id: stockId, url: str };
                }
            },
            {
                match: /depositphotos.com(.*)qview=([0-9]*)/,
                result: string => {
                    var stockSource = 'depositphotos';
                    var stockId = string[2];
                    return { source: stockSource, id: stockId, url: str };
                }
            },
            {
                match: /depositphotos.com(.*)\/(photo|editorial|vector|illustration)\/([0-9a-z-]*)-([0-9]*)/,
                result: string => {
                    var stockSource = 'depositphotos';
                    var stockId = string[4];
                    return { source: stockSource, id: stockId, url: str };
                }
            },
            {
                match: /123rf\.com\/(photo|free-photo)_([0-9]*)_/,
                result: string => {
                    var stockSource = '123rf';
                    var stockId = string[2];
                    return { source: stockSource, id: stockId, url: str };
                }
            },
            {
                match: /123rf\.com\/(.*)mediapopup=([0-9]*)/,
                result: string => {
                    var stockSource = '123rf';
                    var stockId = string[2];
                    return { source: stockSource, id: stockId, url: str };
                }
            },
            {
                match: /123rf\.com\/stock-photo\/([0-9]*).html/,
                result: string => {
                    var stockSource = '123rf';
                    var stockId = string[1];
                    return { source: stockSource, id: stockId, url: str };
                }
            },
            {
                match: /istockphoto\.com\/(.*)gm([0-9A-Z_]*)-/,
                result: string => {
                    var stockSource = 'istockphoto';
                    var stockId = string[2];
                    return { source: stockSource, id: stockId, url: str };
                }
            },
            {
                match: /gettyimages\.com\/(.*)\/([0-9]*)/,
                result: string => {
                    var stockSource = 'istockphoto';
                    var stockId = string[2];
                    return { source: stockSource, id: stockId, url: str };
                }
            },
            {
                match: /dreamstime(.*)-image([0-9]*)/,
                result: string => {
                    var stockSource = 'dreamstime';
                    var stockId = string[2];
                    return { source: stockSource, id: stockId, url: str };
                }
            },
            {
                match: /pngtree\.com(.*)_([0-9]*).html/,
                result: string => {
                    var stockSource = 'pngtree';
                    var stockId = string[2];
                    return { source: stockSource, id: stockId, url: str };
                }
            },
            {
                match: /vectorstock.com\/([0-9a-zA-Z-]*)\/([0-9a-zA-Z-]*)-([0-9]*)/,
                result: string => {
                    var stockSource = 'vectorstock';
                    var stockId = string[3];
                    return { source: stockSource, id: stockId, url: str };
                }
            },
            // ... keep the rest of your mappings here ...
        ];

        let item = Object.assign([], sourceMatch).filter(item => str.match(item.match));
        if (item.length < 1) return false;
        item = item.shift();
        let match = str.match(item.match);
        return item.result(match);
    };

    /**
     * ================= MAIN APP =================
     */
    function NehtwDashboard() {
        const [mode, setMode] = useState('single'); // 'single' | 'batch' (batch coming later)
        const [url, setUrl] = useState('');
        const [loading, setLoading] = useState(false);
        const [error, setError] = useState('');
        const [flash, setFlash] = useState('');
        const [balance, setBalance] = useState(settings.balance || 0);
        const [lastOrder, setLastOrder] = useState(null);
        const [transactions] = useState(settings.transactions || []);
        const [orders, setOrders] = useState([]);
        const [ordersLoading, setOrdersLoading] = useState(false);
        const [ordersError, setOrdersError] = useState('');
        const [downloadingTaskId, setDownloadingTaskId] = useState('');
        const [redownloadError, setRedownloadError] = useState('');
        const [ordersRefreshToken, setOrdersRefreshToken] = useState(0);

        // Auto-clear flash message after a few seconds.
        useEffect(() => {
            if (!flash) return;
            const t = setTimeout(() => setFlash(''), 4000);
            return () => clearTimeout(t);
        }, [flash]);

        useEffect(() => {
            let isMounted = true;

            const loadOrders = async () => {
                setOrdersLoading(true);
                setOrdersError('');

                try {
                    const response = await apiFetch({
                        path: '/nehtw/v1/orders?per_page=20',
                        method: 'GET',
                    });

                    if (!isMounted) {
                        return;
                    }

                    const list = response && Array.isArray(response.data) ? response.data : [];
                    setOrders(list);
                } catch (err) {
                    if (!isMounted) {
                        return;
                    }
                    const msg = (err && err.message) || 'Could not load your downloads. Please try again later.';
                    setOrdersError(msg);
                } finally {
                    if (isMounted) {
                        setOrdersLoading(false);
                    }
                }
            };

            loadOrders();

            return () => {
                isMounted = false;
            };
        }, [ordersRefreshToken]);

        const handleSubmitSingle = async (event) => {
            event.preventDefault();
            setError('');
            setFlash('');
            setLastOrder(null);

            const trimmed = url.trim();
            if (!trimmed) {
                setError('Please paste a stock URL first.');
                return;
            }

            const info = idExtractor(trimmed);
            if (!info || !info.source || !info.id) {
                setError('Could not detect provider / ID from this URL. Maybe this site is not supported yet.');
                return;
            }

            setLoading(true);

            try {
                const cost = settings.defaultCostPoints || 5;

                const response = await apiFetch({
                    path: '/nehtw/v1/stock-order',
                    method: 'POST',
                    data: {
                        site: info.source,
                        stock_id: info.id,
                        source_url: info.url,
                        cost_points: cost,
                    },
                });

                // Expecting something like:
                // { success:true, task_id:'...', new_balance: 80, order: {...}, message:'...' }
                if (response && typeof response.new_balance !== 'undefined') {
                    setBalance(response.new_balance);
                }

                setLastOrder({
                    extractor: info,
                    api: response,
                });

                if (response && response.already_owned) {
                    setFlash('You downloaded this before — here’s your fresh link (no points deducted).');
                } else {
                    setFlash(response && response.message
                        ? response.message
                        : 'Order created successfully.');
                }
                setOrdersRefreshToken((token) => token + 1);
                setUrl('');
            } catch (err) {
                const msg = (err && err.message) || 'Unexpected error while calling the Nehtw REST endpoint.';
                setError(msg);
            } finally {
                setLoading(false);
            }
        };

        /**
         * For now, batch mode is just disabled with a message.
         * We’ll implement it in the next step.
         */
        const handleSubmitBatch = (event) => {
            event.preventDefault();
            setError('Batch mode is not implemented yet – we will add up to 5 URLs with previews in the next step.');
        };

        const onSubmit = mode === 'single' ? handleSubmitSingle : handleSubmitBatch;

        const handleRedownload = async (order) => {
            if (!order || !order.task_id || !order.can_redownload_free) {
                return;
            }

            setRedownloadError('');
            setDownloadingTaskId(order.task_id);

            try {
                const response = await apiFetch({
                    path: `/nehtw/v1/orders/${encodeURIComponent(order.task_id)}/redownload`,
                    method: 'POST',
                });

                if (response && response.order) {
                    setOrders((prev) => {
                        if (!Array.isArray(prev)) {
                            return response.order ? [response.order] : prev;
                        }

                        const next = prev.slice();
                        const index = next.findIndex((item) => item && item.task_id === order.task_id);

                        if (index === -1 && response.order) {
                            return [response.order].concat(next);
                        }

                        if (index > -1) {
                            next[index] = response.order;
                            return next;
                        }

                        return prev;
                    });
                }

                if (response && response.download_link) {
                    window.location.href = response.download_link;
                } else {
                    setRedownloadError('Download link not available yet. Please try again in a moment.');
                }
            } catch (err) {
                const msg = (err && err.message) || 'Failed to refresh the download link. Please try again.';
                setRedownloadError(msg);
            } finally {
                setDownloadingTaskId('');
            }
        };

        return h(
            'div',
            { className: 'nehtw-dashboard-shell' },
            h(
                'div',
                { className: 'nehtw-dashboard-card' },
                // Top row: header + language + theme + buy credits
                h(
                    'div',
                    { className: 'nehtw-dashboard-header-row' },
                h(
                    'div',
                    { className: 'nehtw-dashboard-app-title' },
                    h('div', { className: 'nehtw-dashboard-badge-dot' }),
                    h(
                        'div',
                        null,
                        h('div', { className: 'nehtw-dashboard-app-label' }, 'NEHTW STUDIO'),
                        h('div', { className: 'nehtw-dashboard-app-name' }, 'Stock & AI'),
                        h('div', { className: 'nehtw-dashboard-app-name' }, 'Downloads')
                    )
                ),
                h(
                    'div',
                    { className: 'nehtw-dashboard-header-controls' },
                    h(
                        'div',
                        { className: 'nehtw-dashboard-lang-switch' },
                        h('button', { type: 'button', className: 'nehtw-chip nehtw-chip-on' }, 'العربية'),
                        h('button', { type: 'button', className: 'nehtw-chip' }, 'EN')
                    ),
                    h(
                        'button',
                        { type: 'button', className: 'nehtw-chip nehtw-chip-ghost' },
                        'Dark'
                    ),
                    h(
                        'a',
                        {
                            href: settings.pricingUrl || '#',
                            className: 'nehtw-cta',
                        },
                        'Buy credits / شراء نقاط'
                    )
                )
            ),

            // Main grid
            h(
                'div',
                { className: 'nehtw-dashboard-grid' },

                // Wallet
                h(
                    'section',
                    { className: 'nehtw-panel nehtw-panel-balance' },
                    h('h3', null, 'WALLET BALANCE'),
                    h(
                        'div',
                        { className: 'nehtw-balance-row' },
                        h('div', { className: 'nehtw-balance-amount' }, String(balance)),
                        h(
                            'div',
                            { className: 'nehtw-balance-meta' },
                            h(
                                'div',
                                { className: 'nehtw-balance-meta-line' },
                                '1 point ≈ 20 EGP · subscriptions & pay-as-you-go'
                            ),
                            h(
                                'div',
                                { className: 'nehtw-balance-meta-line' },
                                'Hello, ',
                                settings.user && settings.user.displayName
                                ? settings.user.displayName
                                : 'artist'

                            )
                        )
                    )
                ),

                // Transaction History
                h(
                    'section',
                    { className: 'nehtw-panel nehtw-panel-transactions' },
                    h('h3', null, 'Recent Wallet Activity'),
                    transactions.length > 0
                        ? h(
                              'ul',
                              { className: 'nehtw-transactions-list' },
                              transactions.map((tx) => {
                                  const txType = tx.type === 'topup_woocommerce' 
                                      ? 'Wallet Top-up' 
                                      : tx.type === 'admin_adjust' 
                                      ? 'Admin Adjustment'
                                      : tx.type === 'bonus_coupon'
                                      ? 'Bonus Points'
                                      : tx.type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                                  
                                  const txMeta = tx.meta || {};
                                  const orderId = txMeta.order_id;
                                  const orderLink = orderId 
                                      ? settings.homeUrl + '/my-account/view-order/' + orderId + '/'
                                      : null;
                                  
                                  const txDate = new Date(tx.created_at);
                                  const formattedDate = txDate.toLocaleDateString('en-US', {
                                      month: 'short',
                                      day: 'numeric',
                                      year: 'numeric',
                                      hour: '2-digit',
                                      minute: '2-digit'
                                  });
                                  
                                  return h(
                                      'li',
                                      { key: tx.id, className: 'nehtw-transaction-item' },
                                      h(
                                          'div',
                                          { className: 'nehtw-transaction-main' },
                                          h(
                                              'span',
                                              { className: 'nehtw-transaction-type' },
                                              txType
                                          ),
                                          h(
                                              'span',
                                              { className: 'nehtw-transaction-points' },
                                              '+' + tx.points + ' pts'
                                          )
                                      ),
                                      h(
                                          'div',
                                          { className: 'nehtw-transaction-meta' },
                                          h(
                                              'span',
                                              { className: 'nehtw-transaction-date' },
                                              formattedDate
                                          ),
                                          orderLink && h(
                                              'a',
                                              {
                                                  href: orderLink,
                                                  className: 'nehtw-transaction-order-link',
                                                  target: '_blank',
                                                  rel: 'noopener noreferrer'
                                              },
                                              'View Order'
                                          )
                                      )
                                  );
                              })
                          )
                        : h(
                              'p',
                              { className: 'nehtw-transactions-empty' },
                              "You don't have any transactions yet."
                          )
                ),

                // Smart downloader
                h(
                    'section',
                    { className: 'nehtw-panel nehtw-panel-downloader' },
                    h('h3', null, 'Smart downloader'),
                    h(
                        'p',
                        { className: 'nehtw-panel-sub' },
                        'Paste a stock URL. We detect the site & ID automatically, then call Nehtw.'
                    ),

                    // Mode toggle
                    h(
                        'div',
                        { className: 'nehtw-segmented' },
                        h(
                            'button',
                            {
                                type: 'button',
                                className:
                                    'nehtw-segment ' +
                                    (mode === 'single' ? 'nehtw-segment-on' : ''),
                                onClick: () => setMode('single'),
                            },
                            'Single link'
                        ),
                        h(
                            'button',
                            {
                                type: 'button',
                                className:
                                    'nehtw-segment ' +
                                    (mode === 'batch' ? 'nehtw-segment-on' : ''),
                                onClick: () => setMode('batch'),
                            },
                            'Batch (up to 5)'
                        )
                    ),

                    h(
                        'form',
                        { onSubmit },
                        h(
                            'label',
                            { htmlFor: 'nehtw-stock-url', className: 'nehtw-field-label' },
                            mode === 'single'
                                ? 'Paste stock URL'
                                : 'Batch mode (coming soon)'
                        ),
                        h('input', {
                            id: 'nehtw-stock-url',
                            type: 'url',
                            className: 'nehtw-input',
                            value: url,
                            onChange: (e) => setUrl(e.target.value),
                            placeholder:
                                'https://www.shutterstock.com/... or https://stock.adobe.com/...',
                            disabled: loading || mode === 'batch',
                        }),
                        h(
                            'button',
                            {
                                type: 'submit',
                                className: 'nehtw-button-primary',
                                disabled: loading,
                            },
                            loading ? 'Generating…' : 'Generate download links'
                        )
                    ),

                    error &&
                        h(
                            'div',
                            { className: 'nehtw-alert nehtw-alert-error' },
                            error
                        ),
                    flash &&
                        h(
                            'div',
                            { className: 'nehtw-alert nehtw-alert-success' },
                            flash
                        ),
                    lastOrder &&
                        h(
                            'div',
                            { className: 'nehtw-last-order' },
                            h('p', null, 'Last order:'),
                            h(
                                'p',
                                null,
                                'Detected ',
                                h('strong', null, lastOrder.extractor.source),
                                ' with ID ',
                                h('strong', null, lastOrder.extractor.id)
                            ),
                            lastOrder.api && lastOrder.api.task_id &&
                                h(
                                    'p',
                                    null,
                                    'Task ID: ',
                                    h('code', null, lastOrder.api.task_id)
                                ),
                            lastOrder.api && lastOrder.api.already_owned &&
                                h(
                                    'p',
                                    { className: 'nehtw-badge-owned' },
                                    'Already purchased — free re-download'
                                )
                        )
                ),

                // Download history (still placeholder – will be powered by REST later)
                h(
                    'section',
                    { className: 'nehtw-panel' },
                    h('h3', null, 'Download history'),
                    ordersLoading
                        ? h(
                              'p',
                              { className: 'nehtw-panel-sub' },
                              'Loading your downloads…'
                          )
                        : ordersError
                        ? h(
                              'div',
                              { className: 'nehtw-alert nehtw-alert-error' },
                              ordersError
                          )
                        : orders && orders.length
                        ? h(
                              'div',
                              { className: 'nehtw-orders-list' },
                              orders.map((order, index) => {
                                  const key = order.task_id || order.id || `order-${index}`;
                                  const provider = order.site ? String(order.site).toUpperCase() : 'STOCK';
                                  const title = order.file_name || order.stock_id || 'Download';

                                  let createdAtLabel = '';
                                  if (order && order.created_at) {
                                      const rawDate = typeof order.created_at === 'string'
                                          ? order.created_at.replace(' ', 'T')
                                          : order.created_at;
                                      const parsedDate = new Date(rawDate);
                                      if (!isNaN(parsedDate.getTime())) {
                                          createdAtLabel = parsedDate.toLocaleString();
                                      }
                                  }

                                  const statusLabel = order.status ? String(order.status) : 'pending';
                                  const isDownloading = downloadingTaskId === order.task_id;
                                  const canDownload = !!order.can_redownload_free && !isDownloading;
                                  const buttonLabel = isDownloading
                                      ? 'Preparing download…'
                                      : order.can_redownload_free
                                      ? 'Download again'
                                      : 'Unavailable';

                                  return h(
                                      'div',
                                      { key, className: 'nehtw-order-card' },
                                      order.preview_thumb &&
                                          h(
                                              'div',
                                              { className: 'nehtw-order-thumb' },
                                              h('img', {
                                                  src: order.preview_thumb,
                                                  alt: title,
                                                  loading: 'lazy',
                                              })
                                          ),
                                      h(
                                          'div',
                                          { className: 'nehtw-order-details' },
                                          h('div', { className: 'nehtw-order-provider' }, provider),
                                          h('div', { className: 'nehtw-order-title' }, title),
                                          createdAtLabel &&
                                              h(
                                                  'div',
                                                  { className: 'nehtw-order-date' },
                                                  createdAtLabel
                                              ),
                                          h('span', { className: 'nehtw-order-status' }, statusLabel)
                                      ),
                                      h(
                                          'div',
                                          { className: 'nehtw-order-actions' },
                                          h(
                                              'button',
                                              {
                                                  type: 'button',
                                                  className: 'nehtw-button-primary nehtw-order-download-button',
                                                  disabled: !canDownload,
                                                  onClick: canDownload ? () => handleRedownload(order) : undefined,
                                              },
                                              buttonLabel
                                          )
                                      )
                                  );
                              })
                          )
                        : h(
                              'p',
                              { className: 'nehtw-panel-sub' },
                              'You don’t have any downloads yet. Once you place orders, they will appear here with free re-downloads.'
                          ),
                    redownloadError &&
                        h(
                            'div',
                            { className: 'nehtw-alert nehtw-alert-error nehtw-orders-inline-error' },
                            redownloadError
                        )
                ),

                // Need help panel
                h(
                    'section',
                    { className: 'nehtw-panel' },
                    h('h3', null, 'Need help?'),
                    h(
                        'p',
                        { className: 'nehtw-panel-sub' },
                        'If a download fails or looks wrong, you’ll be able to open a ticket directly from this page. For now, contact us via the admin panel.'
                    )
                )
            )
            )
        );
    }

    function mount() {
        const container = document.getElementById('nehtw-gateway-dashboard-root');
        if (!container) return;
    
        render(h(NehtwDashboard), container);
    }
    
    if (
        document.readyState === 'complete' ||
        document.readyState === 'interactive'
    ) {
        mount();
    } else {
        document.addEventListener('DOMContentLoaded', mount);
    }
    })(window.wp || {}, window.nehtwDashboardSettings || {});
    
