/*
 * NuttyLand staff point-of-sale screen.
 *
 * Works offline (report risk "Internet failure at a market", test case TC-05):
 *  - the product list is cached on the device,
 *  - every completed sale is put in a queue in localStorage with its own UUID,
 *  - the queue is sent to the server whenever the device is online.
 * The server ignores a UUID it has already stored, so re-sending is always safe.
 */
(function () {
    'use strict';

    const root = document.getElementById('pos');
    if (!root) return;

    const KEYS = { catalogue: 'nl_pos_catalogue', queue: 'nl_pos_queue', market: 'nl_pos_market' };
    const $ = (id) => document.getElementById(id);
    const money = (c) => '$' + (c / 100).toFixed(2);

    const store = {
        get(key, fallback) {
            try { const v = localStorage.getItem(key); return v ? JSON.parse(v) : fallback; } catch (e) { return fallback; }
        },
        set(key, value) {
            try { localStorage.setItem(key, JSON.stringify(value)); return true; } catch (e) { return false; }
        },
    };

    let csrf = document.querySelector('meta[name="csrf-token"]').content;
    let catalogue = store.get(KEYS.catalogue, null);
    let basket = []; // [{variantId, name, label, price_cents, qty}]
    let payment = 'eftpos';
    let syncing = false;

    function uuid() {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
        // RFC4122 v4 fallback for older browsers / non-secure contexts
        const b = new Uint8Array(16);
        (window.crypto || window.msCrypto).getRandomValues(b);
        b[6] = (b[6] & 0x0f) | 0x40; b[8] = (b[8] & 0x3f) | 0x80;
        const h = [...b].map((x) => x.toString(16).padStart(2, '0')).join('');
        return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
    }

    function toast(msg, colour) {
        const t = $('pos-toast');
        t.textContent = msg;
        t.className = t.className.replace(/bg-\S+/, colour || 'bg-leaf-700');
        t.classList.remove('hidden');
        clearTimeout(toast.timer);
        toast.timer = setTimeout(() => t.classList.add('hidden'), 2200);
    }

    function updateNetStatus() {
        const el = $('net-status');
        el.classList.remove('hidden');
        if (navigator.onLine) {
            el.textContent = 'Online';
            el.className = 'ml-auto rounded-full bg-leaf-600 px-2 py-0.5 text-xs font-bold';
        } else {
            el.textContent = 'Offline – sales saved on this device';
            el.className = 'ml-auto rounded-full bg-amber-500 px-2 py-0.5 text-xs font-bold text-nut-900';
        }
    }

    /* ---------- catalogue ---------- */

    async function loadCatalogue() {
        try {
            const res = await fetch(root.dataset.catalogueUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (res.status === 401) { $('pos-sync-msg').textContent = 'Your login has expired – log in again to sync.'; return; }
            if (!res.ok) throw new Error('HTTP ' + res.status);
            catalogue = await res.json();
            csrf = catalogue.csrf_token || csrf;
            store.set(KEYS.catalogue, catalogue);
        } catch (e) {
            if (!catalogue) { $('pos-loading').textContent = 'Offline and no saved product list on this device yet. Connect once to download it.'; return; }
        }
        renderMarkets();
        renderProducts();
    }

    function renderMarkets() {
        const select = $('pos-market');
        const saved = store.get(KEYS.market, null) || Number(root.dataset.defaultLocation) || null;
        select.innerHTML = '<option value="">Choose market…</option>' + catalogue.markets
            .map((m) => `<option value="${m.id}" ${m.id === saved ? 'selected' : ''}>${escapeHtml(m.name)}${m.trading_today ? ' (today)' : ''}</option>`)
            .join('');
        if (!select.value) {
            const today = catalogue.markets.find((m) => m.trading_today);
            if (today) select.value = today.id;
        }
        select.onchange = () => { store.set(KEYS.market, Number(select.value) || null); refreshButtons(); };
    }

    function renderProducts() {
        $('pos-loading').classList.add('hidden');
        const term = $('pos-search').value.trim().toLowerCase();
        const list = catalogue.products.filter((p) => !term || p.name.toLowerCase().includes(term) || p.category.toLowerCase().includes(term));
        $('pos-products').innerHTML = list.map((p) => `
            <div class="card p-2">
                <p class="mb-1.5 text-sm font-bold leading-tight">${escapeHtml(p.name)}</p>
                <div class="grid grid-cols-2 gap-1">
                    ${p.variants.map((v) => `<button type="button" class="rounded-md bg-nut-100 px-1 py-2 text-xs font-semibold text-nut-800 hover:bg-nut-200 active:bg-nut-300" data-add="${v.id}">${v.label}<br><span class="font-normal">${money(v.price_cents)}</span></button>`).join('')}
                </div>
            </div>`).join('') || '<p class="col-span-full p-4 text-center text-sm text-nut-500">No match.</p>';
    }

    function findVariant(id) {
        for (const p of catalogue.products) {
            const v = p.variants.find((x) => x.id === id);
            if (v) return { product: p, variant: v };
        }
        return null;
    }

    /* ---------- basket ---------- */

    function addToBasket(variantId) {
        const line = basket.find((l) => l.variantId === variantId);
        if (line) { line.qty++; } else {
            const f = findVariant(variantId);
            if (!f) return;
            basket.push({ variantId, name: f.product.name, label: f.variant.label, price_cents: f.variant.price_cents, qty: 1 });
        }
        renderBasket();
    }

    function renderBasket() {
        $('pos-basket').innerHTML = basket.map((l, i) => `
            <li class="flex items-center gap-2 py-2">
                <span class="flex-1"><b>${escapeHtml(l.name)}</b> ${l.label}<br><span class="text-xs text-nut-500">${money(l.price_cents)} each</span></span>
                <button type="button" class="btn-secondary h-9 w-9 p-0" data-dec="${i}" aria-label="Decrease">−</button>
                <span class="w-6 text-center font-bold">${l.qty}</span>
                <button type="button" class="btn-secondary h-9 w-9 p-0" data-inc="${i}" aria-label="Increase">+</button>
                <span class="w-16 text-right font-semibold">${money(l.qty * l.price_cents)}</span>
            </li>`).join('');
        $('pos-empty').classList.toggle('hidden', basket.length > 0);
        $('pos-total').textContent = money(basket.reduce((s, l) => s + l.qty * l.price_cents, 0));
        refreshButtons();
    }

    function refreshButtons() {
        $('pos-complete').disabled = basket.length === 0 || !$('pos-market').value;
        $('pos-complete').textContent = $('pos-market').value ? 'Complete sale' : 'Choose a market first';
    }

    function completeSale() {
        const locationId = Number($('pos-market').value);
        if (!basket.length || !locationId) return;
        const sale = {
            client_uuid: uuid(),
            location_id: locationId,
            payment_method: payment,
            sold_at: new Date().toISOString(),
            recorded_offline: !navigator.onLine,
            items: basket.map((l) => ({ product_variant_id: l.variantId, quantity: l.qty })),
        };
        const queue = store.get(KEYS.queue, []);
        queue.push(sale);
        if (!store.set(KEYS.queue, queue)) {
            alert('Could not save the sale on this device. Please record it on paper (manual fallback).');
            return;
        }
        toast(`Sale saved – ${$('pos-total').textContent} (${payment.toUpperCase()})`);
        basket = [];
        renderBasket();
        renderQueue();
        sync();
    }

    /* ---------- sync ---------- */

    function renderQueue() {
        $('pos-queue').textContent = store.get(KEYS.queue, []).length;
    }

    async function sync(isRetry) {
        const queue = store.get(KEYS.queue, []);
        renderQueue();
        if (syncing || !queue.length || !navigator.onLine) return;
        syncing = true;
        const batch = queue.slice(0, 50);
        try {
            const res = await fetch(root.dataset.syncUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ sales: batch }),
            });
            if (res.status === 419 && !isRetry) { // session token expired – fetch a fresh one and retry once
                syncing = false;
                await loadCatalogue();
                return sync(true);
            }
            if (res.status === 401 || res.status === 419) {
                $('pos-sync-msg').textContent = 'Log in again to send saved sales. They are kept safely on this device.';
                return;
            }
            if (res.status === 422) {
                const body = await res.json();
                $('pos-sync-msg').textContent = 'Some saved sales were rejected: ' + (body.message || 'validation error');
                return;
            }
            if (!res.ok) throw new Error('HTTP ' + res.status);

            const { results } = await res.json();
            const done = new Set(results.filter((r) => r.status === 'created' || r.status === 'duplicate').map((r) => r.client_uuid));
            const failed = results.filter((r) => r.status === 'error');
            store.set(KEYS.queue, store.get(KEYS.queue, []).filter((s) => !done.has(s.client_uuid)));
            $('pos-sync-msg').textContent = failed.length
                ? `${failed.length} sale(s) could not be saved: ${failed[0].message}`
                : `Last synced ${new Date().toLocaleTimeString()}`;
        } catch (e) {
            $('pos-sync-msg').textContent = 'Could not reach the server – will retry automatically.';
        } finally {
            syncing = false;
            renderQueue();
            if (store.get(KEYS.queue, []).length && navigator.onLine && !isRetry) setTimeout(sync, 1500);
        }
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    /* ---------- events ---------- */

    $('pos-products').addEventListener('click', (e) => {
        const b = e.target.closest('[data-add]');
        if (b) addToBasket(Number(b.dataset.add));
    });
    $('pos-basket').addEventListener('click', (e) => {
        const inc = e.target.closest('[data-inc]');
        const dec = e.target.closest('[data-dec]');
        if (inc) basket[Number(inc.dataset.inc)].qty++;
        if (dec) {
            const i = Number(dec.dataset.dec);
            if (--basket[i].qty <= 0) basket.splice(i, 1);
        }
        if (inc || dec) renderBasket();
    });
    document.querySelectorAll('.pay-btn').forEach((btn) => btn.addEventListener('click', () => {
        payment = btn.dataset.pay;
        document.querySelectorAll('.pay-btn').forEach((b) => {
            const on = b === btn;
            b.setAttribute('aria-pressed', on);
            b.classList.toggle('ring-2', on);
            b.classList.toggle('ring-nut-700', on);
            b.classList.toggle('bg-nut-100', on);
        });
    }));
    $('pos-search').addEventListener('input', renderProducts);
    $('pos-complete').addEventListener('click', completeSale);
    $('pos-clear').addEventListener('click', () => { basket = []; renderBasket(); });
    $('pos-sync').addEventListener('click', () => sync());
    window.addEventListener('online', () => { updateNetStatus(); sync(); });
    window.addEventListener('offline', updateNetStatus);
    setInterval(sync, 20000);

    document.querySelector('.pay-btn[data-pay="eftpos"]').click();
    updateNetStatus();
    if (catalogue) { renderMarkets(); renderProducts(); }
    loadCatalogue().then(() => sync());
    renderBasket();
    renderQueue();

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    }
})();
