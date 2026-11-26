// Brickland panel reader: opens a screen, runs setup clicks, then for each named tab
// of a panel dumps its labelled fields (control type, options), tables, buttons and
// texts, plus a screenshot of the panel element itself (not the clipped page).
// usage: node panel.mjs <plan.json> <outdir>   (WRITE=1 allows non-GET requests)
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
const context = await browser.newContext({ viewport: { width: 1600, height: Number(process.env.VIEWPORT_H || 1000) }, ignoreHTTPSErrors: true });
const page = await context.newPage();
let open = true;
await context.route('**/*', (route) => {
  const m = route.request().method();
  if (['GET', 'HEAD', 'OPTIONS'].includes(m) || open || process.env.WRITE === '1') return route.continue();
  return route.abort();
});
await page.goto(env.ERP_URL, { waitUntil: 'networkidle', timeout: 60000 });
// LOGIN_AS=SUP signs in with the probe supplier login (SUP_USER / SUP_PASS), LOGIN_AS=MERCH with the probe merchandiser (MERCH_EMAIL / MERCH_PASS), instead of the admin.
const who = process.env.LOGIN_AS === 'SUP' ? [env.SUP_USER, env.SUP_PASS] : process.env.LOGIN_AS === 'MERCH' ? [env.MERCH_EMAIL, env.MERCH_PASS] : [env.ERP_USER, env.ERP_PASS];
await page.locator('input[type="email"], input[type="text"]').first().fill(who[0]);
await page.locator('input[type="password"]').first().fill(who[1]);
await page.locator('button[type="submit"]').first().click();
await page.waitForFunction(() => !document.querySelector('input[type="password"]'), null, { timeout: 30000 }).catch(() => {});
await page.waitForLoadState('networkidle').catch(() => {});
open = false;
const idle = async (ms = 1500) => { await page.waitForLoadState('networkidle').catch(() => {}); await page.waitForTimeout(ms); };

// Everything inside the element matched by `root`, grouped the way a form reads.
const dump = (root) => page.evaluate((sel) => {
  const box = document.querySelector(sel) || document.querySelector('main');
  const txt = (e) => (e?.innerText || e?.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 200);
  const ctl = (el) => {
    if (!el) return null;
    const t = el.tagName.toLowerCase();
    const base = { tag: t, type: el.type || '', name: el.name || '', placeholder: el.placeholder || '', value: el.type === 'checkbox' ? el.checked : (el.value || '').slice(0, 60), required: el.required, disabled: el.disabled };
    if (t === 'select') base.options = [...el.options].map((o) => o.text).slice(0, 60);
    return base;
  };
  // The text a control sits beside: the nearest earlier sibling with words, a few levels up.
  const labelOf = (el) => {
    let n = el;
    for (let i = 0; i < 4 && n; i++) {
      let prev = n.previousElementSibling;
      while (prev && !txt(prev)) prev = prev.previousElementSibling;
      if (prev && txt(prev).length < 70) return txt(prev);
      n = n.parentElement;
    }
    return '';
  };
  const controls = [...box.querySelectorAll('input,select,textarea')].map((el) => ({ near: labelOf(el), ...ctl(el) }));
  const fields = [];
  for (const lab of box.querySelectorAll('label')) {
    let el = lab.htmlFor ? document.getElementById(lab.htmlFor) : lab.querySelector('input,select,textarea');
    if (!el) {
      // Brickland lays a label beside its control: the next control after it in the DOM.
      const all = [...box.querySelectorAll('input,select,textarea')];
      el = all.find((c) => lab.compareDocumentPosition(c) & Node.DOCUMENT_POSITION_FOLLOWING);
    }
    fields.push({ label: txt(lab), control: ctl(el) });
  }
  return {
    headings: [...box.querySelectorAll('h1,h2,h3,h4,legend')].map(txt).filter(Boolean),
    fields,
    controls,
    unlabelled: [...box.querySelectorAll('input,select,textarea')].filter((c) => !c.labels || c.labels.length === 0).map(ctl).slice(0, 80),
    tables: [...box.querySelectorAll('table')].map((t) => ({ head: [...t.querySelectorAll('thead th')].map(txt), rows: [...t.querySelectorAll('tbody tr')].slice(0, 6).map((r) => [...r.children].map((c) => { const k = c.querySelector('input,select,button'); return k ? `[${k.tagName.toLowerCase()}${k.type ? ':' + k.type : ''}${k.placeholder ? ' ph=' + k.placeholder : ''}${k.tagName === 'SELECT' ? ' opts=' + [...k.options].map((o) => o.text).slice(0, 8).join('|') : ''}] ${txt(c)}` : txt(c); })), count: t.querySelectorAll('tbody tr').length })),
    buttons: [...new Set([...box.querySelectorAll('button')].map((b) => txt(b) || b.getAttribute('aria-label') || '').filter(Boolean))],
    texts: [...new Set([...box.querySelectorAll('p,small,span')].map(txt).filter((t) => t.length > 18))].slice(0, 40),
  };
}, root);

