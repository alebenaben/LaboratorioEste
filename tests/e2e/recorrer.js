// Prueba de humo en el navegador: ingresa con cada rol, recorre sus pantallas,
// saca una captura de cada una y lista los errores de JavaScript.
//
//   BASE=http://127.0.0.1:8080 node tests/e2e/recorrer.js capturas/
//
// Requiere Playwright (npm i playwright && npx playwright install chromium) y
// una base con los lotes de prueba cargados (php bin/migrar.php --lotes), donde
// la clave de cada usuario es su nombre. Ver docs/PLAN_DE_PRUEBAS.md.
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE = process.env.BASE || 'http://127.0.0.1:8080';
const OUT = process.argv[2] || 'capturas';
fs.mkdirSync(OUT, { recursive: true });

const RECORRIDOS = [
  ['Santiago', ['/afiliados', '/altas', '/cotizador', '/autorizaciones', '/auditoria', '/prestaciones', '/beneficios',
    '/retencion?q=75', '/caja', '/egresos', '/informes', '/cuentas?g=6', '/contabilidad', '/estadisticas', '/promociones', '/config']],
  ['Belén', ['/inicio', '/altas', '/cotizador', '/autorizaciones', '/beneficios', '/retencion']],
  ['Biobárica', ['/verificar']],
  ['sanbenito', ['/verificar']],
];

async function ingresar(page, usuario, clave) {
  await page.goto(BASE + '/ingreso');
  await page.fill('#lg-u', usuario);
  await page.fill('#lg-p', clave);
  await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
  if (page.url().includes('/ingreso')) throw new Error(`No se pudo ingresar como ${usuario}`);
}

(async () => {
  const navegador = await chromium.launch();
  const errores = [];
  for (const [usuario, rutas] of RECORRIDOS) {
    const ctx = await navegador.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await ctx.newPage();
    page.on('pageerror', e => errores.push(`${usuario} ${page.url()}: ${e.message}`));
    page.on('console', m => { if (m.type() === 'error') errores.push(`${usuario} ${page.url()}: consola: ${m.text()}`); });
    await ingresar(page, usuario, process.env['CLAVE_' + usuario.toUpperCase()] || usuario);
    for (const ruta of rutas) {
      const r = await page.goto(BASE + ruta);
      if (!r || r.status() >= 400) errores.push(`${usuario} ${ruta}: HTTP ${r ? r.status() : '?'}`);
      await page.waitForTimeout(300);
      await page.screenshot({ path: path.join(OUT, `${usuario}-${ruta.replace(/[^a-z0-9]/gi, '_')}.png`) });
    }
    await ctx.close();
  }
  await navegador.close();
  if (errores.length) { console.error(errores.join('\n')); process.exit(1); }
  console.log('Sin errores de JavaScript ni respuestas con error.');
})();
