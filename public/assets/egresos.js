/* CU-33: IVA solo con Factura A (botones 21 % y 10,5 %) y proveedor con sugerencias que completa los datos. */
(function () {
  const f = document.getElementById('feg'); if (!f) return;
  const { parseMonto, fmtNum } = window.fmt;
  const t = document.getElementById('comprobante'), il = document.getElementById('iva-l'), iva = document.getElementById('iva'), imp = document.getElementById('importe');
  const tg = () => { il.hidden = t.value !== 'Factura A'; if (il.hidden) iva.value = ''; };
  t.addEventListener('change', tg); tg();
  f.querySelectorAll('[data-egiva]').forEach(b => b.addEventListener('click', () => { const a = +b.dataset.egiva, v = parseMonto(imp.value); iva.value = v ? fmtNum(Math.round(v * a / (100 + a) * 100) / 100) : ''; }));
  f.addEventListener('proveedor', e => { const x = e.detail; document.getElementById('categoria').value = x.categoria; document.getElementById('cuit').value = x.cuit || ''; t.value = x.comprobante; tg(); });
})();
