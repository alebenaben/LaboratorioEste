/* CU-06 · Nueva alta: familiares, categorías por plan, cuota estimada, promoción y avisos de documento. */
(function () {
  'use strict';
  const f = document.getElementById('falta'); if (!f) return;
  const D = JSON.parse(document.getElementById('alta-datos').textContent);
  const { money, esc } = window.fmt;
  const $ = id => document.getElementById(id);
  const plan = $('plan'), fams = $('fams'), est = $('cuota-est');
  let promo = D.promo; let nFam = 0;
  const precio = (c, p) => ((D.precios[p] || {})[c]) || 0;
  const catOpts = (ids, selv) => ids.map(c => { const pr = precio(c, plan.value); return `<option value="${c}" ${c === selv ? 'selected' : ''}>${esc(D.cats[c])} · ${pr ? money(pr) : 'precio a definir'}</option>`; }).join('');
  const individual = () => !!(D.planes[plan.value] || {}).individual;

  function filaFam(cat) {
    const i = nFam++, ap = $('t_apellido').value;
    const d = document.createElement('div');
    d.className = 'fam-row'; d.dataset.i = i;
    d.style.gridTemplateColumns = 'minmax(0,1.2fr) minmax(0,1.1fr) minmax(0,1.1fr) minmax(0,1fr) minmax(0,1fr) minmax(0,1.3fr) auto';
    d.innerHTML = `<input type="hidden" name="fam[${i}][_i]" value="${i}"><label class="f">Apellido<input id="f${i}_apellido" name="fam[${i}][apellido]" class="mayus" required autocomplete="off" data-sugerir="apellido" value="${esc(ap)}"></label>
      <label class="f">Primer nombre<input id="f${i}_nombre1" name="fam[${i}][nombre1]" class="mayus" required autocomplete="off" data-sugerir="nombre"></label>
      <label class="f">Segundo nombre<input id="f${i}_nombre2" name="fam[${i}][nombre2]" class="mayus" autocomplete="off" data-sugerir="nombre"></label>
      <label class="f">DNI o CUIT<input id="f${i}_doc" name="fam[${i}][doc]" class="docfmt" inputmode="numeric" required autocomplete="off"><small style="color:var(--bad)" data-aviso></small></label>
      <label class="f">Nacimiento<input id="f${i}_nac" name="fam[${i}][nac]" type="date" required max="${D.hoy}"></label>
      <label class="f">Categoría<select id="f${i}_categoria" name="fam[${i}][categoria]" data-cats="adic" data-valor="${cat || 'CON'}">${catOpts(D.catAdic, cat || 'CON')}</select></label>
      <button class="btn small danger" type="button" data-quitar>Quitar</button>`;
    fams.appendChild(d); window.preparar(d); avisoDoc(d.querySelector('[name$="[doc]"]'), false);
    return d;
  }
  function pintarFam() {
    const acc = $('fam-acc');
    if (individual()) {
      acc.innerHTML = `<span class="muted" style="font-size:14px">El ${esc(D.planes[plan.value].nombre)} es individual: no se suman integrantes.</span>`;
      fams.innerHTML = '<p class="muted" style="margin:0">Plan individual: solo el titular.</p>';
    } else {
      acc.innerHTML = '<button class="btn small" type="button" id="add-fam">Agregar familiar</button>';
      if (!fams.querySelector('.fam-row')) fams.innerHTML = '<p class="muted" style="margin:0" data-vacio>Sin familiares a cargo.</p>';
    }
  }
  function refrescarCats() {
    $$('select[data-cats]').forEach(s => { const v = s.value || s.dataset.valor; s.innerHTML = catOpts(s.dataset.cats === 'tit' ? D.catTit : D.catAdic, v); });
  }
  const $$ = s => [...f.querySelectorAll(s)];
  function estimar() {
    const cats = [$('t_categoria').value, ...(individual() ? [] : $$('select[data-cats=adic]').map(s => s.value))];
    const tot = cats.reduce((s, c) => s + precio(c, plan.value), 0);
    const falta = cats.some(c => !precio(c, plan.value));
    est.innerHTML = falta ? '<span style="color:var(--bad)">El plan todavía no tiene precio para todas las categorías</span>' : promo ? `${money(Math.round(tot * (100 - promo.pct) / 100))} <span class="muted" style="font-size:13px;font-weight:400">los primeros ${promo.meses === 1 ? 'mes' : promo.meses + ' meses'}, después ${money(tot)}</span>` : money(tot);
    $('promo-txt').textContent = promo ? `Descuento del ${promo.pct}% en la cuota durante ${promo.meses} ${promo.meses === 1 ? 'mes' : 'meses'}, desde el primer mes facturado.` : 'Sin promoción.';
    $('promo_pct').value = promo ? promo.pct : 0; $('promo_meses').value = promo ? promo.meses : 0;
    $('btn-promo').classList.toggle('danger', !!promo);
    $('promo-badge').innerHTML = promo ? `<span class="promo-badge"><span class="pb-t">Promoción</span><span class="pb-v">${promo.pct}% × ${promo.meses} ${promo.meses === 1 ? 'mes' : 'meses'}</span></span>` : '';
  }
  /* E1 / E2: documento ya activo en el padrón (se avisa al terminar de escribirlo). */
  function avisoDoc(inp, esTitular) {
    if (!inp) return;
    inp.addEventListener('blur', async () => {
      const d = inp.value.replace(/\D/g, ''); const av = esTitular ? $('t_doc_aviso') : inp.parentNode.querySelector('[data-aviso]') || inp.closest('label').querySelector('[data-aviso]');
      if (d.length < 6) { if (av) av.textContent = ''; return; }
      const r = await fetch('/api/afiliados/doc?doc=' + d, { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' });
      const j = await r.json();
      if (j.ya) {
        if (esTitular) { window.toast(`${j.ya.nombre} ya está en el padrón (afiliado N° ${j.ya.nro}). No hace falta darlo de alta.`, 'bad'); inp.classList.add('falta'); if (av) av.textContent = `Ya está afiliado (N° ${j.ya.nro})`; }
        else { inp.classList.add('falta'); if (av) av.textContent = 'Ya está afiliado'; }
      } else { inp.classList.remove('falta'); if (av) av.textContent = ''; }
    });
  }
  /* Modal de promoción (5 a 40 % durante 1, 2, 3 o 6 meses). */
  function modalPromo() {
    const pcts = [5, 10, 15, 20, 25, 30, 35, 40], ms = [1, 2, 3, 6];
    window.modal(`<form class="modal" id="fpromo" role="dialog" aria-modal="true" aria-label="Promoción"><h2>Promoción en la cuota</h2><p>Descuento en la cuota del grupo durante los primeros meses facturados.</p>
      <div class="form-grid" style="grid-template-columns:1fr 1fr"><label class="f">Descuento<select id="pp">${pcts.map(x => `<option ${promo && promo.pct === x ? 'selected' : ''} value="${x}">${x}%</option>`).join('')}</select></label>
      <label class="f">Durante<select id="pm">${ms.map(x => `<option ${promo && promo.meses === x ? 'selected' : (x === 3 && !promo ? 'selected' : '')} value="${x}">${x} ${x === 1 ? 'mes' : 'meses'}</option>`).join('')}</select></label></div>
      <div class="row" style="justify-content:space-between">${promo ? '<button class="btn danger" type="button" id="pq">Quitar promoción</button>' : '<span></span>'}<span class="row"><button class="btn" type="button" data-act="modal-no">Cancelar <kbd>Esc</kbd></button><button class="btn primary" type="submit">Aplicar</button></span></div></form>`);
    document.getElementById('fpromo').addEventListener('submit', e => { e.preventDefault(); promo = { pct: +document.getElementById('pp').value, meses: +document.getElementById('pm').value }; window.cerrarModal(); estimar(); });
    const q = document.getElementById('pq'); if (q) q.onclick = () => { promo = null; window.cerrarModal(); estimar(); };
  }

  f.addEventListener('click', e => {
    if (e.target.id === 'add-fam') { const v = fams.querySelector('[data-vacio]'); if (v) v.remove(); filaFam('CON'); estimar(); }
    if (e.target.matches('[data-quitar]')) { e.target.closest('.fam-row').remove(); pintarFam(); estimar(); }
    if (e.target.id === 'btn-promo') modalPromo();
  });
  plan.addEventListener('change', () => { refrescarCats(); pintarFam(); estimar(); });
  f.addEventListener('change', estimar); f.addEventListener('input', e => { if (e.target.matches('select')) estimar(); });
  $('sin_email').addEventListener('change', e => { const m = $('email'); m.disabled = e.target.checked; m.required = !e.target.checked; if (e.target.checked) m.value = ''; });
  avisoDoc($('t_doc'), true);
  /* Esc con datos cargados descarta el alta. */
  const cancelar = document.querySelector('[data-cancelar-alta]');
  cancelar.addEventListener('esc', () => { });

  $('t_categoria').innerHTML = catOpts(D.catTit, $('t_categoria').dataset.valor);
  pintarFam();
  if (!individual()) { const v = fams.querySelector('[data-vacio]'); if (D.fam.length && v) v.remove(); D.fam.forEach(c => filaFam(c)); }
  estimar();
})();
