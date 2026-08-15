const { test, expect } = require('@playwright/test');

const baseURL = process.env.E2E_BASE_URL || 'http://localhost/ophyra';
const email = process.env.E2E_OWNER_EMAIL || 'qa.owner.20260716a@example.test';
const password = process.env.E2E_OWNER_PASSWORD || 'OphyraQA!2026';

test.use({ viewport: { width: 1440, height: 960 }, video: 'on', trace: 'on' });

test('visible authenticated Store workspace inventory', async ({ page }, testInfo) => {
  test.setTimeout(120_000);
  await page.goto(`${baseURL}/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await page.locator('form').filter({ has: page.locator('input[name="email"]') }).locator('button[type="submit"]').click();
  await expect(page).toHaveURL(/panel/);

  await page.goto(`${baseURL}/panel/planner-hub/institution-profile`, { waitUntil: 'domcontentloaded' });
  await page.locator('[name="company_name"]').fill('Ophyra QA Logistics 20260716a');
  await page.locator('[name="business_nature"]').selectOption('logistics_delivery');
  await page.locator('[name="business_operation_type"]').selectOption('store_delivery_tracking');
  await page.locator('[name="phone"]').fill('+12025550198');
  await page.locator('[name="email"]').fill(email);
  await page.locator('[name="address_line1"]').fill('100 QA Test Avenue');
  await page.locator('[name="city"]').fill('Miami');
  await page.locator('[name="state"]').fill('FL');
  await page.locator('[name="zip"]').fill('33101');
  await page.locator('[name="country"]').fill('USA');
  await page.locator('[name="payment_method_accepted"]').fill('Square test card');
  await page.locator('[name="short_description"]').fill('Visible QA workspace for Store and Logistics delivery flows.');
  await page.locator('#businessProfileForm button[type="submit"]').click();
  await page.waitForLoadState('domcontentloaded');
  await page.screenshot({ path: testInfo.outputPath('00-profile-complete.png'), fullPage: true });

  const routes = [
    'panel/planner-hub',
    'panel/planner-hub/store/products/home',
    'panel/planner-hub/store/categories/home',
    'panel/planner-hub/store/orders/home',
    'panel/planner-hub/settings/payment-providers',
    'panel/planner-hub/team',
    'panel/chat',
  ];
  const observations = [];
  for (let index = 0; index < routes.length; index += 1) {
    const route = routes[index];
    const response = await page.goto(`${baseURL}/${route}`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(600);
    observations.push({
      requested: route,
      status: response ? response.status() : null,
      finalUrl: page.url(),
      title: await page.title(),
      heading: await page.locator('h1,h2').first().textContent().catch(() => ''),
      text: (await page.locator('body').innerText()).slice(0, 500),
    });
    await page.screenshot({ path: testInfo.outputPath(`${String(index + 1).padStart(2, '0')}-${route.replaceAll('/', '-')}.png`), fullPage: true });
  }
  console.log(`E2E_OBSERVATIONS=${JSON.stringify(observations)}`);
});
