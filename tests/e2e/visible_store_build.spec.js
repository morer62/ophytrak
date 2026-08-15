const { test, expect } = require('@playwright/test');

const baseURL = process.env.E2E_BASE_URL || 'http://localhost/ophytrak';
const runId = process.env.E2E_RUN_ID || '20260716a';
const ownerEmail = process.env.E2E_OWNER_EMAIL || 'qa.owner.20260716a@example.test';
const password = process.env.E2E_OWNER_PASSWORD || 'OphyraQA!2026';
const deliveryEmail = process.env.E2E_DELIVERY_EMAIL || `qa.delivery.${runId}@example.test`;
const clientEmail = process.env.E2E_CLIENT_EMAIL || `qa.client.${runId}@example.test`;

async function login(page) {
  await page.goto(`${baseURL}/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('[name="email"]').fill(ownerEmail);
  await page.locator('[name="password"]').fill(password);
  await page.locator('form').filter({ has: page.locator('[name="email"]') }).locator('button[type="submit"]').click();
  await expect(page).toHaveURL(/panel/);
}

async function createUser(page, type, email, firstName) {
  await page.goto(`${baseURL}/panel/planner-hub/management/users/create`, { waitUntil: 'domcontentloaded' });
  await page.getByRole('button', { name: 'Start Creating User' }).click();
  await page.locator(`.user-type-card[data-type="${type}"]`).click();
  await page.locator('#emailInput').fill(email);
  await page.locator('#validateEmailBtn').click();
  await expect(page.locator('#mainFormCard')).toBeVisible({ timeout: 15_000 });
  await page.locator('#create-user-form [name="name"]').fill(firstName);
  await page.locator('#create-user-form [name="lastname"]').fill('Visible QA');
  await page.locator('#create-user-form [name="phone"]').fill('+12025550199');
  await page.locator('#create-user-form [name="password"]').fill(password);
  await page.locator('#create-user-form [name="password_confirm"]').fill(password);
  if (type === '4') {
    await page.locator('[name="hourly_rate"]').fill('22');
    const roleOptions = await page.locator('#role-select option').evaluateAll(options => options.map(option => option.value).filter(Boolean));
    expect(roleOptions.length).toBeGreaterThan(0);
    await page.locator('#role-select').selectOption(roleOptions[0]);
  }
  await page.locator('#create-user-form button[type="submit"]').click();
  await page.waitForLoadState('domcontentloaded');
}

test('visible creation of delivery, client, category and product', async ({ page }, testInfo) => {
  test.setTimeout(240_000);
  await login(page);

  await createUser(page, '4', deliveryEmail, 'Delivery');
  await page.screenshot({ path: testInfo.outputPath('01-delivery-created.png'), fullPage: true });
  await createUser(page, '5', clientEmail, 'Client');
  await page.screenshot({ path: testInfo.outputPath('02-client-created.png'), fullPage: true });

  await page.goto(`${baseURL}/panel/planner-hub/store/categories/create`, { waitUntil: 'domcontentloaded' });
  await page.locator('#categoryForm [name="name"]').fill('QA Delivery Products');
  await page.locator('#categoryForm [name="slug"]').fill(`qa-delivery-products-${runId}`);
  await page.locator('#categoryForm [name="description"]').fill('Products used by the visible Store and Logistics QA run.');
  await page.locator('#categoryForm button[type="submit"]').click();
  await page.waitForLoadState('domcontentloaded');
  await page.screenshot({ path: testInfo.outputPath('03-category-created.png'), fullPage: true });

  await page.goto(`${baseURL}/panel/planner-hub/store/products/create`, { waitUntil: 'domcontentloaded' });
  await page.locator('[name="name"]').fill(`QA Delivery Package ${runId}`);
  await page.locator('[name="sku"]').fill(`QA-DELIVERY-${runId}`.toUpperCase());
  await page.locator('[name="slug"]').fill(`qa-delivery-package-${runId}`);
  await page.locator('[name="short_description"]').fill('Visible test product for packaging, QR and delivery.');
  await page.locator('[name="description"]').fill('QA product used to validate Store checkout and the complete logistics workflow.');
  await page.locator('[name="price"]').fill('12.50');
  await page.locator('[name="stock_quantity"]').fill('25');
  const category = page.locator('[name="category_ids[]"]').last();
  if (await category.count()) await category.check();
  const onePixelPng = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', 'base64');
  await page.locator('[name="main_image"]').setInputFiles({ name: 'qa-product.png', mimeType: 'image/png', buffer: onePixelPng });
  await page.screenshot({ path: testInfo.outputPath('04-product-ready.png'), fullPage: true });
  await page.locator('form button[type="submit"]').first().click();
  await page.waitForLoadState('domcontentloaded');
  await page.screenshot({ path: testInfo.outputPath('05-product-created.png'), fullPage: true });

  await expect(page.locator('body')).toContainText(`QA Delivery Package ${runId}`);
});
