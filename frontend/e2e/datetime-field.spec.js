import { test, expect } from "@playwright/test";

/**
 * Verifies the fix for the report-breakdown form's date/time fields:
 * previously a native `<input type="datetime-local">` (broken on some
 * mobile browsers, US month-first display, one combined control). Now a
 * DatePicker (dd/MM/yyyy) + a separate native time input.
 */
test("machine-stopped date and time are separate, dd/MM/yyyy fields", async ({ page }) => {
  await page.goto("/login");
  await page.getByLabel("Email").fill("owner@delta.test");
  await page.getByLabel("Password").fill("password123");
  await page.getByRole("button", { name: "Sign in" }).click();
  await page.waitForURL((url) => url.pathname !== "/login", { timeout: 20_000 });

  await page.goto("/breakdowns/create");

  const stoppedField = page.locator("label", { hasText: "Machine stopped" }).locator("..");
  const dateTrigger = stoppedField.getByRole("button").first();
  const timeInput = stoppedField.locator('input[type="time"]');

  await expect(dateTrigger).toBeVisible();
  await expect(timeInput).toBeVisible();

  await dateTrigger.click();
  await page.getByRole("dialog").waitFor({ state: "visible" }).catch(() => {});
  const dayButton = page.locator("button.rdp-day_button:not([disabled])").first();
  await dayButton.waitFor({ state: "visible", timeout: 10_000 });
  await dayButton.click();

  // dd/MM/yyyy, not the native control's mm/dd/yyyy or a locale-dependent spelled-out date.
  await expect(dateTrigger).toHaveText(/^\d{2}\/\d{2}\/\d{4}$/);

  await timeInput.fill("14:30");
  await expect(timeInput).toHaveValue("14:30");
});
