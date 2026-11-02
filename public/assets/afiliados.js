/* CU-05: filtro en el momento del padrón. */
(function () {
  const q = document.getElementById('q'), fe = document.getElementById('festado'), tb = document.getElementById('tb-af');
  if (!q || !tb) return;
  let t = null, seq = 0;
  const cargar = async () => {
    const mi = ++seq;
    const u = '/afiliados/tabla?q=' + encodeURIComponent(q.value) + '&estado=' + encodeURIComponent(fe.value);
    const r = await fetch(u, { headers: { 'X-Requested-With': 'fetch', Accept: 'text/html' }, credentials: 'same-origin' });
    if (r.status === 401) { location.href = '/ingreso?vencida=1'; return; }
    if (mi !== seq) return;
    tb.innerHTML = await r.text();
    try { history.replaceState(null, '', '/afiliados?q=' + encodeURIComponent(q.value) + '&estado=' + encodeURIComponent(fe.value)); } catch (e) { }
  };
  q.addEventListener('input', () => { clearTimeout(t); t = setTimeout(cargar, 120); });
  fe.addEventListener('change', cargar);
  q.addEventListener('keydown', e => { if (e.key === 'Enter') { const f = tb.querySelector('tr[data-panel]'); if (f) f.click(); } });
  const ab = document.querySelector('[data-abrir-ficha]');
  if (ab) window.abrirPanel('/afiliados/' + ab.dataset.abrirFicha + '/ficha');
})();
