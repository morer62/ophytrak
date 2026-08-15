const { test, expect } = require('@playwright/test');
const baseURL = process.env.E2E_BASE_URL || 'http://localhost/ophyra';

test('Level 5 sees distinct service-order and document portal views', async ({ page }) => {
  await page.goto(`${baseURL}/login`);
  await page.locator('[name="email"]').fill('qa.client.20260716a@example.test');
  await page.locator('[name="password"]').fill('OphyraQA!2026');
  await page.locator('form').filter({ has: page.locator('[name="email"]') }).locator('button[type="submit"]').click();
  await page.goto(`${baseURL}/panel/planner-hub/orders/orders?view=documents`);
  await expect(page.locator('h1')).toContainText(/Contracts|Contratos|Contrats|Contratos/i);
  await expect(page.locator('.alert-info')).toBeVisible();
  await expect(page.locator('nav.nav-pills .active')).toContainText(/Contracts|Contratos|Contrats/i);
  await expect(page.locator('thead')).not.toContainText(/Start|Inicio|Début/i);
  await page.goto(`${baseURL}/panel/planner-hub/orders/orders?view=orders`);
  await expect(page.locator('nav.nav-pills .active')).toContainText(/Service|Servicio|Serviço/i);
});
