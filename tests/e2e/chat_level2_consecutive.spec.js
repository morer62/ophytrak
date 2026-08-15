const { test, expect } = require('@playwright/test');
const baseURL = process.env.E2E_BASE_URL || 'http://localhost/ophytrak';

test('Level 2 consecutive messages remain after live refresh and reload', async ({ page }) => {
  test.setTimeout(60_000);
  await page.goto(`${baseURL}/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('[name="email"]').fill('qa.owner.20260716a@example.test');
  await page.locator('[name="password"]').fill('OphyraQA!2026');
  await page.locator('form').filter({ has: page.locator('[name="email"]') }).locator('button[type="submit"]').click();
  const stamp = Date.now();
  await page.goto(`${baseURL}/panel/planner-hub/team/chat?to=1452`, { waitUntil: 'domcontentloaded' });
  const input = page.locator('[data-chat-form] [name="message"]');
  const send = page.locator('[data-chat-form] button[type="submit"]');
  const first = `Level2 first ${stamp}`;
  const second = `Level2 second ${stamp}`;
  await input.fill(first); await send.click();
  await expect(page.locator('#chatMessages')).toContainText(first);
  await input.fill(second); await send.click();
  await expect(page.locator('#chatMessages')).toContainText(second);
  await page.waitForTimeout(3500);
  await expect(page.locator('#chatMessages')).toContainText(first);
  await expect(page.locator('#chatMessages')).toContainText(second);
  await page.reload({ waitUntil: 'domcontentloaded' });
  await expect(page.locator('#chatMessages')).toContainText(first);
  await expect(page.locator('#chatMessages')).toContainText(second);
});
