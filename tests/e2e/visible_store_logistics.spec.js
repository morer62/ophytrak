const { test, expect } = require('@playwright/test');

const baseURL = process.env.E2E_BASE_URL || 'http://localhost/ophyra';
const runId = process.env.E2E_RUN_ID || Date.now().toString();
const qa = {
  company: `Ophyra QA Logistics ${runId}`,
  ownerEmail: `qa.owner.${runId}@example.test`,
  password: 'OphyraQA!2026',
};

test.use({
  viewport: { width: 1440, height: 960 },
  video: 'on',
  trace: 'on',
});

test('visible Level 2 signup and workspace orientation', async ({ page }, testInfo) => {
  test.setTimeout(180_000);
  const browserErrors = [];
  page.on('console', message => {
    if (message.type() === 'error') browserErrors.push(`console: ${message.text()}`);
  });
  page.on('pageerror', error => browserErrors.push(`page: ${error.message}`));
  page.on('response', response => {
    if (response.status() >= 500) browserErrors.push(`HTTP ${response.status()}: ${response.url()}`);
  });

  await page.goto(`${baseURL}/signup`, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('form[data-signup-wizard]')).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('01-signup-company.png'), fullPage: true });

  await page.locator('[name="company_name"]').fill(qa.company);
  await page.locator('[data-signup-next]').click();
  await page.locator('[name="business_nature"]').selectOption('logistics_delivery');
  await page.locator('[name="business_operation_type"]').selectOption('store_delivery_tracking');
  await page.locator('[data-signup-next]').click();
  await page.locator('[name="name"]').fill('QA');
  await page.locator('[name="lastname"]').fill('Owner');
  await page.locator('[name="email"]').fill(qa.ownerEmail);
  await page.locator('[name="phone_local_number"]').fill('2025550198');
  await page.locator('[data-signup-next]').click();
  await page.locator('[name="password"]').fill(qa.password);
  await page.locator('[name="passwordConfirmation"]').fill(qa.password);
  await page.locator('[name="terms"]').check();
  await page.screenshot({ path: testInfo.outputPath('02-signup-ready.png'), fullPage: true });
  await page.locator('[data-signup-submit]').click();
  await expect(page.locator('[data-signup-success-modal]')).toHaveClass(/is-open/, { timeout: 30_000 });
  await page.screenshot({ path: testInfo.outputPath('03-signup-success.png'), fullPage: true });

  await page.locator('[data-signup-success-login]').click();
  await expect(page).toHaveURL(/login/);
  await page.locator('input[name="email"]').fill(qa.ownerEmail);
  await page.locator('input[name="password"]').fill(qa.password);
  await page.locator('form').filter({ has: page.locator('input[name="email"]') }).locator('button[type="submit"]').click();
  await page.waitForLoadState('domcontentloaded');
  await expect(page).toHaveURL(/panel/);
  await page.screenshot({ path: testInfo.outputPath('04-level2-dashboard.png'), fullPage: true });

  await page.goto(`${baseURL}/panel/planner-hub`, { waitUntil: 'domcontentloaded' });
  await page.screenshot({ path: testInfo.outputPath('05-module-center.png'), fullPage: true });

  await testInfo.attach('qa-account.json', {
    body: Buffer.from(JSON.stringify({ ...qa, baseURL }, null, 2)),
    contentType: 'application/json',
  });
  expect(browserErrors, browserErrors.join('\n')).toEqual([]);
});
