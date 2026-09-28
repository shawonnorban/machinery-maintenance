import { test, expect } from "@playwright/test";

/**
 * Verifies the fix for the Location select on the Asset creation form:
 * previously the popup stretched to nearly full viewport height (Base UI's
 * `alignItemWithTrigger` default) and had no way to filter a 29-entry list.
 */
test("the location select is height-bounded and searchable", async ({ page }) => {
  await page.goto("/login");
  await page.getByLabel("Email").fill("owner@delta.test");
  await page.getByLabel("Password").fill("password123");
  await page.getByRole("button", { name: "Sign in" }).click();
  await page.waitForURL((url) => url.pathname !== "/login", { timeout: 20_000 });

  await page.goto("/assets/create");

  // Pick a factory first so the location list is populated.
  await page.getByRole("combobox", { name: "Factory" }).click();
  await page.getByRole("option").first().click();

  await page.getByRole("combobox", { name: "Location" }).click();

  const popup = page.getByRole("listbox");
  await expect(popup).toBeVisible();

  const box = await popup.boundingBox();
  const viewport = page.viewportSize();
  expect(box.height).toBeLessThan(viewport.height * 0.6);

  const search = page.getByPlaceholder("Search…");
  await expect(search).toBeVisible();
  await search.fill("Line 3");

  const options = page.getByRole("option");
  await expect(options).toHaveCount(1);
  await expect(options.first()).toContainText("Line 3");

  await options.first().click();
  await expect(page.getByRole("combobox", { name: "Location" })).toContainText("Line 3");
});
