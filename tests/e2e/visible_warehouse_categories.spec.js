const { test, expect } = require('@playwright/test');
const baseURL = process.env.E2E_BASE_URL || 'http://localhost/ophytrak';

test('Level 2 manages owner-scoped Warehouse container categories', async ({ page }) => {
  await page.goto(`${baseURL}/login`);
  await page.locator('[name="email"]').fill('qa.owner.20260716a@example.test');
  await page.locator('[name="password"]').fill('OphyraQA!2026');
  await page.locator('form').filter({ has: page.locator('[name="email"]') }).locator('button[type="submit"]').click();
  await page.goto(`${baseURL}/panel/planner-hub/management/storage/containers/categories`);
  await expect(page.locator('h1')).toContainText(/Container Categories|Categorías de contenedores/i);
  const name = 'QA Cold Storage 20260716';
  if (!(await page.locator('body').getByText(name, { exact: true }).count())) {
    await page.locator('[name="name"]').fill(name);
    await page.locator('form').filter({ has: page.locator('[name="name"]') }).locator('button').click();
    await page.goto(`${baseURL}/panel/planner-hub/management/storage/containers/categories`);
  }
  await expect(page.locator('body')).toContainText(name);
  await page.goto(`${baseURL}/panel/planner-hub/management/storage/containers/create`);
  await expect(page.locator('[name="id_category"] option')).toContainText([/Uncategorized|Sin categoría/i, name]);
});
