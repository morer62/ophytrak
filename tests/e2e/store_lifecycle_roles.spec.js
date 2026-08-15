const { test, expect } = require('@playwright/test');

const baseURL = process.env.E2E_BASE_URL || 'http://localhost/ophytrak';
const password = process.env.E2E_OWNER_PASSWORD || 'OphyraQA!2026';
const ownerEmail = process.env.E2E_OWNER_EMAIL;
const clientEmail = process.env.E2E_CLIENT_EMAIL;
const deliveryEmail = process.env.E2E_DELIVERY_EMAIL;
const orderId = process.env.E2E_ORDER_ID;
const onePixelPng = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', 'base64');

async function login(page, email) {
  await page.goto(`${baseURL}/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('[name="email"]').fill(email);
  await page.locator('[name="password"]').fill(password);
  await page.locator('form').filter({ has: page.locator('[name="email"]') }).locator('button[type="submit"]').click();
  await expect(page).toHaveURL(/panel/);
}

test('owner, team and client share one cancellation and return lifecycle', async ({ browser }, testInfo) => {
  test.setTimeout(180_000);
  const clientContext = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const client = await clientContext.newPage();
  await login(client, clientEmail);
  await client.goto(`${baseURL}/panel/store/orders/home?tab=active`, { waitUntil: 'domcontentloaded' });
  await expect(client.getByText('Active', { exact: false }).first()).toBeVisible();
  let row = client.locator('tr').filter({ hasText: `#${orderId}` });
  await expect(row).toBeVisible();
  await row.locator('.request-cancellation-btn').click();
  await client.locator('#requestCancellationModal [name="cancellation_reason"]').fill('QA customer cancellation lifecycle');
  await client.locator('#requestCancellationModal .btn-danger').click();
  await expect(client).toHaveURL(/tab=incidents/);
  await expect(client.locator('tr').filter({ hasText: `#${orderId}` })).toContainText(/Waiting|Esperando|Aguardando/);
  await client.screenshot({ path: testInfo.outputPath('01-client-cancellation-requested.png'), fullPage: true });

  const teamContext = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const team = await teamContext.newPage();
  await login(team, deliveryEmail);
  await team.goto(`${baseURL}/panel/planner-hub/team/my-work?tab=incidents`, { waitUntil: 'domcontentloaded' });
  await expect(team.locator('body')).toContainText(`#${orderId}`);
  await expect(team.locator('body')).toContainText(/requested cancellation|solicitó cancelar|solicitou cancelamento/i);
  await expect(team.locator(`form[data-task-id]`)).toHaveCount(0);
  await team.screenshot({ path: testInfo.outputPath('02-team-paused-incident.png'), fullPage: true });

  const ownerContext = await browser.newContext({ viewport: { width: 1440, height: 960 } });
  const owner = await ownerContext.newPage();
  await login(owner, ownerEmail);
  await owner.goto(`${baseURL}/panel/planner-hub/store/orders/home?tab=incidents`, { waitUntil: 'domcontentloaded' });
  row = owner.locator('tr').filter({ hasText: `#${orderId}` });
  await expect(row).toBeVisible();
  await row.locator('.lifecycle-decision-btn').click();
  await owner.locator('#lifecycleDecisionSelect').selectOption('RESEND');
  await owner.locator('#lifecycleDecisionForm [name="decision_notes"]').fill('Replacement approved by QA owner');
  await owner.locator('#lifecycleDecisionForm .btn-primary').click();
  await expect(owner).toHaveURL(/tab=active/);

  row = owner.locator('tr').filter({ hasText: `#${orderId}` });
  await expect(row).toBeVisible();
  await row.locator('.manual-logistics-btn').click();
  await owner.locator('#manualLogisticsStatus').selectOption('DELIVERED');
  await owner.locator('#manualDeliveryPhoto').setInputFiles({ name: 'delivered.png', mimeType: 'image/png', buffer: onePixelPng });
  await owner.locator('#manualLogisticsForm [name="manual_logistics_notes"]').fill('QA manual delivery with evidence');
  await Promise.all([
    owner.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    owner.locator('#manualLogisticsSubmit').click(),
  ]);
  await owner.goto(`${baseURL}/panel/planner-hub/store/orders/home?tab=closed`, { waitUntil: 'domcontentloaded' });
  await expect(owner.locator('tr').filter({ hasText: `#${orderId}` })).toBeVisible();
  await owner.screenshot({ path: testInfo.outputPath('03-owner-delivered-closed.png'), fullPage: true });

  await client.goto(`${baseURL}/panel/store/orders/home?tab=closed`, { waitUntil: 'domcontentloaded' });
  row = client.locator('tr').filter({ hasText: `#${orderId}` });
  await expect(row).toBeVisible();
  await row.locator('.request-return-btn').click();
  await client.locator('#requestReturnModal [name="return_notes"]').fill('QA return after delivery');
  await client.locator('#requestReturnModal .btn-danger').click();
  await client.goto(`${baseURL}/panel/store/orders/home?tab=incidents`, { waitUntil: 'domcontentloaded' });
  await expect(client.locator('tr').filter({ hasText: `#${orderId}` })).toBeVisible();

  await owner.goto(`${baseURL}/panel/planner-hub/store/orders/home?tab=incidents`, { waitUntil: 'domcontentloaded' });
  row = owner.locator('tr').filter({ hasText: `#${orderId}` });
  await row.locator('.lifecycle-decision-btn').click();
  await owner.locator('#lifecycleDecisionSelect').selectOption('APPROVE');
  await owner.locator('#lifecycleDecisionForm .btn-primary').click();
  await owner.goto(`${baseURL}/panel/planner-hub/store/orders/home?tab=incidents`, { waitUntil: 'domcontentloaded' });
  row = owner.locator('tr').filter({ hasText: `#${orderId}` });
  await row.locator('.lifecycle-decision-btn').click();
  await owner.locator('#lifecycleDecisionSelect').selectOption('RECEIVED_REFUND');
  await owner.locator('#lifecycleDecisionForm [name="decision_notes"]').fill('Return received and refunded by QA owner');
  await owner.locator('#lifecycleDecisionForm .btn-primary').click();
  await expect(owner).toHaveURL(/tab=closed/);
  await expect(owner.locator('tr').filter({ hasText: `#${orderId}` })).toContainText(/Refunded|Reembolsado|Reembolsada/);
  await owner.screenshot({ path: testInfo.outputPath('04-return-refunded-closed.png'), fullPage: true });

  await ownerContext.close(); await teamContext.close(); await clientContext.close();
});
