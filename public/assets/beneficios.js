/* CU-16: buscador y filtro de beneficios. CU-17: enviar información a una persona. */
(function () {
  'use strict';
  const { esc, norm, MAY } = window.fmt;
  const $ = id => document.getElementById(id);
  const D = JSON.parse($('ben-datos').textContent);
  const NOM = new Set(D.nombres);
  const partir = txt => { const w = MAY(txt).trim().split(/\s+/).filter(Boolean); if (!w.length) return null; const es = x => NOM.has(norm(x)); if (w.length === 1) return es(w[0]) ? { apellido: '', nombres: w } : { apellido: w[0], nombres: [] }; if (es(w[0]) && !es(w[w.length - 1])) return { apellido: w[w.length - 1], nombres: w.slice(0, -1) }; return { apellido: w[0], nombres: w.slice(1) }; };
  const telWa = t => { let d = String(t || '').replace(/\D/g, ''); if (!d) return ''; if (d.startsWith('54')) { d = d.slice(2); if (d.startsWith('9')) d = d.slice(1); } d = d.replace(/^0/, ''); const m = d.match(/^(\d{2,4})15(\d{6,8})$/); if (m && (m[1] + m[2]).length === 10) d = m[1] + m[2]; return d.length === 10 ? '549' + d : d; };

  /* Filtro: todas las palabras; con plan elegido se ocultan las otras columnas. */
  function filtrar() {
    const toks = norm($('bq').value).split(/\s+/).filter(Boolean), pf = $('bpl').value;
    document.querySelectorAll('#tb-benef [data-col]').forEach(c => { c.hidden = !!pf && c.dataset.col !== pf; });
    let n = 0;
    document.querySelectorAll('#tb-benef tbody tr[data-txt]').forEach(tr => {
      const enPlan = !pf || tr.dataset.planes.split(',').includes(pf);
      const txt = pf ? tr.querySelector(`td[data-col="${pf}"]`).dataset.txt : tr.dataset.txt;
      const ok = enPlan && toks.every(t => txt.includes(t)); tr.hidden = !ok; if (ok) n++;
    });
    const s = $('sin-benef'); s.hidden = n > 0; s.firstElementChild.textContent = `No hay beneficios que coincidan con "${$('bq').value}".`;
  }
  $('bq').addEventListener('input', filtrar); $('bpl').addEventListener('change', filtrar);

  /* Enviar información. */
  let tipo = 'int', afi = null;
  document.querySelectorAll('[data-at-tipo]').forEach(b => b.addEventListener('click', () => {
    tipo = b.dataset.atTipo;
    document.querySelectorAll('[data-at-tipo]').forEach(x => x.classList.toggle('on', x === b));
    document.querySelectorAll('[data-at]').forEach(x => { x.hidden = x.dataset.at !== tipo; });
    $('pdf-tit').textContent = tipo === 'afi' ? 'Mis beneficios' : 'Planes de Integral del Este';
    $('pdf-sub').textContent = tipo === 'afi' ? 'Beneficios disponibles, lo usado y el ahorro del año.' : 'Cuotas y todos los beneficios de los planes elegidos.';
  }));
  $('at-nom').addEventListener('input', () => { const p = partir($('at-nom').value); $('at-nom-det').textContent = p ? [p.apellido ? 'Apellido: ' + p.apellido : '', p.nombres.length ? 'Nombre: ' + p.nombres.join(' ') : ''].filter(Boolean).join(' · ') : ''; });
  const telDet = () => { const v = $('at-tel').value, t = telWa(v); $('at-tel-det').textContent = !v.trim() ? '' : /^549\d{10}$/.test(t) ? '✓ +54 9 ' + t.slice(3, 6) + ' ' + t.slice(6) : 'Faltan números'; };
  $('at-tel').addEventListener('input', telDet);
  let tb = null;
  $('at-busca').addEventListener('input', () => {
    clearTimeout(tb); tb = setTimeout(async () => {
      const q = $('at-busca').value.trim(), qn = $('at-quien');
      if (!q) { afi = null; qn.innerHTML = ''; return; }
      const r = await fetch('/api/beneficios/afiliado?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' });
      const j = await r.json(); afi = j.a;
      if (afi) { qn.innerHTML = `✓ <b>${esc(afi.nombre)}</b> · N° ${afi.nro} · ${esc(afi.plan)}${afi.activo ? '' : ' (baja)'}<div class="muted" style="font-size:13px">Se le manda el PDF con sus beneficios, lo que usó y lo que le queda.</div>`; if (!$('at-tel').value && afi.tel) { $('at-tel').value = afi.tel; telDet(); } }
      else qn.innerHTML = /[a-z]/i.test(q) && !/\d/.test(q) ? '<span class="muted">Elegí de la lista que aparece.</span>' : '<span style="color:var(--bad)">No se encontró ese afiliado.</span>';
    }, 200);
  });
  document.querySelectorAll('[data-at-enviar]').forEach(b => b.addEventListener('click', async () => {
    ['at-nom', 'at-tel', 'at-busca', 'at-planes'].forEach(i => $(i).classList.remove('falta'));
    const planes = [...document.querySelectorAll('[data-atpl]:checked')].map(x => x.dataset.atpl);
    const fd = new FormData(); fd.append('tipo', tipo); fd.append('modo', b.dataset.atEnviar); fd.append('nombre', $('at-nom').value); fd.append('celular', $('at-tel').value); fd.append('afiliado', afi ? 'N° ' + afi.nro : '');
    planes.forEach(p => fd.append('planes[]', p));
    const j = await window.postJSON('/beneficios/enviar', fd);
    if (!j.ok) { (j.campos || []).forEach(i => $(i) && $(i).classList.add('falta')); window.toast(j.error, 'bad'); return; }
    if (j.descargar) { window.descargar(j.descargar); return; }
    window.toast(j.mensaje); await window.abrirWhatsApp(j.whatsapp);
    $('at-nom').value = ''; $('at-tel').value = ''; $('at-busca').value = ''; afi = null; $('at-quien').innerHTML = ''; $('at-nom-det').textContent = ''; telDet();
  }));
})();
