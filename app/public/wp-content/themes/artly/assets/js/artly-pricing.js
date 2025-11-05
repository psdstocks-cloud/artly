document.addEventListener('DOMContentLoaded', () => {
    // Tab switching functionality
    const tabs = document.querySelectorAll('.pricing-tab');
    const tabPanels = document.querySelectorAll('.pricing-tab-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const targetTab = tab.dataset.tab;

            // Update tab buttons
            tabs.forEach(t => {
                t.classList.remove('active');
                t.setAttribute('aria-selected', 'false');
            });
            tab.classList.add('active');
            tab.setAttribute('aria-selected', 'true');

            // Update tab panels
            tabPanels.forEach(panel => {
                panel.classList.remove('active');
            });
            let targetPanel;
            if (targetTab === 'dynamic') {
                targetPanel = document.getElementById('dynamic-pricing-panel');
            } else {
                targetPanel = document.getElementById(`${targetTab}-panel`);
            }
            if (targetPanel) {
                targetPanel.classList.add('active');
            }
        });
    });

    // Dynamic pricing calculator
    const range = document.getElementById('pointsRange');
    const input = document.getElementById('pointsInput');

    const pricePerPointEl = document.getElementById('pricePerPoint');
    const monthlyTotalEl = document.getElementById('monthlyTotal');
    const youSaveEl = document.getElementById('youSave');
    const badgeEl = document.getElementById('tierBadge');
    const taglineEl = document.getElementById('tierTagline');
    const ctaPointsEl = document.getElementById('ctaPoints');

    if (!range || !input) return;

    const MIN_POINTS = 1;
    const MAX_POINTS = 500;
    const BASE_PRICE = 20; // base EGP/point used for "You save"

    function clampPoints(n) {
        if (!Number.isFinite(n)) return 120;
        return Math.min(MAX_POINTS, Math.max(MIN_POINTS, Math.round(n)));
    }

    // Your pricing tiers in EGP
    function getPricePerPoint(points) {
        if (points <= 5)   return 20;
        if (points <= 10)  return 17;
        if (points <= 30)  return 15;
        if (points <= 70)  return 14;
        if (points <= 100) return 13;
        if (points <= 250) return 11;
        return 9.5; // 251–500
    }

    // Just for nice copy on the right-hand card
    function getTierMeta(points) {
        if (points <= 5) {
            return {
                badge: 'Starter · Light use',
                tagline: 'For trying the service or very occasional downloads.'
            };
        }
        if (points <= 30) {
            return {
                badge: 'Creator · For solo makers',
                tagline: 'Great for freelancers and solo creators shipping regularly.'
            };
        }
        if (points <= 100) {
            return {
                badge: 'Studio · Most popular',
                tagline: 'Perfect for busy designers and small studios.'
            };
        }
        if (points <= 250) {
            return {
                badge: 'Agency · Growing teams',
                tagline: 'Ideal for agencies working across multiple clients.'
            };
        }
        return {
            badge: 'Enterprise · High volume',
            tagline: 'For enterprises and resellers with heavy usage.'
        };
    }

    function formatEGP(value, decimals = 2) {
        return 'EGP ' + Number(value).toLocaleString('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    function updateUI(points) {
        const p = clampPoints(points);

        // sync both controls
        range.value = String(p);
        input.value = String(p);

        const pricePerPoint = getPricePerPoint(p);
        const monthlyTotal = pricePerPoint * p;
        const savings = (BASE_PRICE - pricePerPoint) * p;
        const tier = getTierMeta(p);

        if (pricePerPointEl) {
            pricePerPointEl.innerHTML =
                `<span class="price-value">${formatEGP(pricePerPoint, pricePerPoint % 1 === 0 ? 0 : 1)}</span> / point`;
        }

        if (monthlyTotalEl) {
            monthlyTotalEl.textContent = `${formatEGP(monthlyTotal, 0)} billed monthly`;
        }

        if (youSaveEl) {
            youSaveEl.textContent =
                savings > 0
                    ? `${formatEGP(savings, 0)} vs base pricing`
                    : '–';
        }

        if (badgeEl) {
            badgeEl.textContent = tier.badge;
        }
        if (taglineEl) {
            taglineEl.textContent = tier.tagline;
        }
        if (ctaPointsEl) {
            ctaPointsEl.textContent = p;
        }
    }

    // Slider moves 1 by 1
    range.addEventListener('input', () => {
        const value = clampPoints(parseInt(range.value, 10));
        updateUI(value);
    });

    // Typing directly in input (e.g. 13)
    input.addEventListener('input', () => {
        const value = clampPoints(parseInt(input.value, 10));
        updateUI(value);
    });

    // Safety: on blur, clamp and re-render
    input.addEventListener('blur', () => {
        const value = clampPoints(parseInt(input.value, 10));
        updateUI(value);
    });

    // Initial render
    updateUI(parseInt(range.value, 10) || 120);
});
