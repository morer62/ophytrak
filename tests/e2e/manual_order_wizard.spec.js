const { test, expect } = require('@playwright/test');

const baseURL = process.env.E2E_BASE_URL || 'http://localhost/ophytrak';

test('manual Store order uses guarded wizard and calculates percentage fee', async ({ page }, testInfo) => {
  await page.goto(`${baseURL}/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('[name="email"]').fill('qa.owner.20260716a@example.test');
  await page.locator('[name="password"]').fill('OphyraQA!2026');
  await page.locator('form').filter({ has: page.locator('[name="email"]') }).locator('button[type="submit"]').click();
  await page.goto(`${baseURL}/panel/planner-hub/store/orders/manual`, { waitUntil: 'domcontentloaded' });

  await expect(page.locator('.wizard-step')).toHaveCount(4);
  const client = page.locator('#storeClientSelect');
  if (!(await client.inputValue())) await client.selectOption({ index: 1 });
  await page.locator('#wizardNext').click();
  await expect(page.locator('.wizard-panel[data-step="2"]')).toBeVisible();

  await page.locator('.store-product').first().selectOption({ index: 1 });
  await page.locator('#feeType').selectOption('percentage');
  await page.locator('#feeValue').fill('10');
  await expect(page.locator('#manualOrderTotal')).not.toHaveText('$0.00');
  await page.screenshot({ path: testInfo.outputPath('manual-order-step-2-fee.png'), fullPage: true });

  await page.locator('#wizardNext').click();
  await expect(page.locator('.wizard-panel[data-step="3"]')).toBeVisible();
  await expect(page.locator('#paymentMode option[value="manual_proof"]')).toBeEnabled();
  await expect(page.locator('#paymentMode option[value="mark_paid"]')).toBeEnabled();
});
