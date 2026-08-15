const { test, expect } = require('@playwright/test');

const baseURL = process.env.E2E_BASE_URL || 'http://localhost/ophytrak';
const email = process.env.E2E_DELIVERY_EMAIL || 'qa.delivery.20260806lifecycle2@example.test';
const password = process.env.E2E_OWNER_PASSWORD || 'OphyraQA!2026';

test('delivery user sees mobile package list, scanner and structured proof flow', async ({ page }, testInfo) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(`${baseURL}/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('[name="email"]').fill(email);
  await page.locator('[name="password"]').fill(password);
  await page.locator('form').filter({ has: page.locator('[name="email"]') }).locator('button[type="submit"]').click();
  await page.goto(`${baseURL}/panel/planner-hub/team/driver-mode`, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('[data-driver-tab="pending"]')).toBeVisible();
  await expect(page.locator('[data-driver-tab="incidents"]')).toBeVisible();
  await expect(page.locator('[data-driver-tab="completed"]')).toBeVisible();
  await expect(page.locator('.driver-fab')).toBeVisible();
  const route = page.locator('[data-order-id="1012"]');
  await expect(route).toContainText('OPH-1637-1012-01');
  await route.locator('.driver-detail-btn').click();
  const modal = page.locator('#driverTaskModal19');
  await expect(modal).toBeVisible();
  await modal.locator('[data-driver-flow="proof"]').click();
  await expect(modal.locator('[name="receiver_type"]')).toBeVisible();
  await expect(modal.locator('[name="receiver_name"]')).toBeVisible();
  await expect(modal.locator('[name="document_number"]')).toBeVisible();
  await expect(modal.locator('[name="delivery_proof"]')).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('mobile-proof-flow.png'), fullPage: true });
});
