import { defineConfig, devices } from "@playwright/test";

/**
 * Points at the Next.js container's own port (3010), not the coexistence
 * nginx port (8010) — `/login` itself hasn't been cut over through
 * `docker/nginx/default.conf` yet (only `/metering` and a few Route
 * Handlers have, per docs/12-Stack-Migration-Implementation-Plan.md Phase
 * F/H), so hitting nginx's `/login` would exercise Blade's login page, not
 * the React one these tests are written against. Once a module's own pages
 * are dark-launched through nginx, point `PLAYWRIGHT_BASE_URL` at 8010 (or
 * add a project per base URL) to test through the same path real users use.
 *
 * Browser binaries need glibc; `docker/node/Dockerfile`'s frontend image is
 * Alpine (musl), so tests don't run inside it. Run them with the official
 * `mcr.microsoft.com/playwright` image instead — see the `playwright`
 * service in docker-compose.yml (profile `test`, not part of the default
 * stack) — or from a host machine that has `npx playwright install` browsers.
 */
export default defineConfig({
  testDir: "./e2e",
  fullyParallel: true,
  retries: process.env.CI ? 2 : 0,
  reporter: "list",
  // Turbopack dev-mode compiles each route on its first hit (~5-8s
  // observed live for the dashboard) — the default 30s per-test budget is
  // tight once a test chains a login onto a second route visit.
  timeout: 45_000,
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL ?? "http://localhost:3010",
    trace: "on-first-retry",
  },
  projects: [{ name: "chromium", use: { ...devices["Desktop Chrome"] } }],
});
