/* Integral del Este — comportamiento de las pantallas (sin dependencias).
 * - Formularios con data-ajax: se envían con fetch y la respuesta JSON decide
 *   (redirigir, recargar, mostrar un aviso, marcar campos o pedir la clave de un administrador).
 * - Modales y panel lateral cargados del servidor.
 * - Sugerencias de búsqueda (RT-01), formato de montos y documentos (RT-02).
 * - Esc (RT-09), cierre por inactividad (CU-02) y envío por WhatsApp (RT-04).
 */
(function () {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const csrf = () => ($('meta[name=csrf]') || {}).content || '';
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const norm = v => String(v ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
  const MAY = v => String(v ?? '').toLocaleUpperCase('es-AR');

  /* ---------------- avisos ---------------- */
  function toast(msg, tipo) {
    document.querySelectorAll('.toast').forEach(t => t.remove());
    const l = document.createElement('div');
    l.className = 'toast' + (tipo === 'bad' ? ' toast-bad' : '');
    l.setAttribute('role', 'status');
    l.textContent = msg;
    document.body.appendChild(l);
    setTimeout(() => l.remove(), tipo === 'bad' ? 6000 : 3500);
  }
  window.toast = toast;
  const fl = $('#flash');
  if (fl) { try { JSON.parse(fl.textContent).forEach(f => toast(f.m, f.t)); } catch (e) { } }

  /* ---------------- pedidos ---------------- */
  async function pedir(url, opts = {}) {
    const h = Object.assign({ 'X-Requested-With': 'fetch', 'X-CSRF': csrf(), 'Accept': 'application/json' }, opts.headers || {});
    const r = await fetch(url, Object.assign({ credentials: 'same-origin' }, opts, { headers: h }));
    if (r.status === 401) { location.href = '/ingreso?vencida=1'; throw new Error('sesion'); }
    return r;
  }
  async function postJSON(url, datos) {
    const fd = datos instanceof FormData ? datos : (() => { const f = new FormData(); Object.entries(datos || {}).forEach(([k, v]) => f.append(k, v)); return f; })();
    if (!fd.has('_csrf')) fd.append('_csrf', csrf());
    const r = await pedir(url, { method: 'POST', body: fd });
    let j = {};
    try { j = await r.json(); } catch (e) { j = { ok: false, error: 'Respuesta inesperada del servidor.' }; }
    return j;
  }
  window.postJSON = postJSON;

  /* ---------------- modales ---------------- */
  const layer2 = () => $('#layer2');
  function modal(html) {
    layer2().innerHTML = `<div class="overlay center" data-close-modal>${html}</div>`;
    preparar(layer2());
    const f = layer2().querySelector('input:not([type=hidden]),textarea,select,button.primary');
    if (f) f.focus();
  }
  function cerrarModal() { layer2().innerHTML = ''; }
  const hayModal = () => !!(layer2() && layer2().firstElementChild);
  window.modal = modal; window.cerrarModal = cerrarModal;

  async function abrirModalUrl(url) {
    const r = await pedir(url, { headers: { Accept: 'text/html' } });
    const t = await r.text();
    if (!r.ok) { try { toast(JSON.parse(t).error, 'bad'); } catch (e) { toast('No se pudo abrir.', 'bad'); } return; }
    modal(t);
  }
  window.abrirModalUrl = abrirModalUrl;

  /* Panel lateral (ficha del afiliado). */
  async function abrirPanel(url) {
    const r = await pedir(url, { headers: { Accept: 'text/html' } });
    const t = await r.text();
    if (!r.ok) { toast('No se pudo abrir la ficha.', 'bad'); return; }
    $('#layer').innerHTML = `<div class="overlay" data-close-panel>${t}</div>`;
    preparar($('#layer'));
    document.body.dataset.panel = url;
  }
  function cerrarPanel() { $('#layer').innerHTML = ''; delete document.body.dataset.panel; }
  const hayPanel = () => !!($('#layer') && $('#layer').firstElementChild);
  window.abrirPanel = abrirPanel; window.cerrarPanel = cerrarPanel;

  /* ---------------- CU-04: autorización de un administrador ---------------- */
  function pedirAdmin(accion, texto) {
    return new Promise(resolve => {
      modal(`<form id="fadmin" class="modal" role="dialog" aria-modal="true" aria-label="Autorización de administrador">
        <h2>Requiere un administrador</h2><p>${esc(texto)}</p>
        <label class="f">Usuario administrador<input id="adm-u" autocomplete="off" required></label>
        <label class="f">Clave<input id="adm-p" type="password" autocomplete="off" required></label>
        <p id="adm-err" style="color:var(--bad);display:none"></p>
        <div class="row" style="justify-content:flex-end"><button class="btn" type="button" data-act="modal-no">Cancelar <kbd>Esc</kbd></button><button class="btn primary" type="submit">Autorizar</button></div></form>`);
      const f = $('#fadmin');
      f.addEventListener('submit', async ev => {
        ev.preventDefault();
        const j = await postJSON('/api/autorizar', { usuario: $('#adm-u').value, clave: $('#adm-p').value, accion });
        if (!j.ok) { const e = $('#adm-err'); e.textContent = j.error; e.style.display = ''; return; }
        cerrarModal(); resolve(j.token);
      });
    });
  }
  window.pedirAdmin = pedirAdmin;

  /* ---------------- envío de formularios ---------------- */
  function marcarCampos(form, campos) {
    $$('.falta', form).forEach(i => i.classList.remove('falta'));
    (campos || []).forEach(n => { const i = form.querySelector(`[name="${n}"],[name="${n}[]"],#${CSS.escape(n)}`); if (i) { i.classList.add('falta'); } });
    const p = (campos || []).length && form.querySelector('.falta'); if (p) p.focus();
  }
  async function enviar(form, extra = {}) {
    if (form.dataset.enviando) return;
    if (form.dataset.confirmar && !extra._confirmado && !confirm(form.dataset.confirmar)) return;
    const fd = new FormData(form);
    if (form._submitter && form._submitter.name) fd.set(form._submitter.name, form._submitter.value);
    Object.entries(extra).forEach(([k, v]) => fd.set(k, v));
    form.dataset.enviando = 1;
    let j;
    try { j = await postJSON(form.getAttribute('action') || location.pathname, fd); }
    finally { delete form.dataset.enviando; }
    const err = form.querySelector('.form-error');
    if (j.requiereAdmin) {
      const tok = await pedirAdmin(j.requiereAdmin, j.texto);
      return enviar(form, Object.assign({}, extra, { _adm: tok }));
    }
    if (!j.ok) {
      if (j.redirect) { location.href = j.redirect; return; }
      if (err) { err.textContent = j.error; err.style.display = ''; } else toast(j.error, 'bad');
      marcarCampos(form, j.campos);
      form.dispatchEvent(new CustomEvent('ajax:error', { detail: j }));
      return;
    }
    if (err) err.style.display = 'none';
    marcarCampos(form, []);
    if (j.recordar !== undefined) recordarUsuario(j.recordar);
    form.dispatchEvent(new CustomEvent('ajax:ok', { detail: j, bubbles: true }));
    if (j.whatsapp) abrirWhatsApp(j.whatsapp);
    if (j.descargar) descargar(j.descargar);
    if (j.modalUrl) { if (j.mensaje) toast(j.mensaje); await abrirModalUrl(j.modalUrl); return; }
    if (j.modalHtml) { modal(j.modalHtml); if (j.mensaje) toast(j.mensaje); return; }
    if (j.redirect) { location.href = j.redirect; return; }
    if (j.recargar) { location.reload(); return; }
    if (j.mensaje) toast(j.mensaje);
    if (j.cerrar) cerrarModal();
    if (j.panel) abrirPanel(j.panel);
  }
  window.enviarForm = enviar;

  document.addEventListener('click', e => { const b = e.target.closest('button[type=submit],input[type=submit]'); if (b && b.form) b.form._submitter = b; }, true);
  document.addEventListener('submit', e => {
    const f = e.target;
    if (!f.matches('form[data-ajax]')) return;
    e.preventDefault();
    enviar(f);
  });

  /* ---------------- descargas y WhatsApp (RT-04) ---------------- */
  async function obtenerArchivo(url) {
    const r = await pedir(url, { headers: { Accept: '*/*' } });
    if (!r.ok) { let m = 'No se pudo generar el archivo.'; try { m = (await r.json()).error || m; } catch (e) { } throw new Error(m); }
    const cd = r.headers.get('Content-Disposition') || '';
    const m = cd.match(/filename\*=UTF-8''([^;]+)/) || cd.match(/filename="([^"]+)"/);
    const nombre = m ? decodeURIComponent(m[1]) : 'archivo';
    return { blob: await r.blob(), nombre };
  }
  async function descargar(url) {
    try {
      const { blob, nombre } = await obtenerArchivo(url);
      const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = nombre;
      document.body.appendChild(a); a.click(); setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 1500);
    } catch (e) { toast(e.message, 'bad'); }
  }
  window.descargar = descargar;
  const esMovil = () => /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
  /* {pdf, tel, texto}: en el celular comparte el PDF; en la computadora lo descarga y abre wa.me. */
  async function abrirWhatsApp(w) {
    const url = 'https://wa.me/' + (w.tel || '') + '?text=' + encodeURIComponent(w.texto || '');
    if (!w.pdf) { window.open(url, '_blank', 'noopener'); return; }
    try {
      const { blob, nombre } = await obtenerArchivo(w.pdf);
      const file = new File([blob], nombre, { type: 'application/pdf' });
      if (esMovil() && navigator.canShare && navigator.canShare({ files: [file] })) {
        await navigator.share({ files: [file], text: w.texto || '' });
        return;
      }
      const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = nombre;
      document.body.appendChild(a); a.click(); setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 1500);
      toast('Se descargó el PDF: adjuntalo en el chat de WhatsApp.');
      window.open(url, '_blank', 'noopener');
    } catch (e) { if (e.name !== 'AbortError') toast(e.message || 'No se pudo compartir.', 'bad'); }
  }
  window.abrirWhatsApp = abrirWhatsApp;

  /* ---------------- clicks con data-* ---------------- */
  document.addEventListener('click', async e => {
    const t = e.target;
    if (t.matches('[data-close-modal]')) { cerrarModal(); return; }
    if (t.matches('[data-close-panel]')) { window.cerrarPanel(); return; }
    const b = t.closest('[data-act],[data-modal],[data-panel],[data-post],[data-descargar],[data-wa],[data-href]');
    if (!b) return;
    if (b.dataset.href) { location.href = b.dataset.href; return; }
    if (b.dataset.modal) { e.preventDefault(); abrirModalUrl(b.dataset.modal); return; }
    if (b.dataset.panel) { e.preventDefault(); abrirPanel(b.dataset.panel); return; }
    if (b.dataset.descargar) { e.preventDefault(); descargar(b.dataset.descargar); return; }
    if (b.dataset.wa) { e.preventDefault(); try { const j = JSON.parse(b.dataset.wa); if (j.post) { const r = await postJSON(j.post, j.datos || {}); if (!r.ok) { toast(r.error, 'bad'); return; } abrirWhatsApp(r.whatsapp || j); if (r.mensaje) toast(r.mensaje); if (r.recargar) setTimeout(() => location.reload(), 1500); } else abrirWhatsApp(j); } catch (er) { toast('No se pudo abrir WhatsApp.', 'bad'); } return; }
    if (b.dataset.post) {
      e.preventDefault();
      if (b.dataset.confirmar && !confirm(b.dataset.confirmar)) return;
      const datos = b.dataset.datos ? JSON.parse(b.dataset.datos) : {};
      let j = await postJSON(b.dataset.post, datos);
      if (j.requiereAdmin) { const tok = await pedirAdmin(j.requiereAdmin, j.texto); j = await postJSON(b.dataset.post, Object.assign(datos, { _adm: tok })); }
      if (!j.ok) { toast(j.error, 'bad'); return; }
      if (j.whatsapp) abrirWhatsApp(j.whatsapp);
      if (j.descargar) descargar(j.descargar);
      if (j.modalUrl) { if (j.mensaje) toast(j.mensaje); abrirModalUrl(j.modalUrl); return; }
      if (j.modalHtml) { modal(j.modalHtml); return; }
      if (j.redirect) { location.href = j.redirect; return; }
      if (j.mensaje) toast(j.mensaje);
      if (j.panel) { document.body.dataset.refrescar = 1; abrirPanel(j.panel); }
      if (j.recargar) setTimeout(() => location.reload(), j.mensaje ? 700 : 0);
      if (j.cerrar) cerrarModal();
      return;
    }
    const act = b.dataset.act;
    if (act === 'modal-no') { cerrarModal(); return; }
    if (act === 'panel-no') { window.cerrarPanel(); return; }
    if (act === 'salir') { const j = await postJSON('/salir', {}); location.href = j.redirect || '/ingreso'; return; }
    if (act === 'novedades') { abrirModalUrl('/novedades'); return; }
    if (act === 'imprimir') { window.print(); return; }
    if (act === 'cumple-wa') {
      const j = await postJSON('/api/cumple/' + b.dataset.a + '/saludar', {});
      if (!j.ok) { toast(j.error, 'bad'); return; }
      await abrirWhatsApp(j.whatsapp);
      setTimeout(() => location.reload(), 1200);
    }
  });

  /* Filtros que se aplican al cambiar (sin JavaScript en línea por la política de seguridad). */
  document.addEventListener('change', e => { if (e.target.matches('[data-autosubmit]') && e.target.form) e.target.form.submit(); });

  /* Filas con data-open abren la ficha con clic o Enter. */
  document.addEventListener('keydown', e => {
    if (e.key === 'Enter' && e.target.matches('tr[data-panel],tr[data-href]')) { e.target.click(); }
  });

  /* ---------------- RT-09: Esc ---------------- */
  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    if (e.target.closest && e.target.closest('.ac,.combo') && !e.target.closest('.ac')?.querySelector('ul')?.hidden) { /* lo maneja la lista */ }
    if (e.target.type === 'search' && e.target.value) return;
    if (hayModal()) { e.preventDefault(); cerrarModal(); return; }
    if (hayPanel()) { e.preventDefault(); window.cerrarPanel(); return; }
    const c = $('[data-esc]');
    if (c) { e.preventDefault(); if (c.dataset.esc === 'reset') { c.closest('form')?.reset(); c.dispatchEvent(new Event('esc')); } else location.href = c.dataset.esc; return; }
    const ini = $('[data-esc-inicio]');
    if (ini) { e.preventDefault(); location.href = ini.getAttribute('href'); }
  });

  /* ---------------- CU-02: cierre por inactividad ---------------- */
  const minInact = +(($('meta[name=inactividad]') || {}).content || 0);
  if (minInact && csrf()) {
    let ultima = Date.now(), ultimaPing = Date.now();
    const act = () => {
      ultima = Date.now();
      // La actividad en la pantalla (clics, teclas, scroll) también mantiene viva la sesión en el servidor.
      if (Date.now() - ultimaPing > 60000) { ultimaPing = Date.now(); pedir('/api/actividad', { method: 'POST', body: (() => { const f = new FormData(); f.append('_csrf', csrf()); return f; })() }).catch(() => { }); }
    };
    ['click', 'keydown', 'input', 'scroll', 'touchstart'].forEach(ev => document.addEventListener(ev, act, { passive: true, capture: true }));
    setInterval(async () => {
      if (Date.now() - ultima > minInact * 60000) {
        try { const r = await pedir('/api/ping'); const j = await r.json(); if (j.resta > 0) return; } catch (e) { }
        location.href = '/ingreso?vencida=1';
      }
    }, 20000);
  }

  /* ---------------- formatos de campos (RT-02) ---------------- */
  const fmtDoc = v => { const d = String(v ?? '').replace(/\D/g, ''); if (!d) return ''; if (d.length <= 8) return Number(d).toLocaleString('es-AR'); if (d.length === 11) return `${d.slice(0, 2)}-${d.slice(2, 10)}-${d.slice(10)}`; return d; };
  const parseMonto = v => { const n = Number(String(v ?? '').replace(/\s|\$/g, '').replace(/\./g, '').replace(',', '.')); return isFinite(n) ? n : 0; };
  const fmtNum = n => (+n || 0).toLocaleString('es-AR', { maximumFractionDigits: 2 });
  const money = n => '$ ' + (Math.round(n * 100) / 100).toLocaleString('es-AR', { maximumFractionDigits: 2 });
  window.fmt = { fmtDoc, parseMonto, fmtNum, money, norm, MAY, esc };

  function formatos(root) {
    $$('input.mayus:not([data-fm])', root).forEach(i => { i.dataset.fm = 1; i.addEventListener('input', () => { const p = i.selectionStart; const v = MAY(i.value).replace(/\s{2,}/g, ' '); if (v !== i.value) { i.value = v; try { i.setSelectionRange(p, p); } catch (e) { } } }); });
    $$('input.docfmt:not([data-fm])', root).forEach(i => { i.dataset.fm = 1; if (i.value) i.value = fmtDoc(i.value); i.addEventListener('input', () => { const v = fmtDoc(i.value); if (v !== i.value) { i.value = v; try { i.setSelectionRange(v.length, v.length); } catch (e) { } } }); });
    $$('input.monto:not([data-bound])', root).forEach(i => {
      i.dataset.bound = 1;
      if (i.value !== '') i.value = fmtNum(parseMonto(i.value));
      i.addEventListener('input', () => {
        const pos = i.selectionStart ?? i.value.length; const before = i.value.slice(0, pos).replace(/[^\d,]/g, '').length;
        let v = i.value.replace(/[^\d,]/g, ''); const k = v.indexOf(','); let ent = k < 0 ? v : v.slice(0, k), dec = k < 0 ? null : v.slice(k + 1).replace(/,/g, '').slice(0, 2);
        ent = ent.replace(/^0+(?=\d)/, ''); const f = (ent ? Number(ent).toLocaleString('es-AR') : (dec !== null ? '0' : '')) + (dec !== null ? ',' + dec : '');
        i.value = f; let n = 0, p = 0; while (p < f.length && n < before) { if (/[\d,]/.test(f[p])) n++; p++; } try { i.setSelectionRange(p, p); } catch (e) { }
      });
    });
    ojitos(root);
    sugerencias(root);
  }

  /* Ojito en los campos de clave. */
  const EYE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
  const EYE_OFF = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18"/><path d="M10.6 5.1A10.4 10.4 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6A17 17 0 0 0 2 12s3.5 7 10 7a9.7 9.7 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>';
  function ojitos(root) {
    $$('input[type=password]:not([data-ojo])', root).forEach(i => {
      i.dataset.ojo = 1;
      const w = document.createElement('span'); w.className = 'pw'; i.parentNode.insertBefore(w, i); w.appendChild(i);
      const b = document.createElement('button'); b.type = 'button'; b.className = 'eye'; b.setAttribute('aria-label', 'Mostrar clave'); b.innerHTML = EYE; w.appendChild(b);
      b.onmousedown = e => e.preventDefault();
      b.onclick = () => { const ver = i.type === 'password'; i.type = ver ? 'text' : 'password'; b.innerHTML = ver ? EYE_OFF : EYE; b.setAttribute('aria-label', ver ? 'Ocultar clave' : 'Mostrar clave'); i.focus(); };
    });
  }

  /* ---------------- sugerencias (RT-01) ---------------- */
  function conSugerencias(inp, { fuente, elegir }) {
    if (!inp || inp.dataset.ac) return; inp.dataset.ac = 1;
    const w = document.createElement('span'); w.className = 'ac'; inp.parentNode.insertBefore(w, inp); w.appendChild(inp);
    const ul = document.createElement('ul'); ul.setAttribute('role', 'listbox'); ul.hidden = true; w.appendChild(ul);
    inp.setAttribute('role', 'combobox'); inp.setAttribute('aria-autocomplete', 'list'); inp.setAttribute('autocomplete', 'off');
    let act = -1, items = [], silencio = false, seq = 0;
    const cerrar = () => { ul.hidden = true; act = -1; };
    const pintar = () => { $$('li[data-i]', ul).forEach((li, i) => li.classList.toggle('act', i === act)); const a = ul.querySelector('.act'); if (a) a.scrollIntoView({ block: 'nearest' }); };
    const abrir = async () => {
      if (silencio || document.activeElement !== inp) return;
      const mi = ++seq; const r = await fuente(inp.value); if (mi !== seq) return;
      items = r; if (items === null) { cerrar(); return; }
      act = items.length ? 0 : -1;
      ul.innerHTML = items.map((x, i) => `<li role="option" data-i="${i}">${esc(x.label)}${x.sub ? `<small>${esc(x.sub)}</small>` : ''}</li>`).join('') || '<li style="cursor:default;color:var(--muted)">Sin coincidencias</li>';
      ul.hidden = false; pintar();
    };
    const tomar = x => { cerrar(); silencio = true; try { elegir(x, inp); } finally { silencio = false; } };
    inp.addEventListener('input', abrir);
    ul.addEventListener('mousedown', e => { const li = e.target.closest('li[data-i]'); if (li) { e.preventDefault(); tomar(items[+li.dataset.i]); } });
    inp.addEventListener('keydown', e => {
      if (ul.hidden) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); act = Math.min(items.length - 1, act + 1); pintar(); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); act = Math.max(0, act - 1); pintar(); }
      else if (e.key === 'Enter' || e.key === 'Tab') { if (items[act]) { if (e.key === 'Enter') e.preventDefault(); tomar(items[act]); } else cerrar(); }
      else if (e.key === 'Escape') { e.stopPropagation(); e.preventDefault(); cerrar(); }
    });
    inp.addEventListener('blur', () => setTimeout(cerrar, 150));
  }
  const cacheSug = {};
  async function jsonGet(url) { if (cacheSug[url]) return cacheSug[url]; const r = await pedir(url); const j = await r.json(); cacheSug[url] = j; return j; }
  function sugerencias(root) {
    /* Afiliados por nombre desde la primera letra (hasta 8, activos primero). */
    $$('input[data-sugerir=afiliados]', root).forEach(inp => conSugerencias(inp, {
      fuente: async q => { q = q.trim(); if (!q || /N°\s*\d+\s*$/.test(q) || !/[a-zñáéíóúü]/i.test(q)) return null; return (await jsonGet('/api/afiliados/sugerir?q=' + encodeURIComponent(q))).items; },
      elegir: (x, inp) => { inp.value = `${x.label} · N° ${x.nro}`; inp.dispatchEvent(new Event('input', { bubbles: true })); inp.dispatchEvent(new Event('change', { bubbles: true })); }
    }));
    /* Apellidos y nombres ya cargados en el padrón. */
    $$('input[data-sugerir=apellido],input[data-sugerir=nombre]', root).forEach(inp => conSugerencias(inp, {
      fuente: async q => { if (!q.trim()) return null; const r = (await jsonGet('/api/padron/' + inp.dataset.sugerir + '?q=' + encodeURIComponent(q))).items.filter(x => x.label !== MAY(q).trim()); return r.length ? r : null; },
      elegir: (x, inp) => { inp.value = x.label; inp.dispatchEvent(new Event('input', { bubbles: true })); inp.dispatchEvent(new Event('change', { bubbles: true })); }
    }));
    /* Proveedores de egresos. */
    $$('input[data-sugerir=proveedor]', root).forEach(inp => conSugerencias(inp, {
      fuente: async q => { if (!q.trim()) return null; const r = (await jsonGet('/api/egresos/proveedores?q=' + encodeURIComponent(q))).items; return r.length ? r : null; },
      elegir: (x, inp) => { inp.value = x.label; inp.dispatchEvent(new CustomEvent('proveedor', { bubbles: true, detail: x })); }
    }));
  }

  function preparar(root) { formatos(root || document); document.dispatchEvent(new CustomEvent('preparar', { detail: root || document })); }
  window.preparar = preparar;

  /* ---------------- ingreso: usuarios recordados en este equipo ---------------- */
  const RECK = 'integral-recordados';
  const recordados = () => { try { return JSON.parse(localStorage.getItem(RECK) || '[]'); } catch (e) { return []; } };
  function recordarUsuario(r) {
    if (!r) return;
    try { localStorage.setItem(RECK, JSON.stringify([r, ...recordados().filter(x => norm(x.nombre) !== norm(r.nombre))].slice(0, 15))); } catch (e) { }
  }
  function olvidar(n) { try { localStorage.setItem(RECK, JSON.stringify(recordados().filter(x => norm(x.nombre) !== norm(n)))); } catch (e) { } }
  function login() {
    const f = $('form[data-login]'); if (!f) return;
    const inp = $('#lg-u'), tab = $('#lg-tab'), ul = $('#lg-list'); let act = -1;
    inp.focus();
    const lista = todos => recordados().filter(u => todos || !inp.value || norm(u.nombre).startsWith(norm(inp.value)));
    tab.hidden = !recordados().length;
    const cerrar = () => { ul.hidden = true; tab.setAttribute('aria-expanded', 'false'); inp.setAttribute('aria-expanded', 'false'); };
    const abrir = todos => {
      const us = lista(todos); if (!us.length) { cerrar(); return; } act = -1;
      ul.innerHTML = us.map(u => `<li role="option" data-n="${esc(u.nombre)}" style="display:flex;justify-content:space-between;align-items:center;gap:8px"><span>${esc(u.nombre)}${u.etiqueta ? ` <small style="color:var(--muted)">· ${esc(u.etiqueta)}</small>` : ''}</span><button type="button" class="linklike" data-olvidar="${esc(u.nombre)}" title="Olvidar este usuario en esta computadora" aria-label="Olvidar ${esc(u.nombre)}" style="font-size:16px;line-height:1;padding:0 4px">×</button></li>`).join('');
      ul.hidden = false; tab.setAttribute('aria-expanded', 'true'); inp.setAttribute('aria-expanded', 'true');
    };
    const elegir = n => { inp.value = n; cerrar(); $('#lg-p').focus(); };
    tab.onclick = () => { if (ul.hidden) { abrir(true); inp.focus(); } else cerrar(); };
    inp.oninput = () => abrir(false);
    ul.onmousedown = e => { const ol = e.target.closest('[data-olvidar]'); if (ol) { e.preventDefault(); olvidar(ol.dataset.olvidar); abrir(true); if (!recordados().length) tab.hidden = true; return; } const li = e.target.closest('li[data-n]'); if (li) { e.preventDefault(); elegir(li.dataset.n); } };
    inp.onkeydown = e => {
      const items = $$('li[data-n]', ul);
      if (e.key === 'ArrowDown') { e.preventDefault(); if (ul.hidden) abrir(true); else act = Math.min(items.length - 1, act + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); act = Math.max(0, act - 1); }
      else if (e.key === 'Enter' && !ul.hidden && items[act]) { e.preventDefault(); elegir(items[act].dataset.n); return; }
      else if (e.key === 'Escape') { cerrar(); return; } else return;
      $$('li[data-n]', ul).forEach((li, i) => li.classList.toggle('act', i === act));
    };
    $('#lg-p').addEventListener('focus', cerrar);
  }

  preparar(document);
  login();
})();
