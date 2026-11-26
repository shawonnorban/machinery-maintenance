// Reads Brickland's sidebar links under one group (read-only).
//   node AnnotechERP/tools/erp_crawler/sidelinks.mjs "Production Tracking" [--open /m/<module>]
// Logs in like crawl.mjs, expands the sidebar group named by the text, prints
// every link (path, text) found under it, and — with --open — also opens the
// module page and lists its own menu links. No POST is sent after login.

import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(HERE, '../../..');
const require = createRequire(path.join(ROOT, 'frontend', 'package.json'));
const { chromium } = require('@playwright/test');

const [text, ...rest] = process.argv.slice(2);
const open = rest[0] === '--open' ? rest[1] : null;

function env() {
  const file = process.env.ERP_AUDIT_ENV || path.join(os.homedir(), '.annotech-erp-audit.env');
  const out = {};
  for (const line of fs.readFileSync(file, 'utf8').split(/\r?\n/)) {
    const m = line.match(/^\s*([A-Z_]+)\s*=\s*(.*)\s*$/);
    if (m) out[m[1]] = m[2].replace(/^["']|["']$/g, '');
  }
  return out;
}

const e = env();
const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1600, height: 1000 } });
let loggedIn = false;
await page.route('**/*', (route) => (loggedIn && route.request().method() !== 'GET' ? route.abort() : route.continue()));
await page.goto(e.ERP_URL, { waitUntil: 'networkidle', timeout: 60000 });
await page.locator('input[type="email"], input[name*="user" i], input[id*="user" i], input[name*="login" i], input[type="text"]').first().fill(e.ERP_USER);
await page.locator('input[type="password"]').first().fill(e.ERP_PASS);
await Promise.all([
  page.waitForLoadState('networkidle').catch(() => {}),
  page.locator('button[type="submit"], input[type="submit"]').first().click(),
]);
await page.waitForTimeout(5000);
loggedIn = true;

const links = async () => page.$$eval('a[href]', (as) => as.map((a) => ({ text: (a.innerText || '').trim().replace(/\s+/g, ' '), href: a.getAttribute('href') })));

// Expand the group and read what appears.
const before = new Set((await links()).map((l) => l.href));
const group = page.locator(`nav :text-is("${text}"), aside :text-is("${text}")`).first();
if (await group.count()) {
  await group.click().catch(() => {});
  await page.waitForTimeout(3000);
}
const after = await links();
const added = after.filter((l) => !before.has(l.href));
const matching = after.filter((l) => l.text.toLowerCase().includes(text.toLowerCase()));
console.log(JSON.stringify({ group: text, matching, added }, null, 1));

if (open) {
  await page.goto(new URL(open, e.ERP_URL).href, { waitUntil: 'networkidle', timeout: 60000 });
  await page.waitForTimeout(4000);
  // Expand only the collapsed folders inside the named sidebar item (between it and the next section heading).
  for (let i = 0; i < 30; i++) {
    const clicked = await page.evaluate((name) => {
      const nav = document.querySelector('aside') || document.querySelector('nav');
      if (!nav) return false;
      const all = [...nav.querySelectorAll('*')];
      const start = all.find((el) => el.children.length === 0 && el.textContent.trim() === name);
      if (!start) return false;
      // The next section heading: upper-case text that is not a folder toggle.
      const end = all.find((el) => el.children.length === 0 && /^[A-Z &]{6,}$/.test(el.textContent.trim()) && !el.closest('[aria-expanded]') && (start.compareDocumentPosition(el) & Node.DOCUMENT_POSITION_FOLLOWING));
      const inside = (el) => (start.compareDocumentPosition(el) & Node.DOCUMENT_POSITION_FOLLOWING) && (!end || (el.compareDocumentPosition(end) & Node.DOCUMENT_POSITION_FOLLOWING));
      const owner = start.closest('a,button');
      if (owner && owner.getAttribute('aria-expanded') === 'false') { owner.click(); return true; }
      const toggle = [...nav.querySelectorAll('[aria-expanded="false"]')].find(inside);
      if (!toggle) return false;
      toggle.click();
      return true;
    }, text);
    if (!clicked) break;
    await page.waitForTimeout(600);
  }
  const prefix = open.split('/').slice(0, 3).join('/');
  // Folders may close each other (accordion): open each by its name and gather what shows.
  const gathered = new Map();
  const gather = async (folder) => {
    for (const l of await links()) if (l.href && l.href.startsWith(prefix)) gathered.set(l.href, { ...l, folder });
  };
  await gather('');
  for (const folder of (process.env.FOLDERS || '').split(',').filter(Boolean)) {
    const head = page.locator(`aside :text-is("${folder}"), nav :text-is("${folder}")`).last();
    if (await head.count()) {
      await head.scrollIntoViewIfNeeded().catch(() => {});
      await head.click().catch(() => {});
      await page.waitForTimeout(1200);
      await gather(folder);
    }
  }
  const all = [...gathered.values()];
  console.log(JSON.stringify({ module: open, links: all }, null, 1));
  // The whole menu tree also rides in the page's RSC payload: every path of the module, with the label next to it.
  const html = await page.content();
  const found = {};
  const re = new RegExp(`${prefix.replace(/[/-]/g, (c) => `\\${c}`)}/[a-z0-9-]+`, 'g');
  for (const m of html.matchAll(re)) {
    const around = html.slice(Math.max(0, m.index - 260), m.index + 200).replace(/\\"/g, '"');
    const label = (around.match(/"(?:label|title|name)":"([^"]{2,80})"/g) || []).map((x) => x.split('":"')[1].slice(0, -1));
    found[m[0]] ??= label.slice(0, 3);
  }
  console.log(JSON.stringify({ payload: found }, null, 1));
  // The sidebar text of that item, folders and counts included.
  const sideText = await page.evaluate(() => (document.querySelector('aside') || document.querySelector('nav'))?.innerText ?? '');
  const lines = sideText.split('\n').map((l) => l.trim()).filter(Boolean);
  const at = lines.indexOf(text);
  console.log(JSON.stringify({ sidebar: at >= 0 ? lines.slice(at, at + 60) : lines.slice(0, 40) }, null, 1));
  await page.screenshot({ path: path.join(HERE, 'out', 'sidelinks.png'), fullPage: true });
}
await browser.close();
