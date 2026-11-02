/* CU-12: elección del motivo, guion y secciones de la guía. */
(function () {
  const box = document.querySelector('[data-ret][data-guiones]'); if (!box) return;
  const G = JSON.parse(box.dataset.guiones), { esc } = window.fmt;
  const guion = box.querySelector('[data-guion]'), ofr = box.querySelector('[data-ofrecer]'), como = box.querySelector('[data-como]');
  const elegir = m => {
    box.querySelectorAll('[data-motivo]').forEach(b => b.classList.toggle('primary', b.dataset.motivo === m));
    const g = G[m];
    guion.hidden = false; como.hidden = false; ofr.hidden = m === 'fallecimiento';
    guion.innerHTML = `<p class="at-paso">4 · Qué decirle</p><div style="font-size:16px;font-weight:600;margin-bottom:8px">“${esc(g.abre)}”</div>` +
      g.dec.map(t => `<div style="font-size:15px;padding:4px 0 4px 12px;border-left:3px solid var(--accent);margin:4px 0">${esc(t)}</div>`).join('') +
      `<div class="muted" style="font-size:13px;margin-top:10px">💡 Escuchalo hasta el final, usá su nombre y no prometas nada que no esté en esta pantalla.${g.evitar ? ' ' + esc(g.evitar) : ''}</div>`;
    box.dispatchEvent(new CustomEvent('motivo', { detail: m }));
  };
  box.querySelectorAll('[data-motivo]').forEach(b => b.addEventListener('click', () => elegir(b.dataset.motivo)));
  const ini = JSON.parse(box.dataset.ret).motivo; if (ini) elegir(ini);
  /* Al elegir un afiliado de las sugerencias se busca solo. */
  const q = document.querySelector('input[data-auto-submit]');
  if (q) q.addEventListener('change', () => { if (/N°\s*\d+\s*$/.test(q.value)) q.form.submit(); });
})();
