const { test, expect } = require('@playwright/test');

const baseURL = process.env.E2E_BASE_URL || 'http://localhost/ophytrak';
const email = 'qa.delivery.20260716a@example.test';
const password = 'OphyraQA!2026';
const proof = { name: 'qa-proof.png', mimeType: 'image/png', buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', 'base64') };

test.use({
  viewport: { width: 390, height: 844 },
  geolocation: { latitude: 25.7617, longitude: -80.1918 },
  permissions: ['geolocation'],
  video: 'on',
  trace: 'on',
});

test('visible preparation tag, route start and delivery proof', async ({ page }, testInfo) => {
  test.setTimeout(180_000);
  await page.goto(`${baseURL}/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('[name="email"]').fill(email);
  await page.locator('[name="password"]').fill(password);
  await page.locator('form').filter({ has: page.locator('[name="email"]') }).locator('button[type="submit"]').click();

  await page.goto(`${baseURL}/panel/planner-hub/team/my-work`, { waitUntil: 'domcontentloaded' });
  const preparation = page.locator('article').filter({ has: page.locator('.print-package-tag') }).filter({ hasText: '#4' });
  await expect(preparation).toBeVisible();
  await preparation.locator('.print-package-tag').click();
  await expect(page.locator('#packageTagModal')).toHaveClass(/show/);
  await expect(page.locator('#teamPackageQr img')).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('01-package-tag-qr.png'), fullPage: true });
  await page.locator('#packageTagModal .btn-close').click();

  const completePreparation = preparation.locator('button[name="action"][value="complete"]');
  if (await completePreparation.count()) {
    await Promise.all([page.waitForNavigation(), completePreparation.click()]);
  }
  await page.screenshot({ path: testInfo.outputPath('02-preparation-complete.png'), fullPage: true });

  await page.goto(`${baseURL}/panel/planner-hub/team/driver-mode`, { waitUntil: 'domcontentloaded' });
  await page.locator('[data-driver-modal-target]').filter({ hasText: /Details/i }).first().click();
  const startForm = page.locator('.driver-task-form').filter({ has: page.locator('[name="action"][value="out_for_delivery"]') });
  if (await startForm.count()) {
    await startForm.locator('[name="dispatch_proof"]').setInputFiles(proof);
    await page.screenshot({ path: testInfo.outputPath('03-route-start-ready.png'), fullPage: true });
    await Promise.all([page.waitForNavigation(), startForm.locator('button').click()]);
  }

  await page.goto(`${baseURL}/panel/planner-hub/team/driver-mode`, { waitUntil: 'domcontentloaded' });
  await page.locator('[data-driver-modal-target]').first().click();
  const deliveredForm = page.locator('.driver-task-form').filter({ has: page.locator('[name="action"][value="delivered"]') });
  await expect(deliveredForm).toBeVisible();
  await deliveredForm.locator('[data-driver-flow="proof"]').click();
  await deliveredForm.locator('[name="delivery_proof"]').setInputFiles(proof);
  await deliveredForm.locator('[name="receiver_type"]').selectOption('BUYER');
  await deliveredForm.locator('[name="receiver_name"]').fill('QA Receiver');
  await deliveredForm.locator('[name="document_type"][value="ID"]').check();
  await deliveredForm.locator('[name="document_number"]').fill('QA-12345');
  await deliveredForm.locator('[data-delivery-notes]').fill('Delivered to QA client after visible QR and tracking validation.');
  await deliveredForm.locator('[name="location_lat"]').fill('25.7617');
  await deliveredForm.locator('[name="location_long"]').fill('-80.1918');
  await deliveredForm.locator('[name="location_accuracy"]').fill('10');
  await deliveredForm.locator('[name="location_permission_status"]').fill('granted');
  await page.screenshot({ path: testInfo.outputPath('04-delivery-proof-ready.png'), fullPage: true });
  await deliveredForm.evaluate(form => { form.dataset.locationReady = '1'; });
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    deliveredForm.locator('[data-driver-action="delivered"]').click(),
  ]);

  await page.goto(`${baseURL}/panel/planner-hub/team/my-work`, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('article').filter({ hasText: '#4' }).filter({ hasText: 'COMPLETED' })).toHaveCount(2);
  await page.screenshot({ path: testInfo.outputPath('05-delivery-complete.png'), fullPage: true });
});
