/* CU-15 · Cotizador: integrantes, cuota por plan, promoción, envío por WhatsApp e inicio del alta. */
(function () {
  'use strict';
  const D = JSON.parse(document.getElementById('cot-datos').textContent);
  const { money, esc, norm, MAY } = window.fmt;
  const $ = id => document.getElementById(id);
  const st = { plan: 'PLUS', cats: ['TIT', 'CON', 'HIJ'], promo: null };
  const precio = (c, p) => ((D.precios[p] || {})[c]) || 0;
  const plan = id => D.planes.find(p => p.id === id);
  const NOM = new Set(D.nombres);

  /* Separa apellido y nombres aunque se escriban al revés (misma regla que el servidor). */
  function partir(txt) {
    const w = MAY(txt).trim().split(/\s+/).filter(Boolean); if (!w.length) return null;
    const esNom = x => NOM.has(norm(x));
    if (w.length === 1) return esNom(w[0]) ? { apellido: '', nombres: w } : { apellido: w[0], nombres: [] };
    if (esNom(w[0]) && !esNom(w[w.length - 1])) return { apellido: w[w.length - 1], nombres: w.slice(0, -1) };
    return { apellido: w[0], nombres: w.slice(1) };
  }
  const telWa = t => { let d = String(t || '').replace(/\D/g, ''); if (!d) return ''; if (d.startsWith('54')) { d = d.slice(2); if (d.startsWith('9')) d = d.slice(1); } d = d.replace(/^0/, ''); const m = d.match(/^(\d{2,4})15(\d{6,8})$/); if (m && (m[1] + m[2]).length === 10) d = m[1] + m[2]; return d.length === 10 ? '549' + d : d; };

  function pintar() {
    document.querySelectorAll('[data-cot-plan]').forEach(b => b.setAttribute('aria-pressed', b.dataset.cotPlan === st.plan));
    $('cot-list').innerHTML = st.cats.map((c, i) => `<div class="row" style="flex-wrap:nowrap"><select data-ci="${i}" style="flex:1 1 0" aria-label="Categoría integrante ${i + 1}">${Object.keys(D.cats).filter(k => i === 0 ? ['TIT', 'TIT64'].includes(k) : !['TIT', 'TIT64'].includes(k)).map(k => { const pr = precio(k, st.plan); return `<option value="${k}" ${k === c ? 'selected' : ''}>${esc(D.cats[k])} · ${pr ? money(pr) : 'precio a definir'}</option>`; }).join('')}</select><button class="btn small danger" type="button" data-del="${i}" ${st.cats.length < 2 || i === 0 ? 'disabled' : ''}>Quitar</button></div>`).join('');
    $('cot-indiv').hidden = !plan(st.plan).individual;
    $('cot-plans').innerHTML = D.planes.map(p => {
      const cs = p.individual ? st.cats.slice(0, 1) : st.cats, falta = cs.some(c => !precio(c, p.id)), tot = cs.reduce((s, c) => s + precio(c, p.id), 0);
      return `<div class="plan-card ${p.id === st.plan ? 'sel' : ''}" data-act-plan="${p.id}" role="button" tabindex="0"><h3>${esc(p.nombre)}</h3>
        ${falta ? '<b class="muted">Precio a definir</b>' : st.promo ? `<b class="num">${money(Math.round(tot * (100 - st.promo.pct) / 100))}</b><div class="muted" style="font-size:13px">los primeros ${st.promo.meses === 1 ? 'mes' : st.promo.meses + ' meses'}, después ${money(tot)}</div>` : `<b class="num">${money(tot)}</b>`}
        <ul>${cs.map(c => `<li>${esc(D.cats[c])}: ${precio(c, p.id) ? money(precio(c, p.id)) : 'a definir'}</li>`).join('')}</ul>${p.individual && st.cats.length > 1 ? '<div class="muted" style="font-size:12.5px">Plan individual: solo el titular</div>' : ''}</div>`;
    }).join('');
    $('btn-promo').classList.toggle('danger', !!st.promo);
    $('promo-badge').innerHTML = st.promo ? `<span class="promo-badge"><span class="pb-t">Promoción</span><span class="pb-v">${st.promo.pct}% × ${st.promo.meses} ${st.promo.meses === 1 ? 'mes' : 'meses'}</span></span>` : '';
  }
  document.addEventListener('click', e => {
    const pb = e.target.closest('[data-cot-plan],[data-act-plan]');
    if (pb) { st.plan = pb.dataset.cotPlan || pb.dataset.actPlan; pintar(); return; }
    const del = e.target.closest('[data-del]'); if (del) { st.cats.splice(+del.dataset.del, 1); pintar(); return; }
    if (e.target.id === 'cot-add') { st.cats.push('CON'); pintar(); return; }
    if (e.target.id === 'btn-promo') { promoModal(); return; }
    if (e.target.id === 'cot-iniciar') { const q = new URLSearchParams({ plan: st.plan, cats: st.cats.join(','), pct: st.promo ? st.promo.pct : '', meses: st.promo ? st.promo.meses : '' }); location.href = '/altas?' + q; return; }
    if (e.target.id === 'wa-cot') { enviar(); return; }
    const wp = e.target.closest('[data-wa-plan]');
    if (wp) { const p = plan(wp.dataset.waPlan), cs = p.individual ? st.cats.slice(0, 1) : st.cats, tot = cs.reduce((s, c) => s + precio(c, p.id), 0); waPlan(p.id, cs.some(c => !precio(c, p.id)) ? '' : money(tot)); }
  });
  document.addEventListener('change', e => { if (e.target.matches('[data-ci]')) { st.cats[+e.target.dataset.ci] = e.target.value; pintar(); } });
  async function waPlan(id, cot) {
    const j = await window.postJSON('/cotizador/plan/' + id + '/whatsapp', { cot, celular: $('cot-tel').value });
    if (j.ok) window.abrirWhatsApp(j.whatsapp); else window.toast(j.error, 'bad');
  }
  async function enviar() {
    ['cot-nom', 'cot-tel'].forEach(i => $(i).classList.remove('falta'));
    const j = await window.postJSON('/cotizador/enviar', { plan: st.plan, cats: st.cats.join(','), pct: st.promo ? st.promo.pct : '', meses: st.promo ? st.promo.meses : '', nombre: $('cot-nom').value, celular: $('cot-tel').value });
    if (!j.ok) { (j.campos || []).forEach(i => $(i) && $(i).classList.add('falta')); window.toast(j.error, 'bad'); return; }
    window.toast(j.mensaje); window.abrirWhatsApp(j.whatsapp);
  }
  function promoModal() {
    const pcts = [5, 10, 15, 20, 25, 30, 35, 40], ms = [1, 2, 3, 6], pr = st.promo;
    window.modal(`<form class="modal" id="fpromo" role="dialog" aria-modal="true" aria-label="Promoción"><h2>Promoción en la cuota</h2><p>Descuento durante los primeros meses.</p>
      <div class="form-grid" style="grid-template-columns:1fr 1fr"><label class="f">Descuento<select id="pp">${pcts.map(x => `<option ${pr && pr.pct === x ? 'selected' : ''} value="${x}">${x}%</option>`).join('')}</select></label>
      <label class="f">Durante<select id="pm">${ms.map(x => `<option ${(pr ? pr.meses === x : x === 3) ? 'selected' : ''} value="${x}">${x} ${x === 1 ? 'mes' : 'meses'}</option>`).join('')}</select></label></div>
      <div class="row" style="justify-content:space-between">${pr ? '<button class="btn danger" type="button" id="pq">Quitar promoción</button>' : '<span></span>'}<span class="row"><button class="btn" type="button" data-act="modal-no">Cancelar <kbd>Esc</kbd></button><button class="btn primary" type="submit">Aplicar</button></span></div></form>`);
    document.getElementById('fpromo').addEventListener('submit', e => { e.preventDefault(); st.promo = { pct: +document.getElementById('pp').value, meses: +document.getElementById('pm').value }; window.cerrarModal(); pintar(); });
    const q = document.getElementById('pq'); if (q) q.onclick = () => { st.promo = null; window.cerrarModal(); pintar(); };
  }
  $('cot-nom').addEventListener('input', () => { const p = partir($('cot-nom').value); $('cot-nom-det').textContent = p ? [p.apellido ? 'Apellido: ' + p.apellido : '', p.nombres.length ? 'Nombre: ' + p.nombres.join(' ') : ''].filter(Boolean).join(' · ') : ''; });
  $('cot-tel').addEventListener('input', () => { const v = $('cot-tel').value, t = telWa(v); $('cot-tel-det').textContent = !v.trim() ? '' : /^549\d{10}$/.test(t) ? '✓ +54 9 ' + t.slice(3, 6) + ' ' + t.slice(6) : 'Faltan números'; });
  window.partirInteresado = partir; window.telWa = telWa;
  pintar();
})();
