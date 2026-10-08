import { Html5Qrcode } from 'html5-qrcode';

const csrf = () => document.querySelector('meta[name=csrf-token]')?.content;
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];

async function api(url, { method = 'GET', body } = {}) {
    const res = await fetch(url, {
        method,
        headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json', ...(body ? { 'Content-Type': 'application/json' } : {}) },
        body: body ? JSON.stringify(body) : undefined,
        credentials: 'same-origin',
    });
    let data = {};
    try { data = await res.json(); } catch (_) { /* leer */ }
    return { ok: res.ok, status: res.status, data };
}

// ── Bestätigungsdialoge (kein Inline-JS wegen CSP) ───────────────
document.addEventListener('submit', (e) => {
    const msg = e.target.dataset?.confirm;
    if (msg && !window.confirm(msg)) e.preventDefault();
});
document.addEventListener('click', (e) => {
    const t = e.target.closest('[data-print]');
    if (t) window.print();
    const nav = e.target.closest('[data-toggle-nav]');
    if (nav) $('#sidebar')?.classList.toggle('hidden');
});

// ── Alles markieren ──────────────────────────────────────────────
document.addEventListener('change', (e) => {
    if (e.target.matches('[data-check-all]')) {
        $$('input[type=checkbox][name="ids[]"]').forEach((c) => (c.checked = e.target.checked));
    }
    if (e.target.matches('[data-toggle-target]')) {
        const box = document.getElementById(e.target.dataset.toggleTarget);
        if (box) box.classList.toggle('hidden', !e.target.checked);
    }
    if (e.target.matches('[data-switch]')) {
        const val = e.target.value;
        $$('[data-show-when]').forEach((el) => {
            const [name, v] = el.dataset.showWhen.split('=');
            if (name === e.target.dataset.switch) {
                const hide = v !== val;
                el.classList.toggle('hidden', hide);
                // Versteckte Felder nicht mitsenden (gleichnamige Felder!)
                el.querySelectorAll('select,input,textarea').forEach((i) => { i.disabled = hide; });
            }
        });
    }
});
$$('[data-switch]').forEach((el) => el.dispatchEvent(new Event('change', { bubbles: true })));

// ── Kamera-Scanner ───────────────────────────────────────────────
let activeScanner = null;
async function openScanner(onCode) {
    const dlg = $('#scanner-dialog');
    if (!dlg) return;
    dlg.showModal();
    activeScanner = new Html5Qrcode('scanner-view');
    try {
        await activeScanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 250, height: 250 } },
            (text) => { closeScanner(); onCode(text.trim()); },
        );
    } catch (err) {
        $('#scanner-error').textContent = 'Kamera nicht verfügbar (HTTPS und Berechtigung erforderlich).';
    }
}
async function closeScanner() {
    const dlg = $('#scanner-dialog');
    try { await activeScanner?.stop(); activeScanner?.clear(); } catch (_) { /* ignore */ }
    activeScanner = null;
    if (dlg?.open) dlg.close();
}
$('#scanner-close')?.addEventListener('click', closeScanner);
$('#scanner-dialog')?.addEventListener('close', closeScanner);

document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-camera-for]');
    if (!btn) return;
    const input = document.getElementById(btn.dataset.cameraFor);
    openScanner((code) => {
        input.value = code;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        if (btn.dataset.submit !== undefined) input.form?.requestSubmit();
        else input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
    });
});

