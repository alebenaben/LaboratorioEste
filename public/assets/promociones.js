/* CU-18: "Todos los afiliados" excluye los planes puntuales y vista previa del PDF sin guardar. */
(function () {
  const box = document.querySelector('[data-planes-promo]');
  if (box) {
    const todos = box.querySelector('[data-todos]'), pls = [...box.querySelectorAll('input[name="planes[]"]')];
    todos.addEventListener('change', () => { if (todos.checked) pls.forEach(x => { x.checked = false; }); else if (!pls.some(x => x.checked)) todos.checked = true; });
    pls.forEach(c => c.addEventListener('change', () => { if (c.checked) todos.checked = false; if (!pls.some(x => x.checked)) todos.checked = true; }));
  }
  const b = document.querySelector('[data-ver-borrador]');
  if (b) b.addEventListener('click', () => {
    const f = document.getElementById('pr-form'), q = new URLSearchParams();
    ['titulo', 'beneficio', 'unidad', 'desde', 'hasta', 'texto', 'cond'].forEach(k => q.set(k, f.elements[k].value));
    window.descargar('/pdf/promo-borrador?' + q);
  });
})();
