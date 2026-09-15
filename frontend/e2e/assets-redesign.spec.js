import { test, expect } from "@playwright/test";

/**
 * Verifies the Assets index redesign: a KPI tile row above the table
 * (backed by the new `/assets/counts` endpoint), a card-wrapped filter row,
 * and criticality/status badges with a visible border (the pale `*-subtle`
 * fill alone read as plain colored text on a white row).
 *
 * Generous timeout: Turbopack dev-mode can take 30s+ to compile this route
 * on first hit under concurrent load (observed live), well past the
 * default 5s assertion timeout.
 */
test("assets index shows KPI tiles and bordered badges", async ({ page }) => {
  await page.goto("/login");
  await page.getByLabel("Email").fill("owner@delta.test");
  await page.getByLabel("Password").fill("password123");
  await page.getByRole("button", { name: "Sign in" }).click();
  await page.waitForURL((url) => url.pathname !== "/login", { timeout: 20_000 });

  await page.goto("/assets");
  await page.waitForSelector("table", { timeout: 45_000 });

  const kpiRow = page.locator("div.grid", { has: page.getByText("Total assets") });
  await expect(kpiRow.getByText("Total assets")).toBeVisible();
  await expect(kpiRow.getByText("Running", { exact: true })).toBeVisible();
  await expect(kpiRow.getByText("Needs attention")).toBeVisible();
  await expect(kpiRow.getByText("Critical", { exact: true })).toBeVisible();

  // Column order is: checkbox, Asset, Type, Factory, Criticality, Status.
  const firstCriticalityBadge = page.locator("tbody tr").first().locator("td").nth(4).locator("span[data-slot=badge]");
  const borderWidth = await firstCriticalityBadge.evaluate((el) => getComputedStyle(el).borderWidth);
  expect(parseFloat(borderWidth)).toBeGreaterThan(0);
});
