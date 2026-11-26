// Read-only crawler for the live ERP. See README.md in this folder.
//
//   node AnnotechERP/tools/erp_crawler/crawl.mjs --menu-only [--discover]
//   node AnnotechERP/tools/erp_crawler/crawl.mjs --module "MnM" [--limit 20] [--delay 1500] [--headed] [--click-menu]
//   node AnnotechERP/tools/erp_crawler/crawl.mjs --screens [--only knitting,dyeing] [--allow-load-posts] [--refresh]
//   node AnnotechERP/tools/erp_crawler/crawl.mjs --dialogs --allow-writes [--only …]   (demo instance only)
//
// Safety, enforced in code:
//   - every non-GET request is aborted and logged to out/blocked.log, except the
//     login form submit and URLs matching ALLOW_POST (empty by default);
//   - no button is clicked except the login submit and, with --discover, sidebar
//     expand toggles (aria-expanded="false"); screens are reached by GET navigation
//     to menu links (or, with --click-menu, by clicking menu anchors);
//   - with --allow-load-posts (owner-approved), POSTs the page fires by itself while
//     loading or switching a tab are allowed and logged to out/allowed.log; no
//     save/submit/approve/delete control is ever clicked;
//   - a delay between pages keeps load on the server low.

import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(HERE, '../../..');
const OUT = path.join(HERE, 'out');
const require = createRequire(path.join(ROOT, 'frontend', 'package.json'));
const { chromium } = require('@playwright/test');

// Adjust after the first --menu-only run, from out/login.png and out/menu.json.
const SELECTORS = {
  user: 'input[type="email"], input[name*="user" i], input[id*="user" i], input[name*="login" i], input[type="text"]',
  pass: 'input[type="password"]',
  submit: 'button[type="submit"], input[type="submit"], button:has-text("Login"), button:has-text("Sign in")',
  menu: 'nav, #menu, .menu, .sidebar, #sidebar, .navbar, ul.nav',
  // sidebar expand buttons only (never header/toolbar buttons)
  navToggle: 'nav button, aside button, .sidebar button',
};
// POST endpoints that only READ (e.g. a menu or grid loader). Empty = block every POST.
const ALLOW_POST = [];

const args = process.argv.slice(2);
const flag = (n) => args.includes(n);
const opt = (n, d) => {
  const i = args.indexOf(n);
  return i >= 0 && args[i + 1] ? args[i + 1] : d;
};
const MENU_ONLY = flag('--menu-only');
const DISCOVER = flag('--discover');
const ONLY = opt('--only', '').split(',').map((x) => x.trim()).filter(Boolean);
const MAX_TOGGLES = Number(opt('--max-toggles', '80'));
const ALLOW_LOAD_POSTS = flag('--allow-load-posts');
const SCREENS = flag('--screens');
// demo instance, owner-approved: open "New/Add" dialogs and press Save on the
// empty form to record required fields and validation messages
const DIALOGS = flag('--dialogs');
const ALLOW_WRITES = flag('--allow-writes');
const MODULE = opt('--module', null);
const LIMIT = Number(opt('--limit', '0'));
const DELAY = Number(opt('--delay', '1500'));

