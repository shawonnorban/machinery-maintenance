// Read-only Brickland JS-chunk grep: logs in, opens a route (GET only), and for every script chunk that
// matches MATCH (a regex, optional) prints matches around each search word; SAVE=<dir> also saves the chunks.
// Use when a demo screen has no rows, to read its hidden columns, buttons and messages from the bundle.
// usage: MSYS_NO_PATHCONV=1 SAVE=<dir> node jsgrep.mjs /m/garments/<slug> <word> [word...]
import fs from 'node:fs';
import { createRequire } from 'node:module';
const require = createRequire('C:/wamp64/www/machinery-maintenance/frontend/package.json');
const { chromium } = require('@playwright/test');
const env = Object.fromEntries(fs.readFileSync('C:/wamp64/www/machinery-maintenance/.annotech-erp-audit.env', 'utf8').split(/\r?\n/).map((l) => l.match(/^\s*([A-Z_]+)\s*=\s*(.*)$/)).filter(Boolean).map((m) => [m[1], m[2].replace(/^["']|["']$/g, '')]));
const [route, ...words] = process.argv.slice(2);
const browser = await chromium.launch();
const context = await browser.newContext({ ignoreHTTPSErrors: true });
const page = await context.newPage();
const scripts = new Set();
page.on('response', (r) => { if (r.url().endsWith('.js') || r.request().resourceType() === 'script') scripts.add(r.url()); });
await page.goto(env.ERP_URL, { waitUntil: 'networkidle', timeout: 60000 });
await page.locator('input[type="email"], input[type="text"]').first().fill(env.ERP_USER);
await page.locator('input[type="password"]').first().fill(env.ERP_PASS);
await page.locator('button[type="submit"]').first().click();
await page.waitForFunction(() => !document.querySelector('input[type="password"]'), null, { timeout: 30000 }).catch(() => {});
await context.route('**/*', (r) => (['GET', 'HEAD', 'OPTIONS'].includes(r.request().method()) ? r.continue() : r.abort()));
scripts.clear();
await page.goto(new URL(env.ERP_URL).origin + route, { waitUntil: 'networkidle', timeout: 90000 });
for (const u of scripts) {
  const body = await (await page.request.get(u)).text();
  if (process.env.MATCH && !new RegExp(process.env.MATCH, "i").test(body)) continue; if (process.env.SAVE) fs.writeFileSync(process.env.SAVE + "/" + u.split("/").pop(), body);
  for (const w of words) {
    const re = new RegExp(`.{0,160}${w}.{0,160}`, 'gi');
    const hits = [...body.matchAll(re)].map((m) => m[0]).slice(0, 8);
    if (hits.length) console.log('##', u.split('/').pop(), w, '\n', hits.join('\n---\n'));
  }
}
await browser.close();
