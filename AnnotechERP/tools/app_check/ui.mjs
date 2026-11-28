// Annotech dev-app click-through: signs in through the real login form, runs a
// plan of UI steps against the running dev frontend and keeps what it saves
// (the records stay as sample data). Screenshots and a report land in <outdir>.
// usage: node ui.mjs <plan.json> <outdir>
//
// Credentials: APP_URL / APP_USER / APP_PASS from the gitignored (AS_USER / AS_PASS sign in as someone else)
// .annotech-erp-audit.env, else the seeded demo owner (DemoTenantSeeder).
//
// Steps (run in order; a failed step is logged and the plan goes on):
//   {goto: "/path"}                      open a page and wait for it to settle
//   {click: "<selector>"}                Playwright selector, e.g. "role=button[name='Save lab dip program']"
//   {fill: "<label>", text: "..."}       a text/date/number box by its label (or `sel` for a selector; `exact: true` for the whole label)
//   {pick: "<label>", option: "..."}     open a select by its label and take the option containing the text
//   {upload: "<input selector>", file: "path"}   a file into a file input
//   {chooser: "<button selector>", file: "path"} a file through the chooser the button opens
//   {see: "<text>", as: "key"}           whether the text is on the page now
//   {texts: "key", of: "<selector>"}     inner texts of every match
//   {shot: "name"}                       full-page screenshot
//   {wait: 1500}                         settle for a while
//   {dialog: "accept", text: "..."}     answer the next confirm / prompt (before the click that opens it)
//   {type: "text"}                       type into whatever has focus (a sheet cell's editor)
//   {paste: "a\tb\nc\td"}                paste text (tab / new line separated, as from Excel) into what has focus
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
const require = createRequire('C:/wamp64/www/machinery-maintenance/frontend/package.json');
const { chromium } = require('@playwright/test');

