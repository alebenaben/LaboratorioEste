/* Caja: al cambiar el mes se recalcula lo que falta facturar. */
(function () {
  const p = document.querySelector('[data-ir-periodo]');
  if (p) p.addEventListener('change', () => { if (p.value) location.href = '/caja?per=' + p.value; });
})();