function loadEnv() {
  const file = process.env.ERP_AUDIT_ENV || path.join(os.homedir(), '.annotech-erp-audit.env');
  if (!fs.existsSync(file)) {
    console.error(`Credential file not found: ${file}\nCreate it with ERP_URL=, ERP_USER=, ERP_PASS= (see README.md).`);
    process.exit(1);
  }
  const env = {};
  for (const line of fs.readFileSync(file, 'utf8').split(/\r?\n/)) {
    const m = line.match(/^\s*([A-Z_]+)\s*=\s*(.*)\s*$/);
    if (m) env[m[1]] = m[2].replace(/^["']|["']$/g, '');
  }
  for (const k of ['ERP_URL', 'ERP_USER', 'ERP_PASS']) {
    if (!env[k]) {
      console.error(`${k} missing in ${file}`);
      process.exit(1);
    }
  }
  return env;
}

const MENU_SCRIPT = (menuSel) => {
  const clean = (t) => (t || '').replace(/\s+/g, ' ').trim();
  const short = (t) => (t.length <= 40 ? t.replace(/\s+\d+$/, '') : '');
  const roots = [...document.querySelectorAll(menuSel)];
  const scope = roots.length ? roots : [document.body];
  const seen = new Set();
  const items = [];
  for (const root of scope) {
    for (const a of root.querySelectorAll('a')) {
      const text = clean(a.innerText || a.textContent);
      if (!text) continue;
      const trail = [];
      let el = a.parentElement;
      while (el && el !== root.parentElement) {
        let t = '';
        if (el.tagName === 'LI') {
          const head = el.querySelector(':scope > a, :scope > span, :scope > div, :scope > button');
          t = head ? short(clean(head.innerText)) : '';
        }
        // group heading rendered as a sibling just before the list (div/button/h*/p)
        const prev = el.previousElementSibling;
        if (!t && prev && !prev.querySelector('a')) t = short(clean(prev.innerText));
        if (!t && el.getAttribute && el.getAttribute('aria-label')) t = short(clean(el.getAttribute('aria-label')));
        if (t && t !== text && !trail.includes(t)) trail.unshift(t);
        el = el.parentElement;
      }
      const href = a.getAttribute('href') || '';
      const key = `${trail.join(' / ')}|${text}|${href}`;
      if (seen.has(key)) continue;
      seen.add(key);
      items.push({ path: trail, name: text.replace(/\s+\d+$/, ''), href: href ? new URL(href, location.href).href : '', onclick: a.getAttribute('onclick') || '' });
    }
  }
  return items;
};

const slug = (s) => s.replace(/[^\p{L}\p{N}]+/gu, '_').replace(/^_|_$/g, '').slice(0, 80) || 'screen';
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const writeJson = (file, data) => {
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, JSON.stringify(data, null, 2));
};

async function main() {
  const env = loadEnv();
  fs.mkdirSync(OUT, { recursive: true });
  const blockedLog = fs.createWriteStream(path.join(OUT, 'blocked.log'), { flags: 'w' });

  const browser = await chromium.launch({ headless: !flag('--headed') });
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true });
  const page = await context.newPage();

  let loginPhase = true;
  // true only while a page loads or a tab switches (never around a click that could
  // save); with --allow-load-posts, POSTs the page fires by itself in that window
  // (data loaders / server actions) are let through and logged to out/allowed.log
  let loadWindow = false;
  const allowedLog = fs.createWriteStream(path.join(OUT, 'allowed.log'), { flags: 'a' });
  await context.route('**/*', (route) => {
    const req = route.request();
    const method = req.method();
    if (['GET', 'HEAD', 'OPTIONS'].includes(method)) return route.continue();
    if (loginPhase || ALLOW_WRITES || ALLOW_POST.some((rx) => rx.test(req.url()))) return route.continue();
    if (ALLOW_LOAD_POSTS && loadWindow) {
      allowedLog.write(`${new Date().toISOString()} ALLOWED-LOAD ${method} ${req.url()}\n`);
      return route.continue();
    }
    blockedLog.write(`${new Date().toISOString()} BLOCKED ${method} ${req.url()}\n`);
    return route.abort();
  });

  // ---- login ----
  await page.goto(env.ERP_URL, { waitUntil: 'networkidle', timeout: 60000 });
  await page.screenshot({ path: path.join(OUT, 'login.png'), fullPage: true });
  await page.locator(SELECTORS.user).first().fill(env.ERP_USER);
  await page.locator(SELECTORS.pass).first().fill(env.ERP_PASS);
  const loginUrl = page.url();
  await page.locator(SELECTORS.submit).first().click();
  // keep the login POST window open until the app has moved on (URL change or
  // password field gone), at most 20s
  await page
    .waitForFunction(
      ([url, sel]) => location.href !== url || !document.querySelector(sel),
      [loginUrl, SELECTORS.pass],
      { timeout: 20000 },
    )
    .catch(() => {});
  await page.waitForLoadState('networkidle').catch(() => {});
  await page.waitForTimeout(1500);
  loginPhase = false; // from here on every POST is blocked
  await page.screenshot({ path: path.join(OUT, 'after-login.png'), fullPage: true });
  const stillOnLogin = (await page.locator(SELECTORS.pass).count()) > 0;
  console.log(stillOnLogin ? 'WARNING: password field still visible — login probably failed, check out/after-login.png' : 'Logged in.');

  // ---- menu tree ----
  const collectMenu = (scopeSel) => page.evaluate(MENU_SCRIPT, scopeSel);
  const all = new Map();
  const add = (items) => items.forEach((m) => all.set(`${m.href}|${m.name}`, m));
  // Open collapsed sidebar groups one at a time and collect after every click,
  // because accordion menus close the previous group. Only buttons inside the
  // sidebar (SELECTORS.navToggle) are clicked, each at most once per page, and
  // every POST stays blocked, so this cannot change data.
  const expandAndCollect = async (prefix) => {
    const clicked = new Set();
    for (let n = 0; n < MAX_TOGGLES; n++) {
      const handles = await page.$$(SELECTORS.navToggle);
      let target = null;
      for (const h of handles) {
        // keyed by label + DOM position: group names like "REPORTS" repeat in every module
        const info = await h.evaluate((el) => {
          const label = (el.innerText || el.getAttribute('aria-label') || '').replace(/\s+/g, ' ').trim().slice(0, 60);
          const trail = [];
          for (let n = el; n && n.parentElement; n = n.parentElement) trail.unshift([...n.parentElement.children].indexOf(n));
          return { label, key: `${label}@${trail.join('.')}`, open: el.getAttribute('aria-expanded') === 'true' };
        });
        if (!info.label || info.label.length > 50 || info.open || clicked.has(info.key) || !(await h.isVisible())) continue;
        target = { h, key: info.key };
        break;
      }
      if (!target) break;
      clicked.add(target.key);
      await target.h.click({ timeout: 1000 }).catch(() => {});
      await page.waitForTimeout(350);
      const found = await collectMenu(`${SELECTORS.menu}, main`);
      add(prefix ? found.filter((m) => m.href && new URL(m.href).pathname.startsWith(prefix)) : found);
    }
    return clicked.size;
  };
  // every run merges into what earlier runs found; menu.json only ever grows
  const prev = path.join(OUT, 'menu.json');
  if (fs.existsSync(prev)) add(JSON.parse(fs.readFileSync(prev, 'utf8')));
  add(await collectMenu(SELECTORS.menu));
  if (DISCOVER) {
    if (!ONLY.length) await expandAndCollect(null);
    const modules = [...new Set([...all.values()].map((m) => m.href).filter((h) => h && /^\/m\/[^/]+\/?$/.test(new URL(h).pathname)))]
      .filter((h) => !ONLY.length || ONLY.some((o) => new URL(h).pathname.replace(/\/$/, '').endsWith(`/${o}`)));
    console.log(`Discovering inside ${modules.length} module pages...`);
    for (const href of modules) {
      try {
        await page.goto(href, { waitUntil: 'networkidle', timeout: 60000 });
        await page.waitForTimeout(1200);
        const prefix = new URL(href).pathname.replace(/\/$/, '');
        const before = all.size;
        add((await collectMenu(`${SELECTORS.menu}, main`)).filter((m) => m.href && new URL(m.href).pathname.startsWith(prefix)));
        const toggles = await expandAndCollect(prefix);
        const found = { length: all.size - before };
        fs.mkdirSync(path.join(OUT, 'modules'), { recursive: true });
        await page.screenshot({ path: path.join(OUT, 'modules', `${slug(prefix)}.png`), fullPage: true });
        console.log(`  ${prefix}: +${found.length} links (${toggles} toggles opened)`);
      } catch (e) {
        console.log(`  ERR ${href}: ${e.message.split('\n')[0]}`);
      }
      await sleep(DELAY);
    }
  }
  const menu = [...all.values()];
  writeJson(path.join(OUT, 'menu.json'), menu);
  console.log(`Menu items found: ${menu.length} (out/menu.json)`);

  if (MENU_ONLY || (!MODULE && !SCREENS && !DIALOGS)) {
    await browser.close();
    blockedLog.end();
    allowedLog.end();
    return;
  }

  // ---- screens ----
  // --screens: every /m/<module>/<screen> path (optionally --only <module slugs>);
  // --module "text": menu items whose group/name contains the text.
  const screenPath = (m) => (m.href ? new URL(m.href).pathname.replace(/\/$/, '') : '');
  let wanted;
  if (SCREENS || DIALOGS) {
    const byPath = new Map();
    for (const m of menu) {
      const p = screenPath(m);
      const parts = p.split('/').filter(Boolean);
      if (parts.length < 3 || parts[0] !== 'm') continue;
      if (ONLY.length && !ONLY.includes(parts[1])) continue;
      if (!byPath.has(p)) byPath.set(p, m);
    }
    wanted = [...byPath.values()];
  } else {
    wanted = menu.filter((m) => [...m.path, m.name].some((x) => x.toLowerCase().includes(MODULE.toLowerCase())));
  }
  const targets = LIMIT ? wanted.slice(0, LIMIT) : wanted;
  console.log(`Screens selected: ${wanted.length}, crawling ${targets.length}`);
  const indexFile = path.join(OUT, 'fields_index.json');
  const index = fs.existsSync(indexFile) ? JSON.parse(fs.readFileSync(indexFile, 'utf8')) : {};
  const home = page.url();
  const SAFE_TAB = (t) => t && t.length <= 40 && !/delete|remove|approve|reject|post|submit|save|cancel|close|logout|sign out/i.test(t);

  const extract = (scopeSel = null) =>
    page.evaluate((scopeSel) => {
      const visible = (el) => !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
      const clean = (t) => (t || '').replace(/\s+/g, ' ').trim();
      const labelFor = (el) => {
        if (el.id) {
          const l = document.querySelector(`label[for="${CSS.escape(el.id)}"]`);
          if (l) return clean(l.innerText);
        }
        const wrap = el.closest('label');
        if (wrap) return clean(wrap.innerText);
        // nearest real label in the same form row; never a sibling's value text
        let n = el.parentElement;
        for (let i = 0; i < 3 && n; i++, n = n.parentElement) {
          const lab = n.querySelector(':scope > label, :scope > span.label, :scope > [class*="label" i]');
          if (lab && clean(lab.innerText) && clean(lab.innerText).length <= 60) return clean(lab.innerText);
        }
        return el.getAttribute('aria-label') || el.getAttribute('placeholder') || el.getAttribute('title') || '';
      };
      const scoped = scopeSel ? [...document.querySelectorAll(scopeSel)].filter(visible).pop() : null;
      const main = scoped || document.querySelector('main') || document.body;
      const fields = [];
      const seenFields = new Set();
      for (const el of main.querySelectorAll('input, select, textarea, [role="combobox"]')) {
        if (!visible(el) || ['hidden', 'submit', 'button', 'image', 'reset'].includes(el.type)) continue;
        if (el.closest('tbody')) continue; // inline-edit cells: the grid columns already describe them
        const label = labelFor(el);
        const key = `${label}|${el.tagName}|${el.type}`;
        if (seenFields.has(key)) continue;
        seenFields.add(key);
        fields.push({
          label: label.slice(0, 80),
          name: el.name || el.id || '',
          type: el.tagName === 'SELECT' ? 'select' : el.tagName === 'TEXTAREA' ? 'textarea' : el.getAttribute('role') === 'combobox' ? 'combobox' : el.type,
          required: !!el.required || /\*\s*$/.test(label),
          placeholder: el.getAttribute('placeholder') || '',
          options: el.tagName === 'SELECT' ? [...el.options].map((o) => clean(o.text)).filter(Boolean).slice(0, 30) : [],
        });
      }
      const grids = [...main.querySelectorAll('table')]
        .filter(visible)
        .map((t) => [...t.querySelectorAll('thead th, tr:first-child th')].map((th) => clean(th.innerText)).filter(Boolean))
        .filter((h) => h.length);
      const sections = [...main.querySelectorAll('fieldset legend, h2, h3, h4')].filter(visible).map((h) => clean(h.innerText)).filter((t) => t && t.length <= 60);
      const buttons = [...main.querySelectorAll('button, a[role="button"]')].filter(visible).map((b) => clean(b.innerText)).filter((t) => t && t.length <= 40);
      const tabs = [...main.querySelectorAll('[role="tab"]')].filter(visible).map((t) => ({ label: clean(t.innerText), selected: t.getAttribute('aria-selected') === 'true' }));
      const h1 = main.querySelector('h1');
      return {
        title: clean(h1 ? h1.innerText : ''),
        description: clean(h1 && h1.parentElement ? h1.parentElement.innerText : '').slice(0, 300),
        fields,
        grids,
        sections: [...new Set(sections)],
        buttons: [...new Set(buttons)],
        tabs,
      };
    }, scopeSel);


  const OPENER = /^\+?\s*(new|add|create)\b/i;
  const SUBMIT = /^(save|submit|create|add|confirm|post)\b/i;
  const captureDialogs = async (item, dir, name) => {
    const out = [];
    const load = async () => {
      loadWindow = true;
      await page.goto(item.href, { waitUntil: 'domcontentloaded', timeout: 60000 });
      await page.waitForTimeout(2500);
      loadWindow = false;
    };
    await load();
    const openers = [...new Set(await page.$$eval('main button, main a[role="button"]', (els) =>
      els.filter((e) => e.offsetWidth || e.offsetHeight).map((e) => (e.innerText || '').replace(/\s+/g, ' ').trim()).filter((t) => t && t.length <= 40)))]
      .filter((t) => OPENER.test(t))
      .slice(0, 3);
    for (const label of openers) {
      try {
        await page.locator('main button, main a[role="button"]').filter({ hasText: label }).first().click({ timeout: 3000 });
        await page.waitForTimeout(1500);
        const scope = (await page.locator('[role="dialog"]').count()) ? '[role="dialog"]' : null;
        const form = await extract(scope);
        await page.screenshot({ path: path.join(OUT, 'screens', dir, `${name}__dlg_${slug(label)}.png`), fullPage: true });
        // press Save/Submit on the empty form to see which fields the app requires
        const root = scope ? page.locator(scope).last() : page.locator('main');
        const submit = root.locator('button').filter({ hasText: SUBMIT }).last();
        let validation = [];
        if (await submit.count()) {
          await submit.click({ timeout: 3000 }).catch(() => {});
          await page.waitForTimeout(1500);
          validation = await page.evaluate(() => {
            const clean = (t) => (t || '').replace(/\s+/g, ' ').trim();
            const vis = (el) => !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
            const msgs = [...document.querySelectorAll('[role="alert"], [aria-live] *, .text-destructive, .text-red-500, .error, .invalid-feedback, [data-sonner-toast], [id$="-error"], [id$="form-item-message"]')]
              .filter(vis).map((e) => clean(e.innerText)).filter((t) => t && t.length <= 200);
            const invalid = [...document.querySelectorAll('[aria-invalid="true"]')].map((e) => e.name || e.id || e.getAttribute('placeholder') || '').filter(Boolean);
            return [...new Set(msgs)].map((m) => ({ message: m })).concat(invalid.map((f) => ({ invalid: f })));
          });
          await page.screenshot({ path: path.join(OUT, 'screens', dir, `${name}__dlg_${slug(label)}__submit.png`), fullPage: true });
        }
        out.push({ opener: label, inDialog: !!scope, ...form, validation });
      } catch (e) {
        out.push({ opener: label, error: e.message.split('\n')[0] });
      }
      await page.keyboard.press('Escape').catch(() => {});
      await load();
    }
    return out;
  };

  for (const item of targets) {
    const p = screenPath(item);
    const parts = p.split('/').filter(Boolean);
    const dir = SCREENS || DIALOGS ? slug(parts[1] || 'misc') : slug(MODULE);
    const name = SCREENS || DIALOGS ? slug(parts.slice(2).join('_') || item.name) : slug(item.name);
    const jsonFile = path.join(OUT, 'fields', dir, `${name}.json`);
    if (DIALOGS) {
      if (!fs.existsSync(jsonFile)) continue; // run --screens first
      const known = JSON.parse(fs.readFileSync(jsonFile, 'utf8'));
      if (known.dialogs && !flag('--refresh')) continue;
      try {
        const dialogs = await captureDialogs(item, dir, name);
        writeJson(jsonFile, { ...known, dialogs });
        console.log(`  dlg ${p} (${dialogs.length} dialog(s))`);
      } catch (e) {
        console.log(`  ERR ${p}: ${e.message.split('\n')[0]}`);
      }
      await sleep(DELAY);
      continue;
    }
    if (SCREENS && fs.existsSync(jsonFile) && !flag('--refresh')) continue; // resumable
    try {
      loadWindow = true;
      if (flag('--click-menu') || !item.href || item.href.endsWith('#') || item.href.startsWith('javascript:')) {
        await page.goto(home, { waitUntil: 'networkidle' });
        await page.locator(SELECTORS.menu).getByText(item.name, { exact: true }).first().click({ timeout: 10000 });
      } else {
        await page.goto(item.href, { waitUntil: 'networkidle', timeout: 60000 });
      }
      await page.waitForTimeout(1500);
      loadWindow = false;
      const views = [];
      const first = await extract();
      fs.mkdirSync(path.join(OUT, 'screens', dir), { recursive: true });
      await page.screenshot({ path: path.join(OUT, 'screens', dir, `${name}.png`), fullPage: true });
      views.push({ tab: (first.tabs.find((t) => t.selected) || {}).label || '', ...first });
      // open the other tabs (e.g. "New Booking" behind "Records"); tab switches only
      for (const t of first.tabs.filter((x) => !x.selected && SAFE_TAB(x.label)).slice(0, 6)) {
        try {
          loadWindow = true;
          await page.locator('main [role="tab"]').filter({ hasText: t.label }).first().click({ timeout: 3000 });
          await page.waitForLoadState('networkidle').catch(() => {});
          await page.waitForTimeout(1200);
          loadWindow = false;
          const v = await extract();
          await page.screenshot({ path: path.join(OUT, 'screens', dir, `${name}__${slug(t.label)}.png`), fullPage: true });
          views.push({ tab: t.label, ...v });
        } catch (e) {
          loadWindow = false;
        }
      }
      loadWindow = false;
      const keep = fs.existsSync(jsonFile) ? JSON.parse(fs.readFileSync(jsonFile, 'utf8')) : {};
      writeJson(jsonFile, { ...keep, menu: item, url: page.url(), views }); // a refresh keeps captured dialogs
      const allFields = views.flatMap((v) => v.fields.concat(v.grids.map((g) => ({ label: 'GRID', name: '', type: 'grid', required: false, options: g }))));
      index[item.name] = allFields;
      console.log(`  ok  ${p} (${views.length} view(s), ${allFields.length} fields)`);
    } catch (e) {
      loadWindow = false;
      console.log(`  ERR ${p || item.name}: ${e.message.split('\n')[0]}`);
    }
    await sleep(DELAY);
  }

  writeJson(indexFile, index);
  await browser.close();
  blockedLog.end();
  allowedLog.end();
  console.log('Done. Blocked requests (should be reviewed): out/blocked.log');
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
