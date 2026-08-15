const { test, expect } = require('@playwright/test');

const baseURL = process.env.E2E_BASE_URL || 'http://localhost/ophytrak';
const sellerEmail = process.env.E2E_OWNER_EMAIL || 'qa.seller.20260806cert1@example.test';
const password = process.env.E2E_OWNER_PASSWORD || 'OphyraQA!2026';

test.use({ viewport: { width: 1440, height: 960 }, trace: 'on', video: 'on' });

test('public product and signup expose only OPHYTRACK logistics', async ({ page }) => {
  let response = await page.goto(`${baseURL}/`, { waitUntil: 'domcontentloaded' });
  expect(response.status()).toBe(200);
  await expect(page).toHaveTitle(/OPHYTRACK/i);
  await expect(page.locator('body')).toContainText('OPHYTRACK');
  await expect(page.locator('video')).toHaveCount(1);
  await expect(page.locator('body')).not.toContainText('Book services');

  response = await page.goto(`${baseURL}/signup`, { waitUntil: 'domcontentloaded' });
  expect(response.status()).toBe(200);
  await expect(page.locator('body')).toContainText('OPHYTRACK');
  await expect(page.locator('[name="business_nature"] option[value="service_business"]')).toHaveCount(0);
  await expect(page.locator('[name="business_nature"] option[value="carrier_logistics"]')).toHaveCount(1);
  await expect(page.locator('[name="business_operation_type"] option[value="contracts_services"]')).toHaveCount(0);
});

test('seller sees logistics shell and legacy service routes are closed', async ({ page }) => {
  await page.goto(`${baseURL}/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('[name="email"]').fill(sellerEmail);
  await page.locator('[name="password"]').fill(password);
  await page.locator('form').filter({ has: page.locator('[name="email"]') }).locator('button[type="submit"]').click();
  await expect(page).toHaveURL(/panel/);

  await page.goto(`${baseURL}/panel/planner-hub`, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('body')).toContainText('OPHYTRACK');
  await expect(page.locator('#sidebar')).not.toContainText('Services');
  await expect(page.locator('#sidebar')).not.toContainText('Tickets');

  await page.goto(`${baseURL}/panel/planner-hub/store/orders/home`, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('body')).toContainText(/Store|Tienda|Loja/i);

  await page.goto(`${baseURL}/panel/planner-hub/management/orders`, { waitUntil: 'domcontentloaded' });
  await expect(page).not.toHaveURL(/management\/orders(?:$|\?)/);
});
