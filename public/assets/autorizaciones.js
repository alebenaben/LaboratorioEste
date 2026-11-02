/* CU-20: verificación en el momento, emisión, rechazo y envío a auditoría. */
(function () {
  'use strict';
  const f = document.getElementById('faut'), res = document.getElementById('aut-res');
  if (!f) return;
  const { esc } = window.fmt;
  const datos = () => { const d = {}; new FormData(f).forEach((v, k) => { d[k] = v; }); return d; };
  let t = null, seq = 0;
  async function verificar() {
    const mi = ++seq, q = new URLSearchParams(datos());
    const r = await fetch('/autorizaciones/verificar?' + q, { headers: { 'X-Requested-With': 'fetch', Accept: 'text/html' }, credentials: 'same-origin' });
    if (r.status === 401) { location.href = '/ingreso?vencida=1'; return; }
    if (mi !== seq) return;
    res.innerHTML = await r.text(); window.preparar(res);
  }
  f.addEventListener('submit', e => e.preventDefault());
  f.addEventListener('input', () => { clearTimeout(t); t = setTimeout(verificar, 200); });
  f.addEventListener('change', () => { clearTimeout(t); t = setTimeout(verificar, 50); });
  res.addEventListener('click', async e => {
    const b = e.target.closest('[data-aut]'); if (!b) return;
    const acc = b.dataset.aut;
    if (acc === 'auditoria') {
      const oblig = b.dataset.obligatoria === '1';
      window.modal(`<form class="modal" id="faud" role="dialog" aria-modal="true" aria-label="Enviar a auditoría"><h2>Enviar a auditoría</h2>
        <label class="f">Observación para el auditor<textarea name="obs" id="obs" rows="4" placeholder="Por qué se pide, qué indicó el médico, etc." ${oblig ? 'required' : ''}></textarea></label>
        <p class="form-error" style="display:none"></p>
        <div class="row" style="justify-content:flex-end"><button class="btn" type="button" data-act="modal-no">Cancelar <kbd>Esc</kbd></button><button class="btn primary" type="submit">Enviar</button></div></form>`);
      document.getElementById('faud').addEventListener('submit', async ev => {
        ev.preventDefault();
        const j = await window.postJSON('/autorizaciones/auditoria', Object.assign(datos(), { obs: document.getElementById('obs').value }));
        if (!j.ok) { const er = document.querySelector('#faud .form-error'); er.textContent = j.error; er.style.display = ''; return; }
        location.href = j.redirect;
      });
      return;
    }
    b.disabled = true;
    const j = await window.postJSON('/autorizaciones', Object.assign(datos(), { accion: acc }));
    b.disabled = false;
    if (!j.ok) { window.toast(j.error, 'bad'); return; }
    location.href = j.redirect;
  });
  if (document.getElementById('aut-busca') && document.getElementById('aut-busca').value) verificar();
})();
