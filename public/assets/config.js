/* Configuración: campos modificados en amarillo, alta de usuarios e importación de precios (CU-41). */
(function () {
  'use strict';
  const { esc, money, parseMonto, fmtNum } = window.fmt;
  const D = JSON.parse(document.getElementById('cfg-datos').textContent);

  /* Mientras se edita, los campos modificados se marcan (CU-49). */
  document.querySelectorAll('form[data-marcar]').forEach(f => f.querySelectorAll('input,select,textarea').forEach(i => {
    if (i.type === 'hidden') return;
    const ini = i.type === 'checkbox' ? i.checked : i.value;
    const chk = () => { const now = i.type === 'checkbox' ? i.checked : i.value; (i.type === 'checkbox' ? i.parentElement : i).classList.toggle('cambiado', now !== ini); };
    i.addEventListener('input', chk); i.addEventListener('change', chk);
  }));

  /* Nuevo usuario: campos según el tipo. */
  const tipo = document.querySelector('[data-nu-tipo]');
  if (tipo) { const upd = () => document.querySelectorAll('[data-nu]').forEach(x => { x.hidden = x.dataset.nu !== tipo.value; }); tipo.addEventListener('change', upd); upd(); }

  /* ---------------- CU-41: importar precios ---------------- */
  const btn = document.querySelector('[data-importar]'), file = document.getElementById('imp-file');
  if (!btn) return;
  let im = null;
  btn.addEventListener('click', () => file.click());
  file.addEventListener('change', async () => {
    if (!file.files.length) return;
    window.toast('Leyendo ' + file.files[0].name + '…');
    const fd = new FormData(); fd.append('archivo', file.files[0]);
    const j = await window.postJSON('/config/importar/leer', fd);
    file.value = '';
    if (!j.ok) { window.modal(`<div class="modal" role="dialog" aria-modal="true"><h2>${/No encontré/.test(j.error) ? 'No encontré precios' : 'No se pudo leer el archivo'}</h2><p>${esc(j.error)}</p><p class="muted">Si la lista es una foto o un PDF escaneado, pasala a Excel y volvé a intentar.</p><div class="row" style="justify-content:flex-end"><button class="btn primary" type="button" data-act="modal-no">Entendido</button></div></div>`); return; }
    im = Object.assign(j.imp, { col: 0, quitar: {} });
    pintar();
  });
  const prest = cod => D.prest.find(p => p.cod === cod);
  async function relacionar() {
    const j = await window.postJSON('/config/importar/relacionar', { reng: JSON.stringify(im.reng), col: im.col, unidad: im.unidad });
    if (j.ok) { im.filas = j.filas; pintar(); }
  }
  function pintar() {
    const maxCols = Math.max(1, ...im.reng.map(r => r.montos.length));
    const usados = new Set(im.filas.filter(f => f.dest !== 'nueva' && f.dest !== 'no').map(f => f.dest));
    const faltan = D.prest.filter(p => p.activo && (im.unidad === '*' || p.unidad === im.unidad) && !usados.has(p.cod));
    const c = { cambia: 0, igual: 0, nueva: 0 };
    im.filas.forEach(f => { if (f.dest === 'nueva') c.nueva++; else if (f.dest !== 'no') { const p = prest(f.dest); p && p.valor === f.precio ? c.igual++ : c.cambia++; } });
    const opc = sel => `<option value="nueva" ${sel === 'nueva' ? 'selected' : ''}>➕ Agregar como nueva</option><option value="no" ${sel === 'no' ? 'selected' : ''}>No usar este renglón</option>` +
      D.unidades.map(u => `<optgroup label="${esc(u)}">${D.prest.filter(p => p.unidad === u).map(p => `<option value="${p.cod}" ${sel === p.cod ? 'selected' : ''}>${esc(p.nombre)}${p.activo ? '' : ' (quitada)'}</option>`).join('')}</optgroup>`).join('');
    window.modal(`<div class="modal" role="dialog" aria-modal="true" aria-label="Actualizar prestaciones" style="width:min(1060px,100%)" id="imp-modal">
      <h2>Actualizar prestaciones desde ${esc(im.archivo)}</h2>
      <p style="margin:0">Encontré <b>${im.reng.length}</b> ${im.reng.length === 1 ? 'renglón' : 'renglones'} con precio. Revisá que cada uno corresponda a la prestación correcta; podés cambiar la relación y el precio antes de aplicar.</p>
      <div class="row" style="gap:16px;align-items:flex-end">
        <label class="f" style="margin:0">La lista es de<select id="imp-uni"><option value="*" ${im.unidad === '*' ? 'selected' : ''}>Todas las unidades</option>${D.unidades.map(u => `<option ${im.unidad === u ? 'selected' : ''}>${esc(u)}</option>`).join('')}</select></label>
        ${maxCols > 1 ? `<label class="f" style="margin:0">Hay ${maxCols} precios por renglón; usar el<select id="imp-col">${Array.from({ length: maxCols }, (_, k) => `<option value="${k}" ${im.col === k ? 'selected' : ''}>${k + 1}° precio (ej.: ${money((im.reng.find(r => r.montos.length > k) || { montos: [0] }).montos[k])})</option>`).join('')}</select></label>` : ''}
        <span class="row" style="gap:8px"><span class="chip warn">${c.cambia} cambian de precio</span><span class="chip ok">${c.nueva} nuevas</span><span class="chip">${c.igual} sin cambios</span></span></div>
      <div class="tbl-wrap" style="max-height:52vh;overflow:auto"><table class="imp-tbl"><thead><tr><th>En la lista</th><th>Corresponde a</th><th class="r">Precio actual</th><th class="r">Precio nuevo</th><th>Cambio</th></tr></thead><tbody>
      ${im.filas.map((f, k) => {
        const p = f.dest !== 'nueva' && f.dest !== 'no' && prest(f.dest), dif = p && p.valor ? Math.round((f.precio - p.valor) / p.valor * 1000) / 10 : null;
        return `<tr class="${f.dest === 'nueva' ? 'nueva' : f.dest === 'no' ? 'nousar' : ''}"><td style="max-width:260px">${esc(f.nombre)}</td>
          <td><select data-i="${k}" data-f="dest" style="max-width:260px">${opc(f.dest)}</select>${f.dest === 'nueva' ? `<div class="row" style="gap:6px;margin-top:6px"><select data-i="${k}" data-f="unidad">${D.unidades.map(u => `<option ${f.unidad === u ? 'selected' : ''}>${esc(u)}</option>`).join('')}</select><select data-i="${k}" data-f="tipo">${Object.entries(D.tipos).map(([t, n]) => `<option value="${t}" ${f.tipo === t ? 'selected' : ''}>${esc(n)}</option>`).join('')}</select></div>` : ''}</td>
          <td class="r num">${p ? money(p.valor) : '—'}</td>
          <td class="r"><input class="monto" data-i="${k}" data-f="precio" inputmode="decimal" value="${fmtNum(f.precio)}" style="width:120px;text-align:right" ${f.dest === 'no' ? 'disabled' : ''}></td>
          <td class="nw">${f.dest === 'no' ? '<span class="muted">No se usa</span>' : f.dest === 'nueva' ? '<span class="chip ok">Nueva</span>' : !p.valor ? '<span class="chip warn">Antes sin precio</span>' : dif === 0 ? '<span class="muted">Igual</span>' : `<span class="chip ${dif > 0 ? 'warn' : 'ok'}">${dif > 0 ? 'Sube' : 'Baja'} ${Math.abs(dif).toLocaleString('es-AR')}%</span>`}</td></tr>`;
      }).join('')}</tbody></table></div>
      ${faltan.length ? `<div class="panel" style="padding:14px"><b>No figuran en la lista (${faltan.length})</b><p class="sub" style="margin:4px 0 8px">Quedan como están, salvo que marques quitarlas.</p><div style="display:flex;flex-direction:column;gap:6px">${faltan.map(p => `<label style="display:flex;gap:8px;align-items:center;cursor:pointer"><input type="checkbox" data-q="${p.cod}" ${im.quitar[p.cod] ? 'checked' : ''}>Quitar <b>${esc(p.nombre)}</b> <span class="muted">(${esc(p.unidad)} · ${p.valor ? money(p.valor) : 'sin valor'})</span></label>`).join('')}</div></div>` : ''}
      <div class="row" style="justify-content:flex-end"><button class="btn" type="button" data-act="modal-no">Cancelar <kbd>Esc</kbd></button><button class="btn primary" type="button" id="imp-aplicar">Aplicar cambios</button></div></div>`);
    const L = document.getElementById('imp-modal');
    L.querySelectorAll('[data-i]').forEach(i => i.addEventListener(i.tagName === 'INPUT' ? 'input' : 'change', () => { const f = im.filas[i.dataset.i]; f[i.dataset.f] = i.dataset.f === 'precio' ? parseMonto(i.value) : i.value; if (i.tagName === 'SELECT') pintar(); }));
    L.querySelectorAll('[data-q]').forEach(i => i.addEventListener('change', () => { im.quitar[i.dataset.q] = i.checked; }));
    document.getElementById('imp-uni').addEventListener('change', e => { im.unidad = e.target.value; relacionar(); });
    const col = document.getElementById('imp-col'); if (col) col.addEventListener('change', e => { im.col = +e.target.value; relacionar(); });
    document.getElementById('imp-aplicar').addEventListener('click', async () => {
      const j = await window.postJSON('/config/importar/previa', { json: JSON.stringify({ filas: im.filas, quitar: im.quitar, archivo: im.archivo }) });
      if (j.requiereAdmin) { const t = await window.pedirAdmin(j.requiereAdmin, j.texto); return; }
      if (!j.ok) { window.toast(j.error, 'bad'); return; }
      window.modal(j.modalHtml);
    });
  }
})();
