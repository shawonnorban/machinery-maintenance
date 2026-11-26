// Parity crawler: walks the same screen in Brickland and in AnnotechERP and records each state —
// every tab, Edit on the first row, the row's other buttons (a delete is opened and cancelled,
// never confirmed), a left-list item and the New/Add button beside it — then writes what differs.
//
//   node parity.mjs <pairs.json> <out-dir> <annotech-session-token> [module-filter]
//
// pairs.json: [{ mod, name, bl: "/m/...", ours: "/route/screen" }]. Brickland credentials come
// from .annotech-erp-audit.env. Nothing is saved on either side: Save, Update and confirm buttons
// are never pressed.
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
const require = createRequire('C:/wamp64/www/machinery-maintenance/frontend/package.json');
const { chromium } = require('@playwright/test');

const [pairsFile, outdir, token, filter] = process.argv.slice(2);
const pairs = JSON.parse(fs.readFileSync(pairsFile, 'utf8')).filter((p) => typeof p.ours === 'string' && (!filter || p.mod.includes(filter) || p.ours.includes(filter)));
const env = Object.fromEntries(fs.readFileSync(process.env.ERP_AUDIT_ENV || 'C:/wamp64/www/machinery-maintenance/.annotech-erp-audit.env', 'utf8').split(/\r?\n/).map((l) => l.match(/^\s*([A-Z_]+)\s*=\s*(.*)$/)).filter(Boolean).map((m) => [m[1], m[2].replace(/^["']|["']$/g, '')]));
const OURS = process.env.ANNOTECH_URL || 'http://localhost:3010';
fs.mkdirSync(outdir, { recursive: true });

const browser = await chromium.launch();
const blCtx = await browser.newContext({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true });
const ourCtx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
await ourCtx.addCookies([{ name: 'annotech_session', value: token, domain: 'localhost', path: '/' }]);
const bl = await blCtx.newPage();
const ours = await ourCtx.newPage();
// A delete's confirmation is opened to read it, never accepted; the same for native confirms.
for (const p of [bl, ours]) p.on('dialog', (d) => d.dismiss().catch(() => {}));

await bl.goto(env.ERP_URL, { waitUntil: 'networkidle', timeout: 90000 });
await bl.locator('input[type="email"], input[type="text"]').first().fill(env.ERP_USER);
await bl.locator('input[type="password"]').first().fill(env.ERP_PASS);
await bl.locator('button[type="submit"]').first().click();
await bl.waitForFunction(() => !document.querySelector('input[type="password"]'), null, { timeout: 30000 }).catch(() => {});

/** Clicks a tab and waits until it is the selected one (our tabs are links that navigate). */
async function openTab(page, i) {
  const tab = page.locator('main [role=tab]').nth(i);
  const want = (await tab.innerText()).replace(/\s*\d+$/, '').trim();
  await tab.click({ timeout: 15000 }).catch(() => {});
  await page.waitForFunction((w) => {
    const sel = document.querySelector('main [role=tab][aria-selected=true]');
    return sel && sel.innerText.replace(/\s*\d+$/, '').trim() === w;
  }, want, { timeout: 60000 }).catch(() => {});
  await page.waitForTimeout(800);
}
const settle = async (page) => {
  await page.waitForLoadState('networkidle', { timeout: 60000 }).catch(() => {});
  // A panel still loading (or a dev server still compiling) is not a state to compare.
  await page.waitForFunction(() => !/Loading…|Rendering|Compiling/.test(document.body.innerText), null, { timeout: 90000 }).catch(() => {});
  await page.waitForTimeout(1200);
};
const snap = (page) => page.evaluate(() => {
  const main = document.querySelector('main') || document.body;
  const txt = (e) => (e.innerText || e.value || '').trim().replace(/\s+/g, ' ');
  const label = (b) => (txt(b) || b.getAttribute('aria-label') || b.getAttribute('title') || '').slice(0, 80);
  const visible = (e) => !!(e.offsetWidth || e.offsetHeight || e.getClientRects().length);
  const dlg = [...document.querySelectorAll('[role=dialog],[role=alertdialog]')].filter(visible);
  const inDialog = (e) => dlg.some((d) => d.contains(e));
  const firstRow = main.querySelector('table tbody tr');
  // Each field as "Label=control" (select, radio, checkbox, textarea, date, number, text …).
  const kind = (c) => !c ? '?' : c.getAttribute('role') === 'combobox' || c.tagName === 'SELECT' || c.getAttribute('aria-haspopup') === 'listbox' ? 'select' : c.getAttribute('role') === 'radiogroup' ? 'radio' : c.getAttribute('role') === 'checkbox' ? 'checkbox' : c.tagName === 'TEXTAREA' ? 'textarea' : c.tagName === 'INPUT' ? (c.type === 'search' ? 'text' : c.type) : c.tagName.toLowerCase();
  const fieldsOf = (root) => [...root.querySelectorAll('label')].filter(visible).map((l) => {
    const name = txt(l).replace(/\*/g, '').trim().slice(0, 60);
    let c = l.htmlFor ? document.getElementById(l.htmlFor) : null;
    c = c || l.querySelector('input,select,textarea,[role=combobox],[role=checkbox],[role=radiogroup]');
    if (!c) { let box = l.parentElement; for (let k = 0; k < 2 && box && !c; k++, box = box.parentElement) c = box.querySelector('input:not([type=hidden]),select,textarea,[role=combobox],[role=checkbox],[role=radiogroup]'); }
    if (c && c.type === 'radio') c = { getAttribute: (a) => (a === 'role' ? 'radiogroup' : null), tagName: 'DIV' };
    return name ? `${name}=${kind(c)}` : null;
  }).filter(Boolean);
  return {
    tabs: [...main.querySelectorAll('[role=tab]')].map((t) => txt(t).replace(/\s*\d+$/, '')),
    active: txt(main.querySelector('[role=tab][aria-selected=true]') || document.createElement('i')).replace(/\s*\d+$/, ''),
    headings: [...main.querySelectorAll('h1,h2,h3,h4')].filter((e) => !inDialog(e)).map(txt).filter(Boolean),
    heads: [...main.querySelectorAll('table')].map((t) => [...t.querySelectorAll('thead th')].map(txt)),
    rowButtons: firstRow ? [...firstRow.querySelectorAll('button,a')].map(label) : [],
    rowCount: main.querySelectorAll('table tbody tr').length,
    buttons: [...new Set([...main.querySelectorAll('button,a[role=button]')].filter((b) => !inDialog(b) && !b.closest('table') && b.getAttribute('role') !== 'tab').map(label).filter(Boolean))],
    labels: [...main.querySelectorAll('label')].filter((e) => !inDialog(e)).map(txt).filter(Boolean),
    fields: fieldsOf(main).filter(Boolean),
    texts: [...main.querySelectorAll('p,small')].filter((e) => !inDialog(e)).map(txt).filter((t) => t.length > 25).slice(0, 25),
    dialogs: dlg.map((d) => ({
      headings: [...d.querySelectorAll('h1,h2,h3,h4')].map(txt),
      texts: [...d.querySelectorAll('p,small')].map(txt).filter((t) => t.length > 15).slice(0, 12),
      labels: [...d.querySelectorAll('label')].map(txt).filter(Boolean),
      fields: fieldsOf(d),
      buttons: [...new Set([...d.querySelectorAll('button')].map(label).filter(Boolean))],
    })),
  };
});

/** Every state of one screen, on one side. */
async function explore(page, url, tag) {
  const states = {};
  const shot = async (name) => page.screenshot({ path: path.join(outdir, `${tag}__${name.replace(/[<>:"/\|?*\s]+/g, '_')}.png`), fullPage: true }).catch(() => {});
  const record = async (name) => { states[name] = await snap(page); await shot(name); };
  const close = async () => { await page.keyboard.press('Escape').catch(() => {}); await page.waitForTimeout(400); };
  try {
    await page.goto(url, { waitUntil: 'networkidle', timeout: 180000 });
  } catch (e) {
    return { error: e.message.split('\n')[0] };
  }
  await settle(page);
  await record('default');
  const tabCount = await page.locator('main [role=tab]').count();
  for (let i = 0; i < Math.max(tabCount, 1); i++) {
    let name = 'tab0';
    if (tabCount) {
      const tab = page.locator('main [role=tab]').nth(i);
      name = `tab:${(await tab.innerText()).replace(/\s*\d+$/, '').trim()}`;
      if (/^(Edit)/.test(name.slice(4))) continue;
      await openTab(page, i);
      await settle(page);
    }
    await record(name);
    const row = page.locator('main table tbody tr').first();
    const rowButtons = (await row.count()) ? await row.locator('button,a').count() : 0;
    // Edit on the first row: a tab, a dialog or a page.
    if (rowButtons) {
      const edit = row.locator('button,a').filter({ hasText: /^\s*Edit\s*$/ }).first();
      if (await edit.count()) {
        await edit.click({ timeout: 15000 }).catch(() => {});
        await settle(page);
        await record(`${name}>edit`);
        await close();
        await page.goto(url, { waitUntil: 'networkidle', timeout: 180000 }).catch(() => {});
        await settle(page);
        if (tabCount) { await openTab(page, i); await settle(page); }
      }
      // The row's icon buttons (remove, deactivate, put on calendar …): open, read, cancel.
      const icons = page.locator('main table tbody tr').first().locator('button').filter({ hasNotText: /\S/ });
      const n = Math.min(await icons.count(), 4);
      for (let j = 0; j < n; j++) {
        const b = page.locator('main table tbody tr').first().locator('button').filter({ hasNotText: /\S/ }).nth(j);
        const aria = (await b.getAttribute('aria-label')) || (await b.getAttribute('title')) || `icon${j}`;
        if (!/remove|delete|trash/i.test(aria)) continue; // a toggle or a filing button acts at once; reading its label is enough
        await b.click({ timeout: 10000 }).catch(() => {});
        await page.waitForTimeout(900);
        await record(`${name}>row:delete`);
        await close();
      }
    }
    // A left list (tree, voucher types, banks, registers …): pick its first item, then its New/Add.
    const item = page.locator('main ul li button, main [role=listbox] [role=option]').first();
    if (await item.count()) {
      await item.click({ timeout: 10000 }).catch(() => {});
      await settle(page);
      await record(`${name}>pick`);
      const add = page.locator('main button').filter({ hasText: /^\s*(\+\s*)?(New|Add|Add .{1,30})\s*$/ }).first();
      if (await add.count()) {
        await add.click({ timeout: 10000 }).catch(() => {});
        await page.waitForTimeout(1000);
        await record(`${name}>pick>new`);
        await close();
      }
    }
  }
  return states;
}

const norm = (s) => s.replace(/[*:]/g, '').replace(/\s+/g, ' ').trim().toLowerCase();
const diffList = (a = [], b = []) => {
  const A = new Set(a.map(norm)), B = new Set(b.map(norm));
  return { onlyBrickland: [...A].filter((x) => x && !B.has(x)), onlyOurs: [...B].filter((x) => x && !A.has(x)) };
};
const editMode = (s) => (!s ? 'none' : s.dialogs?.length ? 'dialog' : /^edit/i.test(s.active) ? 'tab' : 'page/other');

const report = [];
for (const p of pairs) {
  const tag = p.ours.replace(/^\//, '').replace(/\//g, '_');
  console.log('…', p.name);
  const B = await explore(bl, new URL(env.ERP_URL).origin + p.bl, `${tag}__bl`);
  const O = await explore(ours, OURS + p.ours, `${tag}__ours`);
  fs.writeFileSync(path.join(outdir, `${tag}.json`), JSON.stringify({ pair: p, bl: B, ours: O }, null, 1));
  const lines = [`## ${p.name}  (${p.bl} ↔ ${p.ours})`];
  if (B.error || O.error) lines.push(`- load error: bl=${B.error ?? 'ok'} ours=${O.error ?? 'ok'}`);
  else {
    const t = diffList(B.default?.tabs, O.default?.tabs);
    if (t.onlyBrickland.length || t.onlyOurs.length) lines.push(`- tabs: Brickland only ${JSON.stringify(t.onlyBrickland)} · ours only ${JSON.stringify(t.onlyOurs)}`);
    if (norm(B.default?.active ?? '') !== norm(O.default?.active ?? '')) lines.push(`- default tab: Brickland "${B.default?.active}" · ours "${O.default?.active}"`);
    for (const name of new Set([...Object.keys(B), ...Object.keys(O)])) {
      if (name === 'default') continue;
      const b = B[name], o = O[name];
      if (!b || !o) { lines.push(`- state "${name}": ${b ? 'missing in ours' : 'only in ours'}`); continue; }
      const parts = [];
      for (const k of ['fields', 'buttons', 'rowButtons']) {
        const d = diffList(b[k], o[k]);
        if (d.onlyBrickland.length || d.onlyOurs.length) parts.push(`${k}: BL ${JSON.stringify(d.onlyBrickland)} | ours ${JSON.stringify(d.onlyOurs)}`);
      }
      const h = diffList(b.heads.flat(), o.heads.flat());
      if (h.onlyBrickland.length || h.onlyOurs.length) parts.push(`grid: BL ${JSON.stringify(h.onlyBrickland)} | ours ${JSON.stringify(h.onlyOurs)}`);
      const dl = diffList(b.dialogs.flatMap((x) => [...x.fields, ...x.buttons, ...x.headings]), o.dialogs.flatMap((x) => [...x.fields, ...x.buttons, ...x.headings]));
      if (dl.onlyBrickland.length || dl.onlyOurs.length) parts.push(`dialog: BL ${JSON.stringify(dl.onlyBrickland)} | ours ${JSON.stringify(dl.onlyOurs)}`);
      if (name.endsWith('>edit') && editMode(b) !== editMode(o)) parts.push(`edit opens as: BL ${editMode(b)} | ours ${editMode(o)}`);
      if (parts.length) lines.push(`- **${name}**\n  - ${parts.join('\n  - ')}`);
    }
  }
  report.push(lines.join('\n'));
  fs.writeFileSync(path.join(outdir, 'REPORT.md'), report.join('\n\n') + '\n');
}
console.log('done', report.length);
await browser.close();