// ── Feedback-Toast + Ton ─────────────────────────────────────────
function beep(ok) {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const o = ctx.createOscillator(); const g = ctx.createGain();
        o.frequency.value = ok ? 880 : 220; g.gain.value = 0.08;
        o.connect(g); g.connect(ctx.destination); o.start(); o.stop(ctx.currentTime + (ok ? 0.1 : 0.35));
    } catch (_) { /* kein Audio */ }
    if (!ok && navigator.vibrate) navigator.vibrate(200);
}
function logLine(box, text, ok) {
    const li = document.createElement('li');
    li.className = 'flex items-start gap-2 rounded-lg border px-3 py-2 text-sm ' + (ok ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800');
    li.textContent = (ok ? '✓ ' : '✕ ') + text;
    box.prepend(li);
    while (box.children.length > 30) box.lastChild.remove();
    beep(ok);
}

// ── Scan-Seite: Nachschlagen / Schnellbuchung ────────────────────
const scanForm = $('#scan-form');
if (scanForm) {
    const input = $('#scan-code'); const card = $('#scan-card'); const log = $('#scan-log');
    let lastAsset = null;
    const render = (a) => {
        lastAsset = a;
        const badge = a.deployable ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
        card.innerHTML = '';
        const h = document.createElement('div');
        h.innerHTML = '<div class="flex items-start justify-between gap-3"><div><div class="text-xs text-zinc-500" data-f="tag"></div><div class="text-lg font-semibold" data-f="name"></div><div class="text-sm text-zinc-500" data-f="meta"></div></div><span class="badge ' + badge + '" data-f="status"></span></div><dl class="dl mt-4"><dt>Standort</dt><dd data-f="loc"></dd><dt>Ausgegeben an</dt><dd data-f="out"></dd><dt>Nächste Prüfung</dt><dd data-f="due"></dd></dl><ul class="mt-3 list-disc pl-5 text-sm text-zinc-600" data-f="contents"></ul><div class="mt-4 flex flex-wrap gap-2" data-f="actions"></div>';
        const set = (f, t) => { $('[data-f=' + f + ']', h).textContent = t; };
        set('tag', a.tag); set('name', a.name); set('meta', a.serial ? 'SN ' + a.serial : ''); set('status', a.status);
        set('loc', a.location || '—'); set('out', a.checked_out_to || '— (im Lager)');
        set('due', (a.inspection_due || '—') + (a.inspection_overdue ? '  ⚠ überfällig' : ''));
        a.contents.forEach((c) => { const li = document.createElement('li'); li.textContent = c; $('[data-f=contents]', h).append(li); });
        const act = $('[data-f=actions]', h);
        const mk = (label, cls, fn) => { const b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-sm ' + cls; b.textContent = label; b.addEventListener('click', fn); act.append(b); };
        const link = document.createElement('a'); link.href = a.url; link.className = 'btn btn-sm'; link.textContent = 'Details öffnen'; act.append(link);
        if (a.checked_out_to) mk('Zurücknehmen', 'btn-primary', () => quick('checkin'));
        else if (a.deployable) mk('Auf mich ausgeben', 'btn-primary', () => quick('checkout_user'));
        card.append(h);
        card.classList.remove('hidden');
    };
    const quick = async (action) => {
        const r = await api(scanForm.dataset.action, { method: 'POST', body: { code: lastAsset.tag, action } });
        logLine(log, r.data.message || 'Fehler', r.ok);
        if (r.ok) lookup(lastAsset.tag);
    };
    const lookup = async (code) => {
        const r = await api(scanForm.dataset.lookup + '?code=' + encodeURIComponent(code));
        if (!r.ok || !r.data.found) { logLine(log, 'Code „' + code + '“ unbekannt', false); card.classList.add('hidden'); return; }
        render(r.data); beep(true);
    };
    scanForm.addEventListener('submit', (e) => { e.preventDefault(); const c = input.value.trim(); if (c) { lookup(c); input.value = ''; } input.focus(); });
    input.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); scanForm.requestSubmit(); } });
    input.focus();
}

// ── Packen / Inventur: Scan → POST → Fortschritt ─────────────────
const packRoot = $('#pack-root') || $('#stocktake-root');
if (packRoot) {
    const input = $('#pack-code'); const log = $('#pack-log');
    const url = packRoot.dataset.url;
    const queueKey = 'inv-queue-' + url;
    const queue = () => JSON.parse(localStorage.getItem(queueKey) || '[]');
    const saveQueue = (q) => localStorage.setItem(queueKey, JSON.stringify(q));
    const modeEl = () => $('input[name=mode]:checked', packRoot)?.value;
    const send = async (payload) => {
        let r;
        try { r = await api(url, { method: 'POST', body: payload }); }
        catch (_) { return { offline: true }; }
        return r;
    };
    const handle = (r, code) => {
        if (r.offline) { saveQueue([...queue(), { payload: r.payload }]); logLine(log, code + ' – offline gespeichert, wird später gesendet', false); return; }
        logLine(log, r.data.message || 'Fehler', r.ok);
        if (r.ok && r.data.total !== undefined) {
            const bar = $('#pack-bar'); const txt = $('#pack-progress');
            const mode = modeEl();
            const done = mode === 'unload' ? r.data.returned : r.data.packed;
            const total = mode === 'unload' ? r.data.packed : r.data.total;
            if (bar) bar.style.width = (total ? Math.round(done / total * 100) : 0) + '%';
            if (txt) txt.textContent = done + ' / ' + total;
            if (r.data.item) {
                const row = document.querySelector('[data-item="' + r.data.item.id + '"]');
                if (row) { $('[data-packed]', row).textContent = r.data.item.packed; $('[data-returned]', row).textContent = r.data.item.returned; }
            }
        }
        if (r.ok && packRoot.id === 'stocktake-root') { /* Liste wird beim Reload aktualisiert */ }
    };
    const flush = async () => {
        let q = queue();
        while (q.length && navigator.onLine) {
            const item = q[0]; const r = await send(item.payload);
            if (r.offline) break;
            q.shift(); saveQueue(q); handle(r, item.payload.code);
        }
    };
    window.addEventListener('online', flush);
    const submit = async () => {
        const code = input.value.trim(); if (!code) return; input.value = '';
        const payload = { code, mode: modeEl() ?? 'load', condition: $('#pack-condition')?.value || 'ok' };
        const r = await send(payload); if (r.offline) r.payload = payload;
        handle(r, code); input.focus();
    };
    input.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); submit(); } });
    $('#pack-submit')?.addEventListener('click', submit);
    flush(); input.focus();
}

