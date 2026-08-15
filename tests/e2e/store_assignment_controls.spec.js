const { test, expect } = require('@playwright/test');
const baseURL = process.env.E2E_BASE_URL || 'http://localhost/ophytrak';

test('Level 2 can open responsibility assignment and assign both stages to self', async ({ page }, testInfo) => {
  await page.goto(`${baseURL}/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('[name="email"]').fill('qa.owner.20260716a@example.test');
  await page.locator('[name="password"]').fill('OphyraQA!2026');
  await Promise.all([
    page.waitForURL(url => !url.pathname.endsWith('/login'), { timeout: 20000 }),
    page.locator('form').filter({ has: page.locator('[name="email"]') }).locator('button[type="submit"]').click()
  ]);
  await page.goto(`${baseURL}/panel/planner-hub/store/orders/home?week_start=2026-07-13&week_end=2026-07-19`, { waitUntil: 'domcontentloaded' });
  const operations = page.locator('[data-bs-target="#assignDeliveryModal"]').first();
  await expect(operations).toBeVisible();
  await operations.click();
  await expect(page.locator('#assignDeliveryModal')).toBeVisible();
  await expect(page.locator('#assignKitchenUserSelect option')).toContainText(['Assign to me']);
  await page.locator('#assignBothToMe').click();
  const me = await page.locator('#assignKitchenUserSelect').inputValue();
  await expect(page.locator('#assignDeliveryUserSelect')).toHaveValue(me);
  await page.screenshot({ path: testInfo.outputPath('assignment-controls.png'), fullPage: true });
});
