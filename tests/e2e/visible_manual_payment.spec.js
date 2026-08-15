const { test, expect } = require('@playwright/test');

const baseURL = process.env.E2E_BASE_URL || 'http://localhost/ophytrak';

test('Level 2 records an externally received payment without rewinding logistics', async ({ page }, testInfo) => {
  test.setTimeout(120_000);
  await page.goto(`${baseURL}/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('[name="email"]').fill('qa.owner.20260716a@example.test');
  await page.locator('[name="password"]').fill('OphyraQA!2026');
  await page.locator('form').filter({ has: page.locator('[name="email"]') }).locator('button[type="submit"]').click();
  await expect(page).toHaveURL(/panel/);

  await page.goto(`${baseURL}/panel/planner-hub/store/orders/home`, { waitUntil: 'domcontentloaded' });
  const edit = page.locator('[data-bs-target="#editPaymentModal"][data-order-id="4"]');
  await expect(edit).toBeVisible();
  await edit.click();
  await page.locator('#editPaymentSelect').selectOption('PAID');
  await expect(page.locator('#manualPaymentDetails')).toBeVisible();
  await page.locator('[name="manual_payment_method"]').selectOption('other');
  await page.locator('#manualPaymentReference').fill('QA-OFFLINE-20260716');
  await page.locator('[name="manual_payment_notes"]').fill('Pago externo recibido y validado por Level 2.');
  await page.screenshot({ path: testInfo.outputPath('01-manual-payment-ready.png'), fullPage: true });
  await page.locator('#editPaymentModal button[type="submit"]').click();
  await page.waitForLoadState('domcontentloaded');
  const row = page.locator('[data-bs-target="#editPaymentModal"][data-order-id="4"]').locator('xpath=ancestor::tr');
  await expect(row).toContainText(/Paid|Pagado/i);
  await page.screenshot({ path: testInfo.outputPath('02-manual-payment-recorded.png'), fullPage: true });
});
