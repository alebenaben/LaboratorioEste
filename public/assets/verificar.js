/* CU-22 / CU-23: verificar afiliado en unidades y comercios. Con 7 o más dígitos verifica solo. */
(function () {
  'use strict';
  const f = document.getElementById('fver'), i = document.getElementById('ver-q'), box = document.getElementById('ver-res');
  if (!f) return;
  const modo = f.dataset.modo, { fmtDoc, parseMonto, money } = window.fmt;
  async function mostrar() {
    const q = i.value.trim();
    if (!q) { box.innerHTML = ''; return; }
    const r = await fetch('/verificar/' + modo + '?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'fetch', Accept: 'text/html' }, credentials: 'same-origin' });
    if (r.status === 401) { location.href = '/ingreso?vencida=1'; return; }
    box.innerHTML = await r.text(); window.preparar(box); calc();
  }
  /* CU-23: el porcentaje no supera el máximo del rubro; muestra ahorro y "Cobrar". */
  function calc() {
    const fc = document.getElementById('fcons'); if (!fc) return;
    const imp = document.getElementById('importe'), pi = document.getElementById('cs-pct'), out = document.getElementById('cs-tot'), max = +fc.dataset.max;
    const upd = () => { let pct = Math.max(0, parseFloat(pi.value) || 0); if (max && pct > max) { pct = max; pi.value = pct; } const v = parseMonto(imp.value), ah = Math.round(v * pct) / 100; out.innerHTML = v ? `Ahorro: <b>${money(ah)}</b> · Cobrar: <b style="color:var(--ink);font-size:17px">${money(v - ah)}</b>` : ''; };
    fc.addEventListener('input', upd); upd();
  }
  f.addEventListener('submit', e => { e.preventDefault(); mostrar(); });
  i.addEventListener('input', () => {
    const v = i.value;
    if (!v.trim()) { box.innerHTML = ''; return; }
    if (/N°\s*\d+\s*$/.test(v)) { mostrar(); return; }
    if (/^[\d.\-\s]+$/.test(v)) {
      const d = v.replace(/\D/g, ''), fv = d.length === 11 ? fmtDoc(d) : d.length > 3 ? Number(d).toLocaleString('es-AR') : d;
      if (modo === 'unidad' && fv !== v) { i.value = fv; }
      if (d.length >= 7) mostrar();
    }
  });
  i.addEventListener('change', () => { if (/N°\s*\d+\s*$/.test(i.value)) mostrar(); });
  /* Esc limpia la búsqueda para verificar a otro afiliado. */
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && !document.querySelector('#layer2 .overlay')) { i.value = ''; box.innerHTML = ''; i.focus(); } });
})();
