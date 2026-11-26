// Reads a Brickland form as it is today: every field's label, control, hint and — for a select —
// its options (each combobox is opened, read and closed). Nothing is saved.
//
//   node fields.mjs <out.json> <path>[#Tab label] [<path>[#Tab label] …]
import fs from 'node:fs';
import { createRequire } from 'node:module';
const require = createRequire('C:/wamp64/www/machinery-maintenance/frontend/package.json');
const { chromium } = require('@playwright/test');

const [outFile, ...targets] = process.argv.slice(2);
const env = Object.fromEntries(fs.readFileSync(process.env.ERP_AUDIT_ENV || 'C:/wamp64/www/machinery-maintenance/.annotech-erp-audit.env', 'utf8').split(/\r?\n/).map((l) => l.match(/^\s*([A-Z_]+)\s*=\s*(.*)$/)).filter(Boolean).map((m) => [m[1], m[2].replace(/^["']|["']$/g, '')]));
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
page.on('dialog', (d) => d.dismiss().catch(() => {}));
await page.goto(env.ERP_URL, { waitUntil: 'networkidle' });
await page.locator('input[type="email"], input[type="text"]').first().fill(env.ERP_USER);
await page.locator('input[type="password"]').first().fill(env.ERP_PASS);
await page.locator('button[type="submit"]').first().click();
await page.waitForFunction(() => !document.querySelector('input[type="password"]'), null, { timeout: 30000 }).catch(() => {});

const out = {};
for (const target of targets) {
  const [p, tab, action] = target.split('#');
  await page.goto(new URL(env.ERP_URL).origin + p, { waitUntil: 'networkidle', timeout: 120000 });
  if (tab) { await page.locator('main [role=tab]').filter({ hasText: tab }).first().click().catch(() => {}); await page.waitForLoadState('networkidle').catch(() => {}); await page.waitForTimeout(1200); }
  if (action) { await page.getByRole('button', { name: action }).first().click().catch(() => {}); await page.waitForTimeout(1500); }
  const root = (await page.locator('[role=dialog]').count()) ? page.locator('[role=dialog]').last() : page.locator('main');
  const fields = await root.evaluate((r) => [...r.querySelectorAll('label')].map((l) => {
    const txt = (e) => (e?.innerText || '').trim().replace(/\s+/g, ' ');
    let c = l.htmlFor ? document.getElementById(l.htmlFor) : null;
    c = c || l.querySelector('input,select,textarea,button[role=combobox]');
    if (!c) { let box = l.parentElement; for (let k = 0; k < 2 && box && !c; k++, box = box.parentElement) c = box.querySelector('input:not([type=hidden]),select,textarea,button[role=combobox],[role=combobox]'); }
    const box = l.closest('div');
    const hint = box ? [...box.querySelectorAll('p,small')].map(txt).filter((t) => t && t !== txt(l)).join(' | ') : '';
    return { label: txt(l), type: c ? (c.getAttribute('role') === 'combobox' ? 'combobox' : c.tagName === 'SELECT' ? 'select' : c.type || c.tagName.toLowerCase()) : '?', id: c?.id || null, placeholder: c?.placeholder || txt(c) || null, hint, options: c?.tagName === 'SELECT' ? [...c.options].map((o) => o.text) : null };
  }));
  // Open each popup select (a button beside its label) and read its options.
  const handles = await root.locator('label').elementHandles();
  for (let i = 0; i < handles.length; i++) {
    const f = fields[i];
    if (!f || !['button', 'combobox'].includes(f.type)) continue;
    const ctl = await handles[i].evaluateHandle((l) => {
      let c = l.htmlFor ? document.getElementById(l.htmlFor) : null;
      c = c || l.querySelector('button');
      let box = l.parentElement;
      for (let k = 0; k < 2 && box && !c; k++, box = box.parentElement) c = box.querySelector('button[aria-haspopup], button[role=combobox], [role=combobox], button');
      return c;
    });
    const el = ctl.asElement();
    if (!el) continue;
    await el.click({ timeout: 5000 }).catch(() => {});
    await page.waitForTimeout(700);
    f.options = (await page.locator('[role=option], [role=listbox] li, [cmdk-item]').allInnerTexts().catch(() => [])).map((o) => o.trim()).slice(0, 60);
    await page.keyboard.press('Escape');
    await page.waitForTimeout(300);
  }
  const extra = await root.evaluate((r) => ({ headings: [...r.querySelectorAll('h1,h2,h3')].map((e) => e.innerText.trim()), texts: [...r.querySelectorAll('p')].map((e) => e.innerText.trim()).filter((t) => t.length > 20).slice(0, 15), buttons: [...new Set([...r.querySelectorAll('button')].map((b) => (b.innerText.trim() || b.getAttribute('aria-label') || '').slice(0, 60)).filter(Boolean))].slice(0, 40) }));
  out[target] = { fields, ...extra };
  console.log('read', target, fields.length);
}
fs.writeFileSync(outFile, JSON.stringify(out, null, 1));
await browser.close();
