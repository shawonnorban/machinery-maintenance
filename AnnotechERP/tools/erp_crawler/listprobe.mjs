// Brickland report-list reader: on a report screen with a numbered list of reports ("1. Employee Master" …)
// and a View button, picks each report in turn, presses View and records what it draws — stat tiles, the
// grid's columns, a few rows, the filter labels — with a screenshot. Only the page load, picking a report
// and the View click may send a POST (reads); everything else stays blocked. Nothing is saved.
// usage: node listprobe.mjs <screen path> <outdir> [only-number,…]
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
const require = createRequire('C:/wamp64/www/machinery-maintenance/frontend/package.json');
const { chromium } = require('@playwright/test');

const [screen, outdir, only = ''] = process.argv.slice(2);
const want = only ? only.split(',').map(Number) : null;
fs.mkdirSync(outdir, { recursive: true });
const env = Object.fromEntries(fs.readFileSync(process.env.ERP_AUDIT_ENV || 'C:/wamp64/www/machinery-maintenance/.annotech-erp-audit.env', 'utf8').split(/\r?\n/).map((l) => l.match(/^\s*([A-Z_]+)\s*=\s*(.*)$/)).filter(Boolean).map((m) => [m[1], m[2].replace(/^["']|["']$/g, '')]));

const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 1600, height: 1100 }, ignoreHTTPSErrors: true });
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
await page.goto(new URL(env.ERP_URL).origin + screen, { waitUntil: 'domcontentloaded', timeout: 120000 }).catch((e) => console.log('goto', e.message.slice(0, 200)));
await page.locator('main button').filter({ hasText: /^\s*\d+\.\s*[A-Z]/ }).first().waitFor({ timeout: 150000 }).catch(() => {});
await page.waitForTimeout(3000);
await page.screenshot({ path: path.join(outdir, 'start.png') });
if (process.env.DEBUG) console.log(await page.evaluate(() => { const e = [...document.querySelectorAll('main *')].find((x) => x.children.length < 3 && /Employee List/.test(x.textContent || '') && x.textContent.length < 40); return e ? e.parentElement.outerHTML.slice(0, 1500) : 'none'; }));
open = false;

const items = page.locator('main button').filter({ hasText: /^\s*\d+\.\s*[A-Z]/ });
const names = (await items.allInnerTexts()).map((t) => t.trim().replace(/\s+/g, ' '));
const out = { screen, names, reports: [] };
for (const [i, name] of names.entries()) {
  const no = Number(name.split('.')[0]);
  if (want && !want.includes(no)) continue;
  const rec = { no, name };
  try {
    open = true;
    await items.nth(i).click({ timeout: 10000 });
    await page.waitForTimeout(1200);
    rec.filters = await page.$$eval('main label', (ls) => ls.map((l) => l.innerText.trim()).filter((t) => t && t.length < 40));
    // DATES=a,b fills the date boxes in order; MONTH fills a month box; EMP types into the Employee box.
    if (process.env.DATES) {
      const ds = process.env.DATES.split(',');
      const boxes = page.locator('main input[type=date]');
      for (let k = 0; k < Math.min(ds.length, await boxes.count()); k++) await boxes.nth(k).fill(ds[k]);
    }
    if (process.env.MONTH && (await page.locator('main input[type=month]').count())) await page.locator('main input[type=month]').first().fill(process.env.MONTH);
    if (process.env.EMP && (await page.locator('main input[placeholder^="Code, name"]').count())) await page.locator('main input[placeholder^="Code, name"]').first().fill(process.env.EMP);
    await page.locator('main button:has-text("View")').first().click({ timeout: 10000 });
    await page.waitForLoadState('networkidle').catch(() => {});
    await page.waitForTimeout(4000);
    open = false;
    Object.assign(rec, await page.evaluate(() => {
      const main = document.querySelector('main');
      const tables = [...main.querySelectorAll('table')];
      const text = main.innerText;
      const at = text.indexOf('\nExcel\n');
      return {
        tail: (at >= 0 ? text.slice(at + 7) : text).slice(0, 5000),
        heads: tables.map((t) => [...t.querySelectorAll('thead tr')].map((r) => [...r.children].map((c) => c.innerText.trim().replace(/\s+/g, ' ') + (c.colSpan > 1 ? `{${c.colSpan}}` : '')))),
        rows: tables.map((t) => [...t.querySelectorAll('tbody tr')].slice(0, 3).map((r) => [...r.children].map((c) => c.innerText.trim().replace(/\s+/g, ' ')))),
        foot: tables.map((t) => [...t.querySelectorAll('tfoot tr')].map((r) => [...r.children].map((c) => c.innerText.trim().replace(/\s+/g, ' ')))),
        count: tables.map((t) => t.querySelectorAll('tbody tr').length),
      };
    }));
    await page.screenshot({ path: path.join(outdir, `r${String(no).padStart(2, '0')}.png`), fullPage: true });
    // PRINT=1: what Print and PDF open — a popup (saved as PDF + PNG) or a download.
    if (process.env.PRINT) {
      for (const btn of ['Print', 'PDF']) {
        open = true;
        const pop = page.waitForEvent('popup', { timeout: 30000 }).then((p) => ['popup', p]).catch(() => null);
        const dl = page.waitForEvent('download', { timeout: 30000 }).then((d) => ['download', d]).catch(() => null);
        await page.locator(`main button:has-text("${btn}")`).first().click({ timeout: 10000 });
        const got = await Promise.race([pop, dl]);
        if (got?.[0] === 'popup') {
          const p = got[1];
          await p.waitForLoadState('load').catch(() => {});
          await p.waitForTimeout(4000);
          await p.pdf({ path: path.join(outdir, `r${no}-${btn}.pdf`), preferCSSPageSize: true, printBackground: true }).catch(() => {});
          await p.screenshot({ path: path.join(outdir, `r${no}-${btn}.png`), fullPage: true });
          rec[btn] = 'popup ' + p.url();
          await p.close();
        } else if (got?.[0] === 'download') {
          await got[1].saveAs(path.join(outdir, `r${no}-${btn}-${got[1].suggestedFilename()}`));
          rec[btn] = 'download ' + got[1].suggestedFilename();
        } else rec[btn] = 'nothing';
        open = false;
      }
    }
  } catch (e) {
    open = false;
    rec.error = e.message.slice(0, 300);
  }
  out.reports.push(rec);
  fs.writeFileSync(path.join(outdir, 'reports.json'), JSON.stringify(out, null, 1));
  console.log(`${name} → ${rec.heads?.map((h) => h.at(-1)?.length ?? 0).join('+') ?? 0} cols, ${rec.count ?? '-'} rows${rec.error ? ' ERR ' + rec.error.slice(0, 80) : ''}`);
}
fs.writeFileSync(path.join(outdir, 'reports.json'), JSON.stringify(out, null, 1));
await browser.close();
