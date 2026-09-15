import { test, expect } from "@playwright/test";

/**
 * Regression test for a real bug (user-reported via screenshot): quantity
 * fields came back from the API as fixed-precision decimal strings
 * ("22.0000") and were printed verbatim instead of formatted — see
 * `frontend/src/lib/format.js` and docs/12-Stack-Migration-Implementation-
 * Plan.md's Phase D entry for the fix. Runs through a real browser against
 * the seeded demo data so a future regression (someone reverting to a raw
 * `{value}` interpolation) fails a real assertion, not just a code review.
 */
test("a spare part's on-hand quantity shows without trailing decimal zeros", async ({ page }) => {
  await page.goto("/login");
  await page.getByLabel("Email").fill("owner@delta.test");
  await page.getByLabel("Password").fill("password123");
  await page.getByRole("button", { name: "Sign in" }).click();
  // See login.spec.js for why this is generous: Turbopack dev-mode compiles
  // the dashboard route on first hit.
  await page.waitForURL((url) => url.pathname !== "/login", { timeout: 20_000 });

  await page.goto("/inventory/parts?search=BLT-M35-A");
  await page.getByRole("link", { name: /BLT-M35-A/ }).first().click();
  await expect(page).toHaveURL(/\/inventory\/parts\/[^/]+$/);

  const onHand = page.getByText("On hand").locator("..").getByText(/PCS/);
  await expect(onHand).toBeVisible();
  await expect(onHand).not.toContainText(".0000");
});
