const { test, expect } = require('@playwright/test');

const baseURL = process.env.E2E_BASE_URL || 'http://localhost/ophytrak';
const email = process.env.E2E_OWNER_EMAIL || 'qa.owner.20260806lifecycle2@example.test';
const password = process.env.E2E_OWNER_PASSWORD || 'OphyraQA!2026';
const packageCode = process.env.E2E_PACKAGE_CODE || 'OPH-1637-2014-01';

test('owner locates a package and sees its operational history', async ({ page }, testInfo) => {
  await page.goto(`${baseURL}/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('[name="email"]').fill(email);
  await page.locator('[name="password"]').fill(password);
  await page.locator('form').filter({ has: page.locator('[name="email"]') }).locator('button[type="submit"]').click();
  await expect(page).toHaveURL(/panel/);
  await page.goto(`${baseURL}/panel/planner-hub/store/orders/home?package=${encodeURIComponent(packageCode)}`, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('body')).toContainText(packageCode);
  await expect(page.locator('.package-result')).toBeVisible();
  await expect(page.locator('.package-timeline')).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('package-finder.png'), fullPage: true });
});
