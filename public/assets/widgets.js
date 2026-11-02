/* Comportamientos de pantallas que se cargan dentro de modales y paneles (se activan con el evento "preparar"). */
(function () {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const { esc, money, parseMonto, fmtNum } = window.fmt;
  const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
  const perLabel = p => { const [y, m] = p.split('-'); return `${MESES[m - 1]} ${y}`; };
  const perMas = (p, n) => { const [y, m] = p.split('-').map(Number); const t = y * 12 + (m - 1) + n; return `${Math.floor(t / 12)}-${String(t % 12 + 1).padStart(2, '0')}`; };
  window.perLabel = perLabel; window.perMas = perMas;

  /* CU-08: al cambiar la categoría o el plan se propone la cuota del nuevo precio. */
  function fichaEdicion(root) {
    $$('form', root).forEach(f => {
      const s = f.querySelector('script[data-precios]'); if (!s || f.dataset.prep) return; f.dataset.prep = 1;
      const precios = JSON.parse(s.textContent);
      const cat = f.querySelector('[data-precio-cat]'), pl = f.querySelector('[data-precio-plan]'), cuota = f.querySelector('[name=cuota]');
      const plan0 = pl ? pl.value : null;
      const upd = () => { const p = pl ? pl.value : (f.dataset.plan || plan0); const v = ((precios[p] || {})[cat.value]) || 0; if (v && cuota) cuota.value = fmtNum(v); };
      if (cat) cat.addEventListener('change', upd);
      if (pl) pl.addEventListener('change', upd);
    });
  }

  /* CU-09: texto de cuánto pagará el grupo con la promoción. */
  function promoGrupo(root) {
    $$('form[data-promo-grupo]', root).forEach(f => {
      if (f.dataset.prep) return; f.dataset.prep = 1;
      const base = +f.dataset.base, txt = f.querySelector('[data-promo-txt]');
      const upd = () => {
        const pct = +f.pct.value, m = +f.meses.value, d = f.desde.value, h = perMas(d, m - 1);
        txt.innerHTML = `Paga <b>${money(Math.round(base * (100 - pct) / 100))}</b> de ${perLabel(d)} a ${perLabel(h)}; después ${money(base)}.`;
      };
      f.addEventListener('change', upd); upd();
    });
  }

  /* CU-10 / CU-12: propuestas de retención de a una y en orden. */
  function retencion(root) {
    $$('[data-ret]', root).forEach(box => {
      if (box.dataset.prep) return; box.dataset.prep = 1;
      const cfg = JSON.parse(box.dataset.ret);
      const st = { motivo: cfg.motivo || '', paso: 0, res: [] };
      const pasos = box.querySelector('[data-ret-pasos]'), fin = box.querySelector('[data-ret-fin]');
      const mot = box.querySelector('[data-ret-motivo]'), com = box.querySelector('[data-ret-com]');
      const enviar = async (accion, extra = {}) => {
        const j = await window.postJSON('/retencion/registrar', Object.assign({ afiliado: cfg.afiliado, motivo: st.motivo, comentario: com ? com.value : '', paso: st.paso, accion, origen: cfg.origen }, extra));
        if (!j.ok) { window.toast(j.error, 'bad'); return; }
        window.toast(j.mensaje);
        if (cfg.origen === 'ficha') { window.cerrarModal(); window.abrirPanel('/afiliados/' + cfg.afiliado + '/ficha'); document.body.dataset.refrescar = 1; }
        else location.href = '/retencion?fin=' + encodeURIComponent(j.resultado) + '&a=' + cfg.afiliado;
      };
      const pintar = () => {
        const L = (cfg.ofertas || {})[st.motivo] || [];
        if (!st.motivo) { pasos.innerHTML = ''; fin.innerHTML = ''; return; }
        if (st.motivo === 'fallecimiento') {
          pasos.innerHTML = cfg.origen === 'ficha' ? '<div class="verdict warn" style="font-size:14px">No se ofrece nada. Si tenía seguro de sepelio, acompañá a la familia con el servicio.</div>' : '';
        } else {
          pasos.innerHTML = (cfg.prev ? `<div class="verdict warn" style="font-size:13.5px;margin-bottom:8px">Ya tuvo una propuesta de retención el ${esc(cfg.prev)}: no se le ofrecen descuentos en la cuota otra vez.</div>` : '') +
            `<div style="display:flex;flex-direction:column;gap:8px">${L.map((o, i) => {
              const r = st.res[i], puede = cfg.admin || ['charla', 'regalo'].includes(o.tipo);
              let acc = '';
              if (r) acc = `<div style="font-size:13px;margin-top:4px;color:var(--muted)">✕ No le interesó</div>`;
              else if (i === st.paso) acc = puede
                ? `<div class="row" style="gap:8px;margin-top:8px"><button class="btn small primary" type="button" data-r="acepto">Aceptó, se queda</button><button class="btn small" type="button" data-r="no">No le interesó, ver lo siguiente</button></div>`
                : `<div style="font-size:13px;margin-top:6px;color:var(--logo-red)">Esto lo decide administración. Decile: “Dejame consultarlo y te llamamos”.</div><div class="row" style="gap:8px;margin-top:8px"><button class="btn small primary" type="button" data-r="derivar">Pasar a administración</button><button class="btn small" type="button" data-r="no">No le interesa, ver lo siguiente</button></div>`;
              else if (i > st.paso) acc = '<div class="muted" style="font-size:12.5px">Se habilita si no acepta el paso anterior.</div>';
              return `<div class="ret-paso ${i === st.paso ? 'act' : ''} ${r ? 'hecho' : ''}" ${i > st.paso ? 'style="opacity:.45"' : ''}><b>Paso ${i + 1}: ${esc(o.t)}</b><div style="font-size:13.5px">${esc(o.d)}</div>${acc}</div>`;
            }).join('')}</div>` + (L.length ? '' : '<p class="muted" style="margin:0">No hay propuestas para este caso.</p>');
        }
        const todas = st.motivo === 'fallecimiento' || st.paso >= L.length;
        if (cfg.origen === 'ficha') {
          fin.innerHTML = todas ? '<button class="btn danger" type="button" data-r="baja">Dar de baja igual</button>' : '<span class="muted" style="font-size:13px">Ofrecé las propuestas en orden antes de dar de baja.</span>';
        } else {
          const b = todas || st.paso > 0 ? (cfg.admin ? '<button class="btn danger" type="button" data-r="baja">Insiste: darlo de baja</button>' : '<button class="btn danger" type="button" data-r="pedir_baja">Insiste en darse de baja</button>') : '';
          fin.innerHTML = `<button class="btn" type="button" data-r="piensa">Lo va a pensar (llamarlo en 3 días)</button>${b}`;
        }
      };
      box.addEventListener('click', e => {
        const b = e.target.closest('[data-r]'); if (!b) return;
        const r = b.dataset.r;
        if (r === 'no') { st.res[st.paso] = 'no'; st.paso++; pintar(); return; }
        if (r === 'baja' && !confirm('¿Confirmás la baja con fecha de hoy?')) return;
        enviar(r);
      });
      if (mot) mot.addEventListener('change', () => { st.motivo = mot.value; st.paso = 0; st.res = []; pintar(); });
      box.addEventListener('motivo', e => { st.motivo = e.detail; st.paso = 0; st.res = []; pintar(); });
      pintar();
    });
  }

  document.addEventListener('preparar', e => { const r = e.detail || document; fichaEdicion(r); promoGrupo(r); retencion(r); });
  fichaEdicion(document); promoGrupo(document); retencion(document);

  /* Si se cambió algo dentro del panel lateral, al cerrarlo se actualiza la lista. */
  const cp = window.cerrarPanel;
  window.cerrarPanel = function () { cp(); if (document.body.dataset.refrescar) { delete document.body.dataset.refrescar; location.reload(); } };
  document.addEventListener('ajax:ok', e => { if (e.detail && (e.detail.refrescar || e.detail.panel)) document.body.dataset.refrescar = 1; });
})();