const [planFile, outdir] = process.argv.slice(2);
const plan = JSON.parse(fs.readFileSync(planFile, 'utf8'));
const envFile = 'C:/wamp64/www/machinery-maintenance/.annotech-erp-audit.env';
const env = fs.existsSync(envFile)
  ? Object.fromEntries(fs.readFileSync(envFile, 'utf8').split(/\r?\n/).map((l) => l.match(/^\s*([A-Z_]+)\s*=\s*(.*)$/)).filter(Boolean).map((m) => [m[1], m[2].replace(/^["']|["']$/g, '')]))
  : {};
const base = env.APP_URL || 'http://localhost:3010';
fs.mkdirSync(outdir, { recursive: true });

const browser = await chromium.launch();
const page = await (await browser.newContext({ viewport: { width: 1600, height: 1000 } })).newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message.split('\n')[0]));
page.on('console', (m) => m.type() === 'error' && errors.push(process.env.FULL_ERRORS ? m.text().slice(0, 4000) : m.text().split('\n')[0]));
const settle = async (ms = 1200) => { await page.waitForLoadState('networkidle', { timeout: 90000 }).catch(() => {}); await page.waitForTimeout(ms); };

await page.goto(`${base}/login`, { timeout: 120000 });
// By shape, not wording: the login form's labels follow the locale.
await page.locator('form input:not([type=password]):not([type=hidden]):not([type=checkbox])').first().fill(process.env.AS_USER || env.APP_USER || 'owner@delta.test');
await page.locator('form input[type=password]').first().fill(process.env.AS_PASS || env.APP_PASS || 'password123');
await page.locator('form button[type=submit]').first().click();
await page.waitForURL((u) => u.pathname !== '/login', { timeout: 60000 });
await settle();

// A form control by the label beside it: <label for>, aria-label, or the control in the same field box.
const escape = (text) => text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const control = (label, exact) => page.getByLabel(exact ? new RegExp(`^\\s*${escape(label)}\\s*\\*?\\s*$`) : label).first();
const fieldBox = (label) => page.locator('label, span, p').filter({ hasText: new RegExp(`^\\s*${label.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\s*\\*?\\s*$`) }).first();

const report = { errors };
for (const step of plan) {
  try {
    if (step.goto) { await page.goto(base + step.goto, { timeout: 120000 }); await settle(); }
    if (step.click) { await page.locator(step.click).first().click({ timeout: 20000 }); await settle(step.after ?? 1500); }
    if (step.fill) {
      const el = step.sel ? page.locator(step.sel).first() : control(step.fill, step.exact);
      await el.fill(step.text, { timeout: 15000 });
    }
    if (step.pick) {
      let trigger = step.sel ? page.locator(step.sel).first() : control(step.pick, step.exact);
      if (!(await trigger.count())) trigger = fieldBox(step.pick).locator('xpath=following::*[@data-slot="select-trigger"][1]');
      await trigger.click({ timeout: 15000 });
      // `exactOption`: the option whose whole text is this ("Finishing Department", not "Fabric Finishing Department").
      const want = step.exactOption ? new RegExp('^\\s*' + step.option.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\s*$') : step.option;
      await page.getByRole('option').filter({ hasText: want }).first().click({ timeout: 15000 });
      await page.waitForTimeout(400);
    }
    // A Print button's document (`printpdf`: the button, `as`: file name): its popup saved as a PDF on the page's own @page size, and a screenshot of the preview.
    if (step.printpdf) {
      const [popup] = await Promise.all([page.waitForEvent('popup', { timeout: 30000 }), page.locator(step.printpdf).first().click()]);
      await popup.waitForLoadState('load').catch(() => {});
      // `until`: ms to wait for a popup that fills in after a server round-trip (it opens blank first).
      if (step.until) await popup.waitForFunction(() => (document.body?.innerText || '').trim().length > 20, null, { timeout: step.until }).catch(() => {});
      await popup.waitForTimeout(800);
      await popup.pdf({ path: path.join(outdir, `${step.as}.pdf`), preferCSSPageSize: true, printBackground: true });
      await popup.screenshot({ path: path.join(outdir, `${step.as}.png`), fullPage: true });
      report[step.as] = (await popup.title());
      await popup.close();
      console.log('print', step.as);
    }
    // A Print link to a server PDF (`pdfhref`: the link, `as`: file name): fetched with the signed-in session and saved.
    if (step.pdfhref) {
      const href = await page.locator(step.pdfhref).first().getAttribute('href', { timeout: 15000 });
      const res = await page.request.get(new URL(href, page.url()).toString());
      fs.writeFileSync(path.join(outdir, `${step.as}.pdf`), await res.body());
      report[step.as] = { status: res.status(), type: res.headers()['content-type'] };
      console.log('pdf', step.as, res.status());
    }
    // The next browser dialog (`dialog`: "accept" | "dismiss"; `text`: what a prompt is answered with).
    if (step.dialog) page.once('dialog', (d) => (step.dialog === 'accept' ? d.accept(d.type() === 'prompt' ? (step.text ?? '') : undefined) : d.dismiss()).catch(() => {}));
    // A mouse drag (`drag`: the element, `by`: [dx, dy] pixels) — a draggable bubble, a splitter.
    if (step.drag) {
      const box = await page.locator(step.drag).first().boundingBox();
      const [dx, dy] = step.by ?? [0, 0];
      await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
      await page.mouse.down();
      for (let i = 1; i <= 10; i++) await page.mouse.move(box.x + box.width / 2 + (dx * i) / 10, box.y + box.height / 2 + (dy * i) / 10);
      await page.mouse.up();
      await page.waitForTimeout(step.after ?? 400);
    }
    // A key on the keyboard (`press`: "Escape" closes an open picker).
    if (step.press) { await page.keyboard.press(step.press); await page.waitForTimeout(step.after ?? 250); }
    // A native <select> by its option's text (`choose`: the select, `option`: part of the option label).
    if (step.choose) {
      const select = page.locator(step.choose).first();
      const label = await select.locator('option').filter({ hasText: step.option }).first().innerText({ timeout: 15000 });
      await select.selectOption({ label: label.trim() });
      await settle(step.after ?? 600);
    }
    // A file into an <input type=file> (`upload`), or through the chooser a button opens (`chooser`).
    if (step.upload) await page.locator(step.upload).first().setInputFiles(step.file);
    // A click that starts a download (Export PDF / Excel): the file is saved and its name and size reported.
    if (step.download) {
      const [file] = await Promise.all([page.waitForEvent('download', { timeout: step.timeout ?? 60000 }), page.locator(step.download).first().click()]);
      const target = path.join(outdir, step.save ?? file.suggestedFilename());
      await file.saveAs(target);
      report[step.as ?? 'download'] = { name: file.suggestedFilename(), bytes: fs.statSync(target).size, saved: target };
    }
    if (step.chooser) {
      const [chooser] = await Promise.all([page.waitForEvent('filechooser', { timeout: 15000 }), page.locator(step.chooser).first().click()]);
      await chooser.setFiles(step.file);
      await settle(step.after ?? 3000);
    }
    if (step.see) report[step.as ?? step.see] = (await page.getByText(step.see, { exact: false }).count()) > 0;
    if (step.html) report[step.html] = await page.locator(step.of).evaluateAll((els) => els.map((e) => e.outerHTML.slice(0, 600)));
    if (step.texts) report[step.texts] =(await page.locator(step.of).allInnerTexts()).map((t) => t.replace(/\s+/g, ' ').trim());
    if (step.shot) { await page.screenshot({ path: path.join(outdir, `${step.shot}.png`), fullPage: true }); console.log('shot', step.shot); }
    // {eval: "document.activeElement.tagName", as: "key"} — a value read off the page (debugging a flow)
    if (step.eval) report[step.as ?? step.eval] = await page.evaluate((src) => String(new Function(`return (${src});`)()), step.eval);
    if (step.type) { await page.keyboard.type(step.type, { delay: 15 }); await page.waitForTimeout(150); }
    if (step.paste !== undefined) {
      // A real paste event carrying the text, on the focused element.
      await page.evaluate((text) => {
        const dt = new DataTransfer();
        dt.setData('text/plain', text);
        document.activeElement?.dispatchEvent(new ClipboardEvent('paste', { clipboardData: dt, bubbles: true, cancelable: true }));
      }, step.paste);
      await page.waitForTimeout(step.after ?? 400);
    }
    if (step.wait) await page.waitForTimeout(step.wait);
  } catch (e) {
    // ERRLINES=n prints Playwright's call log too (why an element was not actionable).
    console.log('FAILED', JSON.stringify(step), e.message.split('\n').slice(0, Number(process.env.ERRLINES) || 1).join(' | '));
    await page.screenshot({ path: path.join(outdir, `failed-${plan.indexOf(step)}.png`), fullPage: true }).catch(() => {});
  }
}
report.url = page.url();
fs.writeFileSync(path.join(outdir, 'report.json'), JSON.stringify(report, null, 1));
console.log('errors', errors.length);
await browser.close();
