const { test, expect } = require('@playwright/test');

const baseURL = process.env.E2E_BASE_URL || 'http://localhost/ophyra';
const password = 'OphyraQA!2026';

async function login(page, email) {
  await page.goto(`${baseURL}/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('[name="email"]').fill(email);
  await page.locator('[name="password"]').fill(password);
  await page.locator('form').filter({ has: page.locator('[name="email"]') }).locator('button[type="submit"]').click();
  await expect(page).toHaveURL(/panel/);
}

test('visible delivery/client chat and client tracking', async ({ browser }, testInfo) => {
  test.setTimeout(120_000);
  const deliveryContext = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const delivery = await deliveryContext.newPage();
  await login(delivery, 'qa.delivery.20260716a@example.test');
  await delivery.goto(`${baseURL}/panel/planner-hub/team/chat?to=1452`, { waitUntil: 'domcontentloaded' });
  await expect(delivery.locator('[data-chat-form]')).toBeVisible();
  await delivery.locator('[name="message"]').fill('QA driver: your package is on the visible test route.');
  await delivery.locator('[data-chat-form] button[type="submit"]').click();
  await expect(delivery.locator('#chatMessages')).toContainText('QA driver: your package is on the visible test route.');
  await delivery.screenshot({ path: testInfo.outputPath('01-driver-message.png'), fullPage: true });

  const clientContext = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const client = await clientContext.newPage();
  await login(client, 'qa.client.20260716a@example.test');
  await client.goto(`${baseURL}/panel/store/orders/home`, { waitUntil: 'domcontentloaded' });
  await expect(client.locator('body')).toContainText('#4');
  await expect(client.locator('body')).toContainText('Ophyra QA Logistics 20260716a');
  await client.screenshot({ path: testInfo.outputPath('02-client-orders-tracking.png'), fullPage: true });

  await client.goto(`${baseURL}/panel/chat?to=1451`, { waitUntil: 'domcontentloaded' });
  await expect(client.locator('body')).toContainText('QA driver: your package is on the visible test route.');
  await client.locator('[name="message"]').fill('QA client: I can see the tracking and delivery conversation.');
  await client.locator('[data-chat-form] button[type="submit"]').click();
  await expect(client.locator('#chatMessages')).toContainText('QA client: I can see the tracking and delivery conversation.');
  await client.screenshot({ path: testInfo.outputPath('03-client-reply.png'), fullPage: true });

  await client.goto(`${baseURL}/store/order-access?token=473747ca7d5a9c2465ad7885d7b562635aeec4edeeafb680`, { waitUntil: 'domcontentloaded' });
  await expect(client.locator('body')).toContainText(/Order #4|#4/);
  await client.screenshot({ path: testInfo.outputPath('04-public-order-access.png'), fullPage: true });

  await clientContext.close();
  await deliveryContext.close();
});