const report = {};
// Native confirm()/alert() dialogs: their text is kept; ACCEPT_DIALOGS=1 accepts them (else dismissed).
report.native_dialogs = [];
page.on('dialog', async (d) => { report.native_dialogs.push(`${d.type()}: ${d.message()}`); if (process.env.ACCEPT_DIALOGS === '1') await d.accept(); else await d.dismiss(); });
for (const step of plan) {
  try {
    if (step.goto) { open = true; await page.goto(new URL(env.ERP_URL).origin + step.goto, { waitUntil: 'networkidle', timeout: 90000 }); await idle(); open = false; }
    if (step.click) { open = true; await page.locator(step.click).first().click({ timeout: 15000 }); await idle(step.wait ?? 1500); open = false; }
    // Tags the card around a heading so `root: "[data-panel]"` can find it.
    if (step.mark) await page.evaluate((text) => {
      document.querySelectorAll('[data-panel]').forEach((e) => e.removeAttribute('data-panel'));
      const h = [...document.querySelectorAll('h1,h2,h3,h4,span,div')].find((e) => e.children.length === 0 && e.textContent.trim().startsWith(text));
      let card = h;
      // Up to the first ancestor that holds the panel's own form (its tabs and Save).
      while (card && !(card.offsetHeight > 300 && card.querySelector('button') && [...card.querySelectorAll('button')].some((b) => b.textContent.trim() === 'Save'))) card = card.parentElement;
      (card || h)?.setAttribute('data-panel', '1');
    }, step.mark);
    // A click and every request, download and new tab it sets off over `wait` ms (`as`: name) — what a Preview button actually does.
    if (step.netclick) {
      open = true;
      const seen = [];
      const onReq = (r) => seen.push(`${r.method()} ${r.url()}`);
      const onRes = (r) => seen.push(`<- ${r.status()} ${r.headers()['content-type'] ?? ''} ${r.url()}`);
      const onDl = (d) => seen.push(`DOWNLOAD ${d.suggestedFilename()}`);
      const onPage = (p) => seen.push(`PAGE ${p.url()}`);
      context.on('request', onReq);
      context.on('response', onRes);
      page.on('download', onDl);
      context.on('page', onPage);
      await page.locator(step.netclick).first().click({ timeout: 15000 });
      await page.waitForTimeout(step.wait ?? 15000);
      context.off('request', onReq);
      context.off('response', onRes);
      page.off('download', onDl);
      context.off('page', onPage);
      report[step.as] = seen.filter((s) => !/\.(js|css|woff2?|png|svg|ico)(\?|$)/.test(s));
      open = false;
    }
    // A click that may open a new tab (a report's Preview): its url, text, tables and a screenshot (`as`: name); without one, where the page went.
    if (step.popclick) {
      open = true;
      const [popup] = await Promise.all([context.waitForEvent('page', { timeout: step.wait ?? 15000 }).catch(() => null), page.locator(step.popclick).first().click({ timeout: 15000 })]);
      const target = popup ?? page;
      await target.waitForLoadState('networkidle').catch(() => {});
      await target.waitForTimeout(2500);
      await target.screenshot({ path: path.join(outdir, `${step.as}.png`), fullPage: true }).catch(() => {});
      report[step.as] = await target.evaluate(() => ({
        url: location.href,
        text: document.body.innerText.slice(0, 6000),
        tables: [...document.querySelectorAll('table')].slice(0, 6).map((t) => ({ head: [...t.querySelectorAll('thead th')].map((c) => c.innerText.trim()), rows: [...t.querySelectorAll('tbody tr')].slice(0, 8).map((r) => [...r.children].map((c) => c.innerText.trim())) })),
      })).catch((e) => ({ error: e.message }));
      report[step.as].popup = Boolean(popup);
      if (popup) await popup.close();
      open = false;
    }
    if (step.peek) { await page.locator(step.peek).first().click({ timeout: 15000 }); await page.waitForTimeout(step.wait ?? 1500); }
    if (step.escape) await page.keyboard.press('Escape');
    // A text of "$ENV:NAME" is read from the env file, so a secret never sits in a plan.
    const text = typeof step.text === 'string' && step.text.startsWith('$ENV:') ? env[step.text.slice(5)] ?? '' : step.text;
    if (step.type) { await page.locator(step.type).first().click({ timeout: 15000 }); await page.locator(step.type).first().fill(text ?? ''); await page.waitForTimeout(1200); }
    // The open popup list (search pickers): its option texts.
    if (step.popup) report[step.popup] = await page.evaluate(() => [...document.querySelectorAll('[role=option], [role=listbox] li, ul.absolute li, [data-radix-popper-content-wrapper] li, .absolute li')].map((e) => e.innerText.trim()).filter(Boolean).slice(0, 80));
    if (step.fillsel) { await page.locator(step.fillsel).first().fill(text); await page.waitForTimeout(400); }
    if (step.choose) { await page.locator(step.choose).first().selectOption(step.index !== undefined ? { index: step.index } : { label: step.label }); await page.waitForTimeout(800); }
    // Where the page now is, and where links point (`hrefs` lists text + href of every match).
    // Scroll the page's own scroller (the app scrolls an inner element, not the window) down `scroll` pixels.
    if (step.scroll !== undefined) {
      await page.evaluate((y) => {
        const scroller = [...document.querySelectorAll('*')].find((e) => e.scrollHeight > e.clientHeight + 50 && ['auto', 'scroll'].includes(getComputedStyle(e).overflowY));
        (scroller ?? document.scrollingElement).scrollTop = y;
      }, step.scroll);
      await page.waitForTimeout(600);
    }
    // The whole page, for forms taller than the window.
    if (step.fullshot) await page.screenshot({ path: path.join(outdir, `${step.fullshot}.png`), fullPage: true });
    if (step.url) report[step.url] = page.url();
    if (step.hrefs) report[step.hrefs] = await page.locator(step.of).evaluateAll((els) => els.map((e) => [e.innerText.trim().replace(/\s+/g, ' '), e.getAttribute('href')]));
    // Plain texts of every match (computed cells, totals, print previews).
    if (step.texts) report[step.texts] = await page.locator(step.of).allInnerTexts();
    if (step.options) report[step.options] = await page.locator(step.of).first().evaluate((el) => [...el.options].map((o) => o.text));
    if (step.html) report[step.html] = await page.locator(step.of).evaluateAll((els) => els.map((e) => e.outerHTML.slice(0, 700)));
    if (step.dump) {
      const el = page.locator(step.root).first();
      await el.scrollIntoViewIfNeeded().catch(() => {});
      await el.screenshot({ path: path.join(outdir, `${step.dump}.png`) }).catch((e) => console.log('shot failed', e.message.split('\n')[0]));
      report[step.dump] = await dump(step.root);
      console.log('dump', step.dump);
    }
  } catch (e) {
    console.log('FAILED', JSON.stringify(step), e.message.split('\n')[0]);
  }
}
fs.writeFileSync(path.join(outdir, 'report.json'), JSON.stringify(report, null, 1));
await browser.close();