// ── Dynamische Zeilen (Packlisten-Vorlagen) ──────────────────────
const addRow = $('#add-kit-row');
if (addRow) {
    const tpl = $('#kit-row-template'); const body = $('#kit-rows');
    let n = body.children.length;
    const add = () => { const html = tpl.innerHTML.replaceAll('__i__', n++); const tr = document.createElement('tbody'); tr.innerHTML = html; body.append(tr.firstElementChild); };
    addRow.addEventListener('click', add);
    body.addEventListener('click', (e) => { if (e.target.closest('[data-remove-row]')) e.target.closest('tr').remove(); });
    if (!body.children.length) add();
}

// ── Service Worker (installierbare PWA, nur statische Assets) ───
if ('serviceWorker' in navigator && location.protocol === 'https:') {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
}

// ── NFC (Web NFC, Chrome auf Android): Tags lesen/beschreiben ───
if ('NDEFReader' in window) {
    // Lese-Button neben jedem Kamera-Button
    $$('[data-camera-for]').forEach((cam) => {
        const b = document.createElement('button');
        b.type = 'button'; b.className = 'btn'; b.textContent = 'NFC'; b.title = 'NFC-Tag lesen';
        cam.after(b);
        b.addEventListener('click', async () => {
            const input = document.getElementById(cam.dataset.cameraFor);
            b.textContent = '… Tag halten';
            try {
                const reader = new NDEFReader();
                const ctl = new AbortController();
                await reader.scan({ signal: ctl.signal });
                reader.onreading = (ev) => {
                    const rec = ev.message.records.find((r) => r.recordType === 'text' || r.recordType === 'url');
                    const code = rec ? new TextDecoder(rec.encoding || 'utf-8').decode(rec.data).trim() : ev.serialNumber;
                    ctl.abort(); b.textContent = 'NFC';
                    input.value = code; input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }));
                    if (cam.dataset.submit !== undefined) input.form?.requestSubmit();
                };
            } catch (_) { b.textContent = 'NFC'; }
        });
    });
    // Beschreiben
    $$('[data-nfc-write]').forEach((b) => {
        b.classList.remove('hidden');
        b.addEventListener('click', async () => {
            try { await new NDEFReader().write({ records: [{ recordType: 'text', data: b.dataset.nfcWrite }] }); b.textContent = '✓ Beschrieben'; }
            catch (_) { b.textContent = 'Fehlgeschlagen'; }
        });
    });
}

// ── Unterschriftenfeld ──────────────────────────────────────────
const sigCanvas = $('#sig-canvas');
if (sigCanvas) {
    const ctx = sigCanvas.getContext('2d');
    ctx.lineWidth = 3; ctx.lineCap = 'round'; ctx.strokeStyle = '#111';
    let drawing = false; let dirty = false;
    const pos = (e) => { const r = sigCanvas.getBoundingClientRect(); return [(e.clientX - r.left) * sigCanvas.width / r.width, (e.clientY - r.top) * sigCanvas.height / r.height]; };
    sigCanvas.addEventListener('pointerdown', (e) => { drawing = true; sigCanvas.setPointerCapture(e.pointerId); const [x, y] = pos(e); ctx.beginPath(); ctx.moveTo(x, y); });
    sigCanvas.addEventListener('pointermove', (e) => { if (!drawing) return; const [x, y] = pos(e); ctx.lineTo(x, y); ctx.stroke(); dirty = true; });
    ['pointerup', 'pointercancel'].forEach((ev) => sigCanvas.addEventListener(ev, () => { drawing = false; }));
    $('#sig-clear').addEventListener('click', () => { ctx.clearRect(0, 0, sigCanvas.width, sigCanvas.height); dirty = false; });
    $('#sig-form').addEventListener('submit', (e) => {
        if (!dirty) { e.preventDefault(); alert('Bitte zuerst unterschreiben.'); return; }
        $('#sig-image').value = sigCanvas.toDataURL('image/png');
    });
}
