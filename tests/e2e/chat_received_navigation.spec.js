const { test, expect } = require('@playwright/test');

const baseURL = process.env.E2E_BASE_URL || 'http://localhost/ophytrak';
const password = 'OphyraQA!2026';

async function login(page, email) {
  await page.goto(`${baseURL}/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('[name="email"]').fill(email);
  await page.locator('[name="password"]').fill(password);
  await page.locator('form').filter({ has: page.locator('[name="email"]') }).locator('button[type="submit"]').click();
  await expect(page).toHaveURL(/panel/);
}

async function send(page, url, text) {
  await page.goto(baseURL + url, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('[data-chat-form]')).toBeVisible();
  await page.locator('[name="message"]').fill(text);
  await page.locator('[data-chat-form] button[type="submit"]').click();
  await expect(page.locator('#chatMessages')).toContainText(text);
}

test('received conversations open from card and header for client, Level 2 and Level 4', async ({ browser }, testInfo) => {
  test.setTimeout(120_000);
  const stamp = Date.now();
  const ownerContext = await browser.newContext();
  const owner = await ownerContext.newPage();
  await login(owner, 'qa.owner.20260716a@example.test');

  const clientText = `QA received client ${stamp}`;
  await send(owner, '/panel/planner-hub/team/chat?to=1452', clientText);

  const clientContext = await browser.newContext();
  const client = await clientContext.newPage();
  await login(client, 'qa.client.20260716a@example.test');
  await client.goto(`${baseURL}/panel/chat`, { waitUntil: 'domcontentloaded' });
  const clientCard = client.locator('.chat-card.thread').filter({ hasText: clientText }).first();
  await expect(clientCard).toBeVisible();
  await clientCard.click();
  await expect(client.locator('[data-chat-form]')).toBeVisible();
  await expect(client.locator('#chatMessages')).toContainText(clientText);
  await client.screenshot({ path: testInfo.outputPath('client-received-open.png'), fullPage: true });

  const deliveryText = `QA received Level 4 ${stamp}`;
  await send(owner, '/panel/planner-hub/team/chat?to=1451', deliveryText);
  const deliveryContext = await browser.newContext();
  const delivery = await deliveryContext.newPage();
  await login(delivery, 'qa.delivery.20260716a@example.test');
  await delivery.goto(`${baseURL}/panel`, { waitUntil: 'domcontentloaded' });
  await delivery.locator('#chatUnreadDropdown').click();
  const deliveryUnread = delivery.locator('[data-chat-unread-list] a').filter({ hasText: deliveryText }).first();
  await expect(deliveryUnread).toBeVisible();
  await deliveryUnread.click();
  await expect(delivery.locator('[data-chat-form]')).toBeVisible();
  await expect(delivery.locator('#chatMessages')).toContainText(deliveryText);
  await delivery.screenshot({ path: testInfo.outputPath('level4-header-open.png'), fullPage: true });

  const ownerReply = `QA received Level 2 ${stamp}`;
  // Leave the live chat first; its polling intentionally marks visible messages read.
  await owner.goto(`${baseURL}/panel`, { waitUntil: 'domcontentloaded' });
  await send(delivery, '/panel/planner-hub/team/chat?to=1450', ownerReply);
  await owner.goto(`${baseURL}/panel`, { waitUntil: 'domcontentloaded' });
  await owner.locator('#chatUnreadDropdown').click();
  const ownerUnread = owner.locator('[data-chat-unread-list] a').filter({ hasText: ownerReply }).first();
  await expect(ownerUnread).toBeVisible();
  await ownerUnread.click();
  await expect(owner.locator('[data-chat-form]')).toBeVisible();
  await expect(owner.locator('#chatMessages')).toContainText(ownerReply);
  await owner.screenshot({ path: testInfo.outputPath('level2-header-open.png'), fullPage: true });

  await Promise.all([ownerContext.close(), clientContext.close(), deliveryContext.close()]);
});
