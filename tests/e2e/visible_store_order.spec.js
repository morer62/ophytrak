const { test, expect } = require('@playwright/test');

const baseURL = process.env.E2E_BASE_URL || 'http://localhost/ophytrak';
const runId = process.env.E2E_RUN_ID || '20260716a';
const password = process.env.E2E_OWNER_PASSWORD || 'OphyraQA!2026';
const ownerEmail = process.env.E2E_OWNER_EMAIL || 'qa.owner.20260716a@example.test';
const clientEmail = process.env.E2E_CLIENT_EMAIL || `qa.client.${runId}@example.test`;
const deliveryEmail = process.env.E2E_DELIVERY_EMAIL || `qa.delivery.${runId}@example.test`;

async function login(page, email) {
  await page.goto(`${baseURL}/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('[name="email"]').fill(email);
  await page.locator('[name="password"]').fill(password);
  await page.locator('form').filter({ has: page.locator('[name="email"]') }).locator('button[type="submit"]').click();
  await expect(page).toHaveURL(/panel/);
}

test('visible order creation, assignment and Level 4 task visibility', async ({ browser }, testInfo) => {
  test.setTimeout(240_000);
  const ownerContext = await browser.newContext({ viewport: { width: 1440, height: 960 }, recordVideo: { dir: testInfo.outputPath('owner-video') } });
  const owner = await ownerContext.newPage();
  await login(owner, ownerEmail);

  await owner.goto(`${baseURL}/panel/planner-hub/store/orders/manual`, { waitUntil: 'domcontentloaded' });
  const clientOption = owner.locator('#storeClientSelect option').filter({ hasText: clientEmail });
  await expect(clientOption).toHaveCount(1);
  await owner.locator('#storeClientSelect').selectOption(await clientOption.getAttribute('value'));
  await owner.locator('#wizardNext').click();
  const productOption = owner.locator('.store-product option').filter({ hasText: `QA Delivery Package ${runId}` });
  await expect(productOption).toHaveCount(1);
  await owner.locator('.store-product').selectOption(await productOption.getAttribute('value'));
  await owner.locator('#wizardNext').click();
  await owner.locator('[name="shipping_address_1"]').fill('200 Client QA Street, Miami, FL 33101, USA');
  await owner.locator('#manualStoreOrderForm').evaluate(form => {
    const values = { shipping_place_id: 'qa-browser-place', shipping_city: 'Miami', shipping_state: 'FL', shipping_zip: '33101', shipping_country: 'USA' };
    Object.entries(values).forEach(([name, value]) => { const field=form.querySelector(`[name="${name}"]`); field.disabled=false; field.value=value; });
    form.dispatchEvent(new CustomEvent('ophyra:address-selected', { bubbles: true }));
  });
  await owner.locator('[name="payment_mode"]').selectOption('mark_paid');
  await owner.locator('[name="payment_reference"]').fill('QA Square sandbox payment pending');
  await owner.screenshot({ path: testInfo.outputPath('01-manual-order-ready.png'), fullPage: true });
  await owner.locator('#wizardNext').click();
  await owner.locator('#manualStoreOrderForm button[type="submit"]').click();
  await owner.waitForLoadState('domcontentloaded');
  await expect(owner).toHaveURL(/store\/orders\/home/);
  await owner.screenshot({ path: testInfo.outputPath('02-order-created.png'), fullPage: true });

  const assignButton = owner.locator('[data-bs-target="#assignDeliveryModal"]').first();
  await expect(assignButton).toBeVisible();
  const orderId = await assignButton.getAttribute('data-order-id');
  await assignButton.click();
  const deliveryOption = owner.locator('#assignDeliveryUserSelect option').filter({ hasText: deliveryEmail });
  await expect(deliveryOption).toHaveCount(1);
  const deliveryId = await deliveryOption.getAttribute('value');
  await owner.locator('#assignKitchenUserSelect').selectOption(deliveryId);
  await owner.locator('#assignDeliveryUserSelect').selectOption(deliveryId);
  await owner.locator('#assignAllowClose').check();
  await owner.locator('#assignAllowChat').check();
  await owner.screenshot({ path: testInfo.outputPath('03-assignment-ready.png'), fullPage: true });
  await owner.locator('#assignDeliveryModal form button[type="submit"]').click();
  await owner.waitForLoadState('domcontentloaded');
  await owner.screenshot({ path: testInfo.outputPath('04-assignment-saved.png'), fullPage: true });

  const deliveryContext = await browser.newContext({ viewport: { width: 390, height: 844 }, recordVideo: { dir: testInfo.outputPath('delivery-video') } });
  const delivery = await deliveryContext.newPage();
  await login(delivery, deliveryEmail);
  await delivery.goto(`${baseURL}/panel/planner-hub/team/my-work`, { waitUntil: 'domcontentloaded' });
  await expect(delivery.locator('body')).toContainText(`#${orderId}`);
  await delivery.screenshot({ path: testInfo.outputPath('05-level4-my-work.png'), fullPage: true });
  await delivery.goto(`${baseURL}/panel/planner-hub/team/driver-mode`, { waitUntil: 'domcontentloaded' });
  await expect(delivery.locator('body')).toContainText(`#${orderId}`);
  await expect(delivery.locator('#openPackageScanner')).toBeVisible();
  await delivery.screenshot({ path: testInfo.outputPath('06-driver-mode.png'), fullPage: true });

  console.log(`QA_ORDER_ID=${orderId}`);
  await deliveryContext.close();
  await ownerContext.close();
});
