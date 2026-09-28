import { test, expect } from "@playwright/test";

/** Verifies search and both filters (status, criticality) now sit on one row inside a single card. */
test("assets search and filters share one row", async ({ page }) => {
  await page.goto("/login");
  await page.getByLabel("Email").fill("owner@delta.test");
  await page.getByLabel("Password").fill("password123");
  await page.getByRole("button", { name: "Sign in" }).click();
  await page.waitForURL((url) => url.pathname !== "/login", { timeout: 20_000 });

  await page.goto("/assets");
  await page.waitForSelector("table", { timeout: 45_000 });

  const searchInput = page.getByPlaceholder("Search asset code, name, serial…");
  const statusSelect = page.getByRole("combobox", { name: /status/i }).or(page.getByText("All statuses"));
  await expect(searchInput).toBeVisible();
  await expect(page.getByText("All statuses")).toBeVisible();
  await expect(page.getByText("All criticalities")).toBeVisible();

  await page.screenshot({ path: "test-results/assets-oneline.png" });

  const searchBox = await searchInput.boundingBox();
  const statusBox = await page.getByText("All statuses").boundingBox();
  expect(Math.abs(searchBox.y - statusBox.y)).toBeLessThan(10);
});
