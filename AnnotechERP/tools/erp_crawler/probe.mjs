// Read-only Brickland probe: logs in, opens screens, clicks tabs/list items,
// screenshots and dumps controls. Every non-GET is blocked except while a page
// or a tab/list item loads (the page's own data loaders). Never presses Save.
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
const require = createRequire('C:/wamp64/www/machinery-maintenance/frontend/package.json');
const { chromium } = require('@playwright/test');

const [planFile, outdir] = process.argv.slice(2);
const plan = JSON.parse(fs.readFileSync(planFile, 'utf8'));
const env = Object.fromEntries(fs.readFileSync(process.env.ERP_AUDIT_ENV || 'C:/wamp64/www/machinery-maintenance/.annotech-erp-audit.env', 'utf8').split(/\r?\n/).map((l) => l.match(/^\s*([A-Z_]+)\s*=\s*(.*)$/)).filter(Boolean).map((m) => [m[1], m[2].replace(/^["']|["']$/g, '')]));
fs.mkdirSync(outdir, { recursive: true });

const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true });
const page = await context.newPage();
let open = true;
const blocked = [];
await context.route('**/*', (route) => {
  const m = route.request().method();
  // WRITE=1 lets a plan create/edit/remove (only on a demo instance with no live data).
  if (['GET', 'HEAD', 'OPTIONS'].includes(m) || open || process.env.WRITE === '1') return route.continue();
  blocked.push(`${m} ${route.request().url()}`);
  return route.abort();
});
await page.goto(env.ERP_URL, { waitUntil: 'networkidle', timeout: 60000 });
await page.locator('input[type="email"], input[type="text"]').first().fill(env.ERP_USER);
await page.locator('input[type="password"]').first().fill(env.ERP_PASS);
await page.locator('button[type="submit"]').first().click();
await page.waitForFunction(() => !document.querySelector('input[type="password"]'), null, { timeout: 30000 }).catch(() => {});
await page.waitForLoadState('networkidle').catch(() => {});
open = false;

const loading = async (fn) => { open = true; try { await fn(); await page.waitForLoadState('networkidle').catch(() => {}); await page.waitForTimeout(1500); } finally { open = false; } };
const dump = (rows) => page.evaluate((rowsMax) => {
  const main = document.querySelector('main') || document.body;
  const txt = (e) => (e.innerText || e.value || '').trim().replace(/\s+/g, ' ').slice(0, 160);
  return {
    headings: [...main.querySelectorAll('h1,h2,h3,h4')].map(txt),
    texts: [...main.querySelectorAll('p,small,span.text-muted,[class*="muted"]')].map(txt).filter((t) => t.length > 20).slice(0, 30),
    tables: [...main.querySelectorAll('table')].map((t) => ({ head: [...t.querySelectorAll('thead th')].map(txt), rows: [...t.querySelectorAll('tbody tr')].slice(0, rowsMax).map((r) => [...r.children].map((c) => { const ctl = c.querySelector('input,select,button'); const off = [...c.querySelectorAll('button')].filter((b) => b.disabled).map((b) => txt(b)); return ctl ? `[${ctl.tagName.toLowerCase()}${ctl.type ? ':' + ctl.type : ''}${ctl.value ? '=' + ctl.value : ''}${ctl.placeholder ? ' ph=' + ctl.placeholder : ''}] ${txt(c)}${off.length ? ' {disabled: ' + off.join(', ') + '}' : ''}` : txt(c); })), count: t.querySelectorAll('tbody tr').length })),
    selects: [...main.querySelectorAll('select')].slice(0, 12).map((s) => ({ name: s.name || s.id, options: [...s.options].map((o) => o.text).slice(0, 25) })),
    buttons: [...new Set([...main.querySelectorAll('button,a.btn')].map(txt).filter(Boolean))].slice(0, 60),
    inputs: [...main.querySelectorAll('input,textarea')].slice(0, 40).map((i) => `${i.type}:${i.name || i.id}:${i.placeholder || ''}`),
    labels: [...main.querySelectorAll('label')].map(txt).filter(Boolean).slice(0, 40),
    options: [...document.querySelectorAll('[role=option], [role=listbox] li')].map(txt).filter(Boolean).slice(0, 60),
    // An open dialog renders outside <main>; its title, hints, fields and buttons.
    dialogs: [...document.querySelectorAll('[role=dialog],[role=alertdialog]')].map((d) => ({
      headings: [...d.querySelectorAll('h1,h2,h3,h4')].map(txt),
      texts: [...d.querySelectorAll('p,small,span')].map(txt).filter((t) => t.length > 15).slice(0, 20),
      labels: [...d.querySelectorAll('label')].map(txt).filter(Boolean),
      inputs: [...d.querySelectorAll('input,textarea,select')].map((i) => `${i.type}:${i.name || i.id}:${i.placeholder || ''}${i.tagName === 'SELECT' ? ':' + [...i.options].map((o) => o.text).slice(0, 20).join('|') : ''}`),
      buttons: [...new Set([...d.querySelectorAll('button')].map(txt).filter(Boolean))],
    })),
  };
}, rows ?? 4);

const report = {};
for (const step of plan) {
  try {
    if (step.goto) await loading(() => page.goto(new URL(env.ERP_URL).origin + step.goto, { waitUntil: 'networkidle', timeout: 90000 }));
    if (step.click) await loading(() => page.getByText(step.click, { exact: step.exact ?? false }).first().click({ timeout: 15000 }));
    if (step.clickSel) await loading(() => (step.last ? page.locator(step.clickSel).last() : page.locator(step.clickSel).first()).click({ timeout: 15000 }));
    if (step.select) await loading(() => page.locator(step.select).last().selectOption(step.value, { timeout: 15000 }));
    if (step.pick) await page.locator(step.pick).first().selectOption({ label: step.label }, { timeout: 15000 });
    if (step.fill) await page.locator(step.fill).first().fill(String(step.value), { timeout: 15000 });
    if (step.press) await loading(() => page.locator(step.press).first().click({ timeout: 15000 }));
    if (step.wait) await page.waitForTimeout(step.wait);
    // a click that opens a dialog/editor only (no load): POSTs stay blocked
    if (step.peek) { await page.locator(step.peek).first().click({ timeout: 15000 }); await page.waitForTimeout(1200); }
    // scroll every scrolling container to its end (Brickland scrolls inside the shell, so fullPage alone clips)
    if (step.scroll) { await page.evaluate((f) => document.querySelectorAll('*').forEach((e) => { if (e.scrollHeight > e.clientHeight + 40 && /auto|scroll/.test(getComputedStyle(e).overflowY)) e.scrollTop = typeof f === 'number' ? e.scrollHeight * f : e.scrollHeight; }), step.scroll); await page.waitForTimeout(600); }
    if (step.shot) { await page.screenshot({ path: path.join(outdir, `${step.shot}.png`), fullPage: true }); report[step.shot] = await dump(step.rows); console.log('shot', step.shot); }
    if (step.escape) await page.keyboard.press('Escape');
  } catch (e) {
    console.log('FAILED', JSON.stringify(step), e.message.split('\n')[0]);
  }
}
fs.writeFileSync(path.join(outdir, 'report.json'), JSON.stringify(report, null, 1));
console.log('blocked:', blocked.length, blocked.slice(0, 10));
await browser.close();
