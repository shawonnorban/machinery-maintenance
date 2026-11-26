// Screenshots of Brickland screens in its light theme (read-only: every
// non-GET request after login is blocked). Usage:
//   node AnnotechERP/tools/erp_crawler/light_shots.mjs <path> [<path> ...]
// e.g. /m/security/lookup-tables. Output: out/light/<slug>.png (+ __<tab>.png).
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const require = createRequire(path.join(here, '../../../frontend/package.json'));
const { chromium } = require('@playwright/test');

const OUT = path.join(here, 'out', 'light');
const file = process.env.ERP_AUDIT_ENV || path.join(os.homedir(), '.annotech-erp-audit.env');
const env = Object.fromEntries(
  fs.readFileSync(file, 'utf8').split(/\r?\n/).map((l) => l.match(/^\s*([A-Z_]+)\s*=\s*(.*)\s*$/)).filter(Boolean).map((m) => [m[1], m[2].replace(/^["']|["']$/g, '')]),
);
const paths = process.argv.slice(2);
const slug = (s) => s.replace(/[^\p{L}\p{N}]+/gu, '_').replace(/^_|_$/g, '') || 'home';

fs.mkdirSync(OUT, { recursive: true });
const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, ignoreHTTPSErrors: true, colorScheme: 'light' });
let loginPhase = true;
await context.route('**/*', (route) => {
  const method = route.request().method();
  if (loginPhase || ['GET', 'HEAD', 'OPTIONS'].includes(method)) return route.continue();
  return route.abort();
});
const page = await context.newPage();
await page.goto(env.ERP_URL, { waitUntil: 'networkidle', timeout: 60000 });
await page.locator('input[type=email], input[name=email], input[name=username], input[type=text]').first().fill(env.ERP_USER);
await page.locator('input[type=password]').first().fill(env.ERP_PASS);
await page.locator('button[type=submit]').first().click();
await page.waitForFunction(() => !document.querySelector('input[type=password]'), null, { timeout: 30000 }).catch(() => {});
await page.waitForLoadState('networkidle').catch(() => {});
loginPhase = false;

const base = new URL(env.ERP_URL).origin;
let themed = false;
for (const p of paths) {
  await page.goto(base + p, { waitUntil: 'networkidle', timeout: 90000 }).catch(() => {});
  await page.waitForTimeout(1500);
  if (!themed) {
    // The sun icon in the topbar toggles the theme; keep it light from here on.
    const isDark = await page.evaluate(() => document.documentElement.classList.contains('dark') || document.documentElement.dataset.theme === 'dark');
    if (isDark) {
      const toggle = page.locator('header button:has(svg.lucide-sun), header button[aria-label*="theme" i], header button[title*="theme" i]').first();
      if (await toggle.count()) await toggle.click();
      await page.waitForTimeout(800);
    }
    themed = true;
  }
  const name = slug(p);
  await page.screenshot({ path: path.join(OUT, `${name}.png`) });
  console.log('shot', p, 'dark=', await page.evaluate(() => document.documentElement.classList.contains('dark')));
  if (process.env.TABS) {
    const tabs = page.getByRole('tab');
    const count = await tabs.count();
    for (let i = 0; i < Math.min(count, 4); i++) {
      const label = (await tabs.nth(i).innerText()).trim();
      await tabs.nth(i).click().catch(() => {});
      await page.waitForTimeout(1200);
      await page.screenshot({ path: path.join(OUT, `${name}__${slug(label).slice(0, 30)}.png`) });
    }
  }
}
await browser.close();
