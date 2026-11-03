/* PULSEMONITOR — shared UI helpers (pixel icons, charts, live log, dialogs) */
(function () {
  const C = {
    hp: '#3f8f45', warn: '#c98316', bad: '#c63d45', info: '#3f74b8',
    paper: '#f4ecd6', paper3: '#cbbd96', ink: '#181425', stone: '#cbbd96', stone2: '#7a6a58'
  };

  /* ---------- Pixel icons (16x16 grid, drawn with rects) ---------- */
  const PIX = {
    dashboard: ['1111111000000000','1000001000000000','1011101011111110','1011101010000010','1000001010111010','1111111010111010','0000000010000010','1111111011111110','1000001000000000','1011101011111110','1011101010000010','1000001010111010','1111111010111010','0000000010000010','0000000011111110','0000000000000000'],
    apps:      ['0001111111110000','0010000000001000','0100111111100100','0100100000100100','0100101110100100','0100100000100100','0100111111100100','0100000000000100','0100111111100100','0100100000100100','0100100000100100','0100111111100100','0010000000001000','0001111111110000','0000011111000000','0000000000000000'],
    uptime:    ['0000000000000000','0000000000000000','0000000000000000','0000000001000000','0000000011100000','0000000110110000','0000001100011000','1111111000001111','0000000000000000','0000000000000000','0001000000000000','0011100000000000','0110110000000000','1100011000000000','0000001111111111','0000000000000000'],
    events:    ['0000000110000000','0000001100000000','0000011000000000','0000110000000000','0001111111100000','0000000011000000','0000000110000000','0000001100000000','0000011000000000','0000110000000000','0001100000000000','0000000000000000','0000000000000000','0000000000000000','0000000000000000','0000000000000000'],
    alerts:    ['0000000110000000','0000001111000000','0000001111000000','0000011111100000','0000011111100000','0000111111110000','0000111001110000','0001111001111000','0001111001111000','0011111111111100','0011111111111100','0111111001111110','0111111111111110','1111111111111111','0000000000000000','0000000000000000'],
    lab:       ['0000011111000000','0000010001000000','0000010001000000','0000010001000000','0000010001000000','0000010001000000','0000011011000000','0000110001100000','0001100000110000','0011000000011000','0110111011101100','0110111111101100','0011111111111000','0001111111110000','0000000000000000','0000000000000000'],
    settings:  ['0000011001100000','0000011111100000','0001110000111000','0001100000011000','0111000000001110','0110001111000110','0110010000100110','1110010000100111','1110010000100111','0110010000100110','0110001111000110','0111000000001110','0001100000011000','0001110000111000','0000011111100000','0000011001100000'],
    server:    ['0111111111111110','0100000000000010','0101101000011010','0100000000000010','0111111111111110','0100000000000010','0101101000011010','0100000000000010','0111111111111110','0100000000000010','0101101000011010','0100000000000010','0111111111111110','0000001111000000','0000111111110000','0000000000000000'],
    shield:    ['0001111111111000','0011000000001100','0110000000000110','0110000000000110','0110001001000110','0110011011000110','0110110001100110','0110100000100110','0110000000000110','0011000000001100','0011000000001100','0001100000011000','0000110000110000','0000011001100000','0000001111000000','0000000000000000'],
    bolt:      ['0000000111100000','0000001111000000','0000011110000000','0000111100000000','0001111111110000','0011111111100000','0000000111000000','0000001110000000','0000011100000000','0000111000000000','0001110000000000','0011000000000000','0000000000000000','0000000000000000','0000000000000000','0000000000000000'],
    heart:     ['0000000000000000','0011100000111000','0111110001111100','1111111011111110','1111111111111110','1111111111111110','1111111111111110','0111111111111100','0011111111111000','0001111111110000','0000111111100000','0000011111000000','0000001110000000','0000000100000000','0000000000000000','0000000000000000'],
    skull:     ['0000111111110000','0001111111111000','0011111111111100','0111111111111110','0111001111001110','0110000110000110','0110000110000110','0111001111001110','0111111111111110','0011111111111100','0001111001111000','0000110000110000','0000110110110000','0000011111100000','0000000000000000','0000000000000000'],
    globe:     ['0000011111100000','0001100000011000','0010011111100100','0100100000010010','0100100000010010','1001000000001001','1111111111111111','1001000000001001','1001000000001001','1111111111111111','0100100000010010','0100100000010010','0010011111100100','0001100000011000','0000011111100000','0000000000000000'],
    key:       ['0000000000111000','0000000001111100','0000000011000110','0000000011000110','0000000001111100','0000000000111000','0000000001100000','0000000011000000','0000000110000000','0000001100000000','0000011000000000','0000110110000000','0001100110000000','0011000000000000','0110000000000000','0000000000000000'],
    chip:      ['0001000100010000','0001000100010000','0111111111111100','0100000000000100','1101111111101110','0101000000101000','1101000000101110','0101000000101000','1101000000101110','0101000000101000','1101111111101110','0100000000000100','0111111111111100','0001000100010000','0001000100010000','0000000000000000'],
    check:     ['0000000000000000','0000000000000110','0000000000001110','0000000000011100','0000000000111000','0000000001110000','0110000011100000','1111000111000000','0111101110000000','0011111100000000','0001111000000000','0000110000000000','0000000000000000','0000000000000000','0000000000000000','0000000000000000'],
    search:    ['0000111111000000','0011000000110000','0110000000011000','0100000000001000','1100000000001100','1000000000000100','1000000000000100','1100000000001100','0100000000001000','0110000000011000','0011000000111100','0000111111011110','0000000000001111','0000000000000111','0000000000000011','0000000000000000'],
    stopwatch: ['0000011111100000','0000000110000000','0000011111100000','0001100000011000','0011000000001100','0110000110000110','0100000110000010','1100000110000011','1100000111100011','0100000000000010','0110000000000110','0011000000001100','0001100000011000','0000011111100000','0000000000000000','0000000000000000'],
    play:      ['0000000000000000','0001100000000000','0001111000000000','0001111110000000','0001111111100000','0001111111111000','0001111111111110','0001111111111110','0001111111111000','0001111111100000','0001111110000000','0001111000000000','0001100000000000','0000000000000000','0000000000000000','0000000000000000'],
  };
  function icon(name, color, size) {
    const rows = PIX[name] || PIX.chip; const s = size || 16;
    let r = '';
    rows.forEach((row, y) => { for (let x = 0; x < 16; x++) if (row[x] === '1') r += `<rect x="${x}" y="${y}" width="1" height="1"/>`; });
    return `<svg class="ico pixel-ico" viewBox="0 0 16 16" width="${s}" height="${s}" fill="${color || 'currentColor'}" shape-rendering="crispEdges">${r}</svg>`;
  }
  document.querySelectorAll('[data-ico]').forEach(el => { el.innerHTML = icon(el.dataset.ico, el.dataset.color, el.dataset.size); });

  /* ---------- Topbar clock / ws ping ---------- */
  function shell() {
    const clock = document.getElementById('clock');
    if (clock) { const t = () => { clock.textContent = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }); }; t(); setInterval(t, 10000); }
    const ping = document.getElementById('ws-ping');
    if (ping) setInterval(() => { ping.textContent = (9 + Math.floor(Math.random() * 9)) + 'ms'; }, 3000);
  }

  /* ---------- Canvas helpers ---------- */
  function setup(canvas) {
    const r = canvas.parentElement.getBoundingClientRect();
    const dpr = Math.min(2, window.devicePixelRatio || 1);
    canvas.width = r.width * dpr; canvas.height = r.height * dpr;
    const ctx = canvas.getContext('2d'); ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.imageSmoothingEnabled = false; ctx.clearRect(0, 0, r.width, r.height);
    return { ctx, w: r.width, h: r.height };
  }
  function frame(ctx, w, h, pad, max, min, unit, xlabels) {
    ctx.fillStyle = C.stone;
    for (let i = 0; i <= 3; i++) { const y = Math.round(pad.t + (h - pad.t - pad.b) * i / 3); for (let x = pad.l; x < w - pad.r; x += 6) ctx.fillRect(x, y, 2, 2); }
    ctx.fillStyle = C.stone2; ctx.font = '7px "Press Start 2P"'; ctx.textAlign = 'right'; ctx.textBaseline = 'middle';
    for (let i = 0; i <= 3; i++) { const v = max - (max - min) * i / 3; ctx.fillText(Math.round(v) + (unit || ''), pad.l - 8, pad.t + (h - pad.t - pad.b) * i / 3); }
    if (xlabels) { ctx.textAlign = 'center'; ctx.textBaseline = 'top'; xlabels.forEach((l, i) => ctx.fillText(l, pad.l + (w - pad.l - pad.r) * i / (xlabels.length - 1), h - pad.b + 8)); }
  }
  function niceMax(v, step) { return Math.max(step, Math.ceil(v * 1.15 / step) * step); }
  // stepped pixel line
  function lineChart(canvas, series, opts) {
    opts = opts || {}; const { ctx, w, h } = setup(canvas);
    const pad = { l: 58, r: 8, t: 8, b: opts.xlabels ? 24 : 8 };
    const max = opts.max ?? niceMax(Math.max(...series.flatMap(s => s.data)), 10); const min = opts.min ?? 0;
    frame(ctx, w, h, pad, max, min, opts.unit, opts.xlabels);
    const Y = v => Math.round(pad.t + (h - pad.t - pad.b) * (1 - (Math.min(max, v) - min) / (max - min)));
    if (opts.threshold != null) { ctx.fillStyle = C.bad; const y = Y(opts.threshold); for (let x = pad.l; x < w - pad.r; x += 10) ctx.fillRect(x, y, 6, 2); }
    series.forEach(s => {
      const n = s.data.length; const pw = (w - pad.l - pad.r) / (n - 1);
      const pts = s.data.map((v, i) => [Math.round(pad.l + i * pw), Y(v)]);
      if (s.fill) { ctx.fillStyle = s.color + '2a'; pts.forEach(([x, y], i) => { if (i < n - 1) ctx.fillRect(x, y, pts[i + 1][0] - x, h - pad.b - y); }); }
      ctx.fillStyle = s.color;
      pts.forEach(([x, y], i) => { if (i < n - 1) { const [nx, ny] = pts[i + 1]; ctx.fillRect(x, y - 1, nx - x + 3, 3); ctx.fillRect(nx, Math.min(y, ny) - 1, 3, Math.abs(ny - y) + 3); } });
      const [lx, ly] = pts[n - 1]; ctx.fillRect(lx - 3, ly - 4, 7, 7);
    });
  }
  function barChart(canvas, data, opts) {
    opts = opts || {}; const { ctx, w, h } = setup(canvas);
    const pad = { l: 52, r: 8, t: 8, b: opts.xlabels ? 24 : 8 };
    const max = opts.max ?? niceMax(Math.max(...data), 5);
    frame(ctx, w, h, pad, max, 0, opts.unit, opts.xlabels);
    const n = data.length; const bw = (w - pad.l - pad.r) / n; const gap = Math.max(2, Math.round(bw * .3));
    data.forEach((v, i) => {
      const bh = Math.round((h - pad.t - pad.b) * Math.min(v, max) / max); const x = Math.round(pad.l + i * bw + gap / 2);
      ctx.fillStyle = opts.colorFn ? opts.colorFn(v, i) : (opts.color || C.hp);
      for (let yy = h - pad.b - 4; yy > h - pad.b - bh; yy -= 6) ctx.fillRect(x, yy, Math.max(2, Math.round(bw - gap)), 4);
    });
  }
  function live(canvas, opts) {
    const n = opts.points || 50; const data = Array.from({ length: n }, () => opts.base + (Math.random() - .5) * opts.noise);
    const tick = () => { data.shift(); data.push(Math.max(0, opts.base + (Math.random() - .5) * opts.noise)); if (canvas.offsetParent) lineChart(canvas, [{ data, color: opts.color || C.hp, fill: true }], { max: opts.max, unit: opts.unit }); };
    tick(); return setInterval(tick, opts.interval || 1500);
  }

  /* ---------- Data helpers ---------- */
  function rnd(min, max) { return Math.round(min + Math.random() * (max - min)); }
  function series(n, base, noise, spikeProb) { return Array.from({ length: n }, () => Math.max(0, base + (Math.random() - .5) * noise + (Math.random() < (spikeProb || 0) ? noise * 2 : 0))); }
  document.querySelectorAll('.blocks[data-auto]').forEach(el => {
    const n = +el.dataset.auto; const step = +(el.dataset.step || 3600000);
    const down = (el.dataset.down || '').split(',').filter(Boolean).map(Number); const deg = (el.dataset.deg || '').split(',').filter(Boolean).map(Number);
    let html = ''; const now = Date.now();
    for (let i = 0; i < n; i++) {
      const cls = down.includes(i) ? 'down' : deg.includes(i) ? 'degraded' : '';
      const t = new Date(now - (n - 1 - i) * step).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
      html += `<i class="${cls}" data-t="${t} ${cls === 'down' ? 'DOWN' : cls === 'degraded' ? 'SLOW' : 'UP'}"></i>`;
    }
    el.innerHTML = html;
  });

  /* ---------- Live event log ---------- */
  const APPS = ['checkout-api', 'auth-service', 'payments', 'billing-worker', 'cdn-edge', 'mobile-bff'];
  const MSGS = {
    INFO: ['Request completed 200 OK', 'User session created', 'Webhook delivered', 'Job queued: invoice.generate', 'Health check passed', 'Deploy v2.14.3 finished'],
    WARNING: ['Response time 842ms > 500ms', 'Retrying upstream (2/3)', 'Rate limit at 85%', 'Slow query 1.9s on orders'],
    ERROR: ['500 Internal Server Error', 'ECONNREFUSED db-primary:5432', 'Timeout after 10000ms', 'Payment gateway 502'],
  };
  const pick = a => a[Math.floor(Math.random() * a.length)];
  function fakeEvent(app) { const r = Math.random(); const lvl = r < .75 ? 'INFO' : r < .92 ? 'WARNING' : 'ERROR'; return { ts: new Date(), lvl, app: app || pick(APPS), msg: pick(MSGS[lvl]) }; }
  function eventLine(e, withPayload) {
    const payload = withPayload ? `<details><summary>payload</summary><pre>${JSON.stringify({ event_id: 'evt_' + Math.random().toString(36).slice(2, 10), level: e.lvl.toLowerCase(), source: e.app, message: e.msg, ip: '10.0.' + rnd(0, 9) + '.' + rnd(2, 254), duration_ms: rnd(12, 900) }, null, 2)}</pre></details>` : '';
    return `<div class="line new"><span class="ts">${e.ts.toLocaleTimeString('pt-BR')}</span><span class="lvl ${e.lvl}">${e.lvl === 'WARNING' ? 'WARN' : e.lvl}</span><span class="src">${e.app}</span><span>${e.msg}${payload}</span></div>`;
  }
  function feed(el, opts) {
    opts = opts || {}; const max = opts.max || 8;
    for (let i = 0; i < max; i++) el.insertAdjacentHTML('beforeend', eventLine(fakeEvent(opts.app), opts.payload).replace(' new', ''));
    let paused = false;
    const t = setInterval(() => { if (paused) return; el.insertAdjacentHTML('afterbegin', eventLine(fakeEvent(opts.app), opts.payload)); while (el.children.length > max) el.lastElementChild.remove(); }, opts.interval || 2500);
    return { toggle() { paused = !paused; return paused; }, stop() { clearInterval(t); } };
  }

  /* ---------- Dialog typewriter ---------- */
  function type(el) {
    const html = el.innerHTML; const tmp = document.createElement('div'); tmp.innerHTML = html; const text = tmp.textContent; let i = 0;
    el.textContent = '';
    const t = setInterval(() => { i += 2; if (i >= text.length) { clearInterval(t); el.innerHTML = html; } else el.textContent = text.slice(0, i); }, 22);
  }
  document.querySelectorAll('[data-type]').forEach(type);

  /* ---------- Tabs that switch panes ---------- */
  document.querySelectorAll('[data-tabs]').forEach(tabs => {
    tabs.addEventListener('click', e => {
      const b = e.target.closest('[data-pane]'); if (!b) return; e.preventDefault();
      tabs.querySelectorAll('[data-pane]').forEach(x => { x.classList.toggle('active', x === b); const p = document.getElementById(x.dataset.pane); if (p) p.hidden = x !== b; });
      window.dispatchEvent(new Event('resize'));
    });
  });

  /* ---------- UI utils ---------- */
  function toast(msg) {
    let t = document.querySelector('.toast');
    if (!t) { t = document.createElement('div'); t.className = 'box dialog toast'; document.body.appendChild(t); }
    t.textContent = msg; t.classList.add('show'); clearTimeout(t._h); t._h = setTimeout(() => t.classList.remove('show'), 2200);
  }
  document.addEventListener('click', e => {
    const c = e.target.closest('[data-copy]'); if (c) { if (navigator.clipboard) navigator.clipboard.writeText(c.dataset.copy).catch(() => {}); toast(c.dataset.label || 'Copied!'); }
    const m = e.target.closest('[data-modal]'); if (m) { e.preventDefault(); document.getElementById(m.dataset.modal).classList.add('open'); }
    const x = e.target.closest('[data-close]'); if (x) { e.preventDefault(); x.closest('.modal-bg').classList.remove('open'); }
    const t = e.target.closest('[data-toast]'); if (t) { e.preventDefault(); toast(t.dataset.toast); }
    if (e.target.classList && e.target.classList.contains('modal-bg')) e.target.classList.remove('open');
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelectorAll('.modal-bg.open').forEach(m => m.classList.remove('open')); });

  /* ---------- Custom select (game menu look, native select kept underneath) ---------- */
  // The list lives in <body> so the clipped .box corners can't cut it off.
  function closeSelects(except) { document.querySelectorAll('.select.open').forEach(w => { if (w !== except) { w.classList.remove('open'); w._list.classList.remove('open'); } }); }
  function placeList(btn, list) {
    const r = btn.getBoundingClientRect(); const gap = 6, edge = 8;
    list.style.minWidth = r.width + 'px';
    const w = list.offsetWidth, h = list.offsetHeight;
    list.style.left = Math.max(edge, Math.min(r.left, innerWidth - w - edge)) + 'px';
    list.style.top = (r.bottom + gap + h > innerHeight - edge && r.top - gap - h > edge ? r.top - gap - h : r.bottom + gap) + 'px';
  }
  function enhanceSelect(sel) {
    if (sel.dataset.enhanced) return; sel.dataset.enhanced = '1';
    const wrap = document.createElement('div'); wrap.className = 'select'; wrap.style.cssText = sel.style.cssText;
    sel.parentNode.insertBefore(wrap, sel); wrap.appendChild(sel); sel.tabIndex = -1; sel.setAttribute('aria-hidden', 'true');
    const btn = document.createElement('button'); btn.type = 'button'; btn.className = 'input select-btn'; btn.setAttribute('aria-haspopup', 'listbox');
    if (sel.id) { btn.id = sel.id; sel.removeAttribute('id'); } // keeps <label for> working
    const list = document.createElement('ul'); list.className = 'box select-list'; list.setAttribute('role', 'listbox');
    wrap.append(btn); document.body.appendChild(list); wrap._list = list;
    let active = sel.selectedIndex;
    const render = () => {
      btn.textContent = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : '';
      list.innerHTML = [...sel.options].map((o, i) => `<li role="option" data-i="${i}" aria-selected="${i === sel.selectedIndex}" class="${i === active ? 'active' : ''} ${i === sel.selectedIndex ? 'selected' : ''}">${o.text}</li>`).join('');
    };
    const isOpen = () => wrap.classList.contains('open');
    const open = () => { closeSelects(wrap); active = sel.selectedIndex; render(); wrap.classList.add('open'); list.classList.add('open'); placeList(btn, list); };
    const close = () => { wrap.classList.remove('open'); list.classList.remove('open'); };
    const choose = i => { sel.selectedIndex = i; sel.dispatchEvent(new Event('input', { bubbles: true })); sel.dispatchEvent(new Event('change', { bubbles: true })); close(); btn.focus(); };
    btn.addEventListener('click', () => (isOpen() ? close() : open()));
    list.addEventListener('mousedown', e => e.preventDefault());
    list.addEventListener('click', e => { const li = e.target.closest('li'); if (li) choose(+li.dataset.i); });
    list.addEventListener('mouseover', e => { const li = e.target.closest('li'); if (li && +li.dataset.i !== active) { active = +li.dataset.i; render(); } });
    btn.addEventListener('keydown', e => {
      const n = sel.options.length;
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); if (!isOpen()) return open(); active = (active + (e.key === 'ArrowDown' ? 1 : n - 1)) % n; render(); }
      else if ((e.key === 'Enter' || e.key === ' ') && isOpen()) { e.preventDefault(); choose(active); }
      else if (e.key === 'Escape' || e.key === 'Tab') close();
    });
    sel.addEventListener('change', render);
    render();
  }
  document.addEventListener('click', e => { if (!e.target.closest('.select, .select-list')) closeSelects(); });
  window.addEventListener('resize', () => closeSelects());
  window.addEventListener('scroll', e => { if (!(e.target.closest && e.target.closest('.select-list'))) closeSelects(); }, true);
  document.querySelectorAll('select.input').forEach(enhanceSelect);

  shell();
  window.PM = { C, icon, lineChart, barChart, live, series, rnd, feed, toast, type, enhanceSelect };
})();
