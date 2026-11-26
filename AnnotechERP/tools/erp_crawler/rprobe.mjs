// Brickland report-set reader: on a report screen (radios + Preview), picks each report in
// turn, sets the date window, presses Preview and records what it draws below the form —
// stat tiles, the grid's columns, a few rows — and which parameter boxes the report enables.
// Only the page load and the Preview click may send a POST (reads); everything else stays blocked.
// usage: node rprobe.mjs <screen path> <out.json> [from] [to] [only-label-substring]
import fs from 'node:fs';
import { createRequire } from 'node:module';
const require = createRequire('C:/wamp64/www/machinery-maintenance/frontend/package.json');
const { chromium } = require('@playwright/test');

const [screen, outfile, from = '2026-01-01', to = '2026-12-31', only = ''] = process.argv.slice(2);
const env = Object.fromEntries(fs.readFileSync(process.env.ERP_AUDIT_ENV || 'C:/wamp64/www/machinery-maintenance/.annotech-erp-audit.env', 'utf8').split(/\r?\n/).map((l) => l.match(/^\s*([A-Z_]+)\s*=\s*(.*)$/)).filter(Boolean).map((m) => [m[1], m[2].replace(/^["']|["']$/g, '')]));

const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 1600, height: 1000 }, ignoreHTTPSErrors: true });
const page = await context.newPage();
let open = true;
await context.route('**/*', (route) => {
  const m = route.request().method();
  if (['GET', 'HEAD', 'OPTIONS'].includes(m) || open) return route.continue();
  return route.abort();
});
await page.goto(env.ERP_URL, { waitUntil: 'networkidle', timeout: 60000 });
await page.locator('input[type="email"], input[type="text"]').first().fill(env.ERP_USER);
await page.locator('input[type="password"]').first().fill(env.ERP_PASS);
await page.locator('button[type="submit"]').first().click();
await page.waitForFunction(() => !document.querySelector('input[type="password"]'), null, { timeout: 30000 }).catch(() => {});
await page.waitForLoadState('networkidle').catch(() => {});
open = false;

// Loading a screen reads its pickers through server actions (POST), as panel.mjs allows.
const load = async () => {
  open = true;
  await page.goto(new URL(env.ERP_URL).origin + screen, { waitUntil: 'networkidle', timeout: 90000 }).catch((e) => console.log('goto', e.message.slice(0, 300)));
  await page.waitForTimeout(1500);
  open = false;
};
await load();
if (process.env.DEBUG) { console.log(page.url()); await page.screenshot({ path: outfile + ".png" }); }
const labels = await page.$$eval('main label:has(input[type=radio])', (ls) => ls.map((l) => l.innerText.trim()));
const out = { screen, labels, reports: [] };
for (const [i, label] of labels.entries()) {
  if (only && !label.includes(only)) continue;
  if (i) await load();
  const rec = { label };
  try {
    await page.locator('main label:has(input[type=radio])').nth(i).click({ timeout: 10000 });
    await page.waitForTimeout(700);
    rec.enabled = await page.$$eval('main select:not([disabled]), main input:not([type=radio]):not([type=checkbox]):not([disabled])', (es) => es.map((e) => e.name || e.getAttribute('aria-label') || e.type));
    const dates = page.locator('main input[type=date]');
    if ((await dates.count()) > 0) await dates.nth(0).fill(from);
    if ((await dates.count()) > 1) await dates.nth(1).fill(to);
    await page.waitForTimeout(300);
    open = true;
    await page.locator('main button:has-text("Preview"):not([role=tab])').last().click({ timeout: 10000 });
    await page.waitForLoadState('networkidle').catch(() => {});
    await page.waitForTimeout(3500);
    open = false;
    Object.assign(rec, await page.evaluate(() => {
      const main = document.querySelector('main');
      const text = main.innerText;
      const cut = text.lastIndexOf('\nReset\n');
      const tail = cut >= 0 ? text.slice(cut + 7) : '';
      return {
        tail: tail.slice(0, 1500),
        heads: [...main.querySelectorAll('table')].map((t) => [...t.querySelectorAll('thead th')].map((c) => c.innerText.trim().replace(/\s+/g, ' '))),
        rows: [...main.querySelectorAll('table')].map((t) => [...t.querySelectorAll('tbody tr')].slice(0, 3).map((r) => [...r.children].map((c) => c.innerText.trim().replace(/\s+/g, ' ')))),
        alerts: [...main.querySelectorAll('[role=alert], .text-danger, .text-red-500')].map((e) => e.innerText.trim()).filter(Boolean),
      };
    }));
  } catch (e) {
    open = false;
    rec.error = e.message.slice(0, 300);
  }
  out.reports.push(rec);
  fs.writeFileSync(outfile, JSON.stringify(out, null, 1));
  console.log(`${i + 1}/${labels.length} ${label} → ${rec.heads?.[0]?.length ?? 0} cols${rec.error ? ' ERR' : ''}`);
}
fs.writeFileSync(outfile, JSON.stringify(out, null, 1));
await browser.close();
