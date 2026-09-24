const { test, expect } = require('@playwright/test');
const baseURL = process.env.E2E_BASE_URL || 'http://localhost/ophytrak';

test('Level 2 chooses an internal team or an associated carrier for delivery', async ({ page }, testInfo) => {
  test.setTimeout(90000);
  const runId = Date.now().toString();
  const email = `qa.assignment.${runId}@example.test`;
  const password = 'OphyraQA!2026';
  await page.goto(`${baseURL}/signup`);
  await page.locator('[name="company_name"]').fill(`QA Assignment ${runId}`);
  await page.locator('[data-signup-next]').click();
  await page.locator('[name="business_nature"]').selectOption('carrier_logistics');
  await page.locator('[name="business_operation_type"]').selectOption('store_delivery_tracking');
  await page.locator('[data-signup-next]').click();
  await page.locator('[name="name"]').fill('Assignment');
  await page.locator('[name="lastname"]').fill('Owner');
  await page.locator('[name="email"]').fill(email);
  await page.locator('[name="phone_local_number"]').fill('2025550188');
  await page.locator('[data-signup-next]').click();
  await page.locator('[name="password"]').fill(password);
  await page.locator('[name="passwordConfirmation"]').fill(password);
  await page.locator('[name="terms"]').check();
  await page.locator('[data-signup-submit]').click();
  await expect(page.locator('[data-signup-success-modal]')).toHaveClass(/is-open/, { timeout: 30000 });
  await page.goto(`${baseURL}/login`);
  await page.locator('[name="email"]').fill(email);
  await page.locator('[name="password"]').fill(password);
  await page.locator('form').filter({ has: page.locator('[name="email"]') }).locator('button[type="submit"]').click();
  await expect(page).not.toHaveURL(/\/login$/);
  await page.goto(`${baseURL}/panel/planner-hub/institution-profile`);
  await page.locator('[name="business_nature"]').selectOption('logistics_delivery');
  await page.locator('[name="address_line1"]').fill('100 Logistics Avenue');
  await page.locator('[name="city"]').fill('São Paulo');
  await page.locator('[name="state"]').fill('SP');
  await page.locator('[name="zip"]').fill('01001-000');
  await page.locator('[name="short_description"]').fill('Transportadora verificada para testes de atribuição.');
  await page.getByRole('button', { name: /Save Profile|Salvar perfil|Guardar perfil/i }).click();
  await page.waitForLoadState('domcontentloaded');

  await page.goto(`${baseURL}/panel/planner-hub/store/carriers?locale=es`);
  await expect(page.locator('h1')).toHaveText('Compañías de envío');
  await expect(page.locator('body')).not.toContainText('carrier_relations.');
  await expect(page.locator('a.sidebar-link').filter({ hasText: 'Compañías de envío' })).toBeVisible();
  const available = page.locator('select[name="carrier_owner_id"]');
  if (await available.locator('option').count() > 1) {
    await available.selectOption({ index: 1 });
    await page.getByRole('button', { name: 'Agregar a mi red' }).click();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('form input[name="action"][value="remove"]')).toHaveCount(1);
  }
  await page.screenshot({ path: testInfo.outputPath('shipping-companies-es.png'), fullPage: true });

  await page.goto(`${baseURL}/panel/planner-hub/store/orders/home?locale=es`);
  await expect(page.locator('#assignDeliveryModal')).toHaveCount(1);
  await page.evaluate(() => bootstrap.Modal.getOrCreateInstance(document.getElementById('assignDeliveryModal')).show());
  await expect(page.locator('#assignDeliveryModal')).toBeVisible();
  await expect(page.locator('#assignKitchenUserSelect option')).toContainText(['La preparación será asignada a mí']);
  await expect(page.locator('#deliveryTypeTeam')).toBeChecked();
  await page.locator('#deliveryTypeCarrier').check();
  await expect(page.locator('#carrierDeliveryFields')).toBeVisible();
  await expect(page.locator('#assignCarrierOwnerSelect option')).not.toHaveCount(1);
  await expect(page.locator('body')).not.toContainText('store_orders.');
  await page.screenshot({ path: testInfo.outputPath('assignment-controls-es.png'), fullPage: true });
});
