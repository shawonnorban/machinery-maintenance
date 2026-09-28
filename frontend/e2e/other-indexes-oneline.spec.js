import { test, expect } from "@playwright/test";

async function login(page) {
  await page.goto("/login");
  await page.getByLabel("Email").fill("owner@delta.test");
  await page.getByLabel("Password").fill("password123");
  await page.getByRole("button", { name: "Sign in" }).click();
  await page.waitForURL((url) => url.pathname !== "/login", { timeout: 20_000 });
}

const PAGES = [
  { url: "/settings/locations", search: "Search name or code…" },
  { url: "/settings/factories", search: "Search name or code…" },
  { url: "/technicians", search: "Search name or employee id…" },
  { url: "/inventory/parts", search: "Search part number, name, brand…" },
  { url: "/vendors", search: "Search name or code…" },
  { url: "/settings/users", search: "Search name or email…" },
  { url: "/inventory/stock", search: "Search part number or name…" },
];

for (const { url, search } of PAGES) {
  test(`${url} has search and filter on one row`, async ({ page }) => {
    await login(page);
    await page.goto(url);
    await page.waitForSelector("table, main", { timeout: 45_000 });

    const searchInput = page.getByPlaceholder(search);
    await expect(searchInput).toBeVisible({ timeout: 30_000 });

    // The Select trigger sitting beside the search input in the same card row.
    const combobox = page.getByRole("combobox").first();
    await expect(combobox).toBeVisible();

    const searchBox = await searchInput.boundingBox();
    const comboboxBox = await combobox.boundingBox();
    expect(Math.abs(searchBox.y - comboboxBox.y)).toBeLessThan(10);
  });
}
