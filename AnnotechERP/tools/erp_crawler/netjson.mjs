// Read-only: logs into Brickland, opens each route (GET only, non-GET aborted), saves every JSON/RSC response body.
// usage: node netjson.mjs <outdir> <route> [route...]
import fs from 'node:fs';
import { createRequire } from 'node:module';
const require = createRequire('C:/wamp64/www/machinery-maintenance/frontend/package.json');
const { chromium } = require('@playwright/test');
const env = Object.fromEntries(fs.readFileSync('C:/wamp64/www/machinery-maintenance/.annotech-erp-audit.env', 'utf8').split(/\r?\n/).map((l) => l.match(/^\s*([A-Z_]+)\s*=\s*(.*)$/)).filter(Boolean).map((m) => [m[1], m[2].replace(/^["']|["']$/g, '')]));
const [outdir, ...routes] = process.argv.slice(2);
fs.mkdirSync(outdir, { recursive: true });
const browser = await chromium.launch();
const context = await browser.newContext({ ignoreHTTPSErrors: true });
const page = await context.newPage();
await page.goto(env.ERP_URL, { waitUntil: 'networkidle', timeout: 60000 });
await page.locator('input[type="email"], input[type="text"]').first().fill(env.ERP_USER);
await page.locator('input[type="password"]').first().fill(env.ERP_PASS);
await page.locator('button[type="submit"]').first().click();
await page.waitForFunction(() => !document.querySelector('input[type="password"]'), null, { timeout: 30000 }).catch(() => {});
let n = 0;
const log = [];
page.on('response', async (r) => {
  const u = r.url();
  if (/\.(js|css|woff2?|png|svg|ico|jpg)(\?|$)/.test(u)) return;
  const ct = r.headers()['content-type'] || '';
  try {
    const body = await r.text();
    const name = `${String(++n).padStart(3, '0')}.txt`;
    fs.writeFileSync(`${outdir}/${name}`, body);
    log.push([name, r.request().method(), r.status(), ct, u, body.length]);
  } catch {}
});
await context.route('**/*', (r) => (['GET', 'HEAD', 'OPTIONS'].includes(r.request().method()) ? r.continue() : (log.push(['blocked', r.request().method(), r.request().url()]), r.abort())));
for (const route of routes) {
  log.push(['ROUTE', route]);
  await page.goto(new URL(env.ERP_URL).origin + route, { waitUntil: 'networkidle', timeout: 90000 });
  await page.waitForTimeout(2000);
}
fs.writeFileSync(`${outdir}/log.json`, JSON.stringify(log, null, 1));
await browser.close();
