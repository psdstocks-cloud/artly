(function(){
    const config = window.nehtwSiteControls || {};
    const supportsAbort = typeof AbortController !== 'undefined';
    const input = document.querySelector('[data-nehtw-stock-input]') || document.querySelector('#nehtw-stock-url') || document.querySelector('.nehtw-stock-url-input');
    const submit = document.querySelector('[data-nehtw-stock-submit]') || document.querySelector('#nehtw-stock-submit');
    const badgeTarget = document.querySelector('[data-nehtw-site-badge]') || document.querySelector('.nehtw-site-badge-target');
    const pointsTarget = document.querySelector('[data-nehtw-points-hint]') || document.querySelector('.nehtw-points-hint');
    const chipsTarget = document.querySelector('[data-nehtw-provider-chips]');

    function createBadge(){
        if(badgeTarget){
            badgeTarget.innerHTML = '';
            const badge = document.createElement('span');
            badge.className = 'nehtw-site-badge neutral';
            badgeTarget.appendChild(badge);
            return badge;
        }
        const badge = document.querySelector('.nehtw-site-badge');
        if(badge){return badge;}
        const span = document.createElement('span');
        span.className = 'nehtw-site-badge neutral';
        document.body.appendChild(span);
        return span;
    }

    function showToast(message){
        let toast = document.querySelector('.nehtw-site-toast');
        if(!toast){
            toast = document.createElement('div');
            toast.className = 'nehtw-site-toast';
            toast.setAttribute('role','status');
            toast.setAttribute('aria-live','polite');
            document.body.appendChild(toast);
        }
        toast.textContent = message;
        setTimeout(()=>{
            if(toast && toast.parentNode){ toast.parentNode.removeChild(toast); }
        },4000);
    }

    function setBadge(text, tone){
        const badge = createBadge();
        badge.textContent = text;
        badge.classList.remove('success','danger','neutral');
        badge.classList.add(tone || 'neutral');
    }

    function disableSubmit(state){
        if(!submit){return;}
        submit.disabled = state;
    }

    function updatePoints(points){
        if(pointsTarget){
            pointsTarget.textContent = points ? config.l10n.points.replace('%d', points) : '';
        }
    }

    let controller = null;
    async function validate(url){
        if(!url){
            setBadge(config.l10n.unsupported,'neutral');
            disableSubmit(true);
            updatePoints('');
            return;
        }
        if(controller && controller.abort){controller.abort();}
        controller = supportsAbort ? new AbortController() : null;
        try{
            const options = controller ? { signal: controller.signal } : {};
            const res = await fetch(`${config.root}nehtw/v1/sites/resolve?url=${encodeURIComponent(url)}`, options);
            const data = await res.json();
            if(!data.found){
                setBadge(config.l10n.unsupported,'neutral');
                disableSubmit(true);
                updatePoints('');
                return;
            }
            if(data.status !== 'active'){
                const tone = data.status === 'maintenance' ? 'danger' : 'danger';
                setBadge(`${data.label}: ${config.l10n[data.status] || data.status}`,tone);
                disableSubmit(true);
                updatePoints('');
                showToast(`${data.label} ${config.l10n.unavailable}`);
                if(config.notifyEnabled){
                    renderNotify(data.site_key,data.label);
                }
                return;
            }
            setBadge(`${data.label}: ${config.l10n.active}`,'success');
            disableSubmit(false);
            updatePoints(data.points_per_file);
            removeNotify();
        }catch(e){
            setBadge(config.l10n.error || 'Error','danger');
        }
    }

    function removeNotify(){
        const form = document.querySelector('.nehtw-notify-form');
        if(form){form.remove();}
    }

    function renderNotify(siteKey,label){
        if(!config.notifyEnabled){return;}
        let container = document.querySelector('.nehtw-notify-form');
        if(container){return;}
        container = document.createElement('form');
        container.className = 'nehtw-notify-form';
        container.innerHTML = `
            <label>
                <span>${config.l10n.notifyLabel.replace('%s', label)}</span>
                <input type="email" name="email" placeholder="${config.l10n.notifyEmail}" required />
            </label>
            <button type="submit">${config.l10n.notifyCta}</button>
        `;
        if(input && input.parentNode){
            input.parentNode.appendChild(container);
        }else{
            document.body.appendChild(container);
        }
        container.addEventListener('submit',async function(e){
            e.preventDefault();
            const email = container.querySelector('input[name="email"]').value;
            const payload = new FormData();
            payload.append('site_key', siteKey);
            payload.append('email', email);
            try{
                const res = await fetch(`${config.root}nehtw/v1/sites/notify`,{
                    method:'POST',
                    body: payload
                });
                const data = await res.json();
                if(data && data.success){
                    showToast(config.l10n.notifyThanks);
                    container.remove();
                }else{
                    showToast((data && data.message) || config.l10n.error);
                }
            }catch(err){
                showToast(config.l10n.error);
            }
        });
    }

    if(input){
        input.addEventListener('input', function(e){
            const value = e.target.value.trim();
            if(value.length < 6){
                disableSubmit(true);
                return;
            }
            validate(value);
        });
        if(input.value){
            validate(input.value.trim());
        }
    }

    if(config.providerChips && chipsTarget){
        fetch(`${config.root}nehtw/v1/sites`).then(r=>r.json()).then(list=>{
            chipsTarget.classList.add('nehtw-provider-chips');
            list.forEach(item=>{
                const chip = document.createElement('span');
                chip.className = `nehtw-provider-chip status-${item.status}`;
                chip.textContent = `${item.label} (${item.points_per_file})`;
                chip.title = item.status === 'active' ? config.l10n.active : config.l10n.tooltip;
                chipsTarget.appendChild(chip);
            });
        });
    }
})();
