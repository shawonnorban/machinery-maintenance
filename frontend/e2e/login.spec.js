import { test, expect } from "@playwright/test";

/**
 * The one scaffolded end-to-end test (Phase F/H of docs/12-Stack-Migration-
 * Implementation-Plan.md) — proves the harness itself works against the real
 * coexistence stack (nginx → Next.js's own `/api/auth/login` route → the
 * Laravel API) before anyone builds the tenant-isolation and offline-retry
 * suites the roadmap actually calls for. Credentials come from
 * `DemoTenantSeeder::PASSWORD` and its seeded `owner@delta.test` account.
 */
test("a seeded user can sign in and reach the dashboard", async ({ page }) => {
  await page.goto("/login");

  await page.getByLabel("Email").fill("owner@delta.test");
  await page.getByLabel("Password").fill("password123");
  await page.getByRole("button", { name: "Sign in" }).click();

  // Anchored on the path right after the host — `/\/(?!login)/` alone
  // matches almost any URL (the "//" after "http:" already satisfies it),
  // including the login page itself, so it never actually proved the
  // redirect happened. A generous timeout: Turbopack dev-mode compiles the
  // dashboard route on first hit (~5-8s observed live), on top of the
  // login round trip itself.
  await page.waitForURL((url) => url.pathname !== "/login", { timeout: 20_000 });
});
