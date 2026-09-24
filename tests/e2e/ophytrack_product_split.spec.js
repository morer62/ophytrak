const { test, expect } = require('@playwright/test');

const baseURL = process.env.E2E_BASE_URL || 'http://localhost/ophytrak';
const sellerEmail = process.env.E2E_OWNER_EMAIL || 'qa.seller.20260806cert1@example.test';
const password = process.env.E2E_OWNER_PASSWORD || 'OphyraQA!2026';

test.use({ viewport: { width: 1440, height: 960 }, trace: 'on', video: 'on' });

test('public product and signup expose only OPHYTRACK logistics', async ({ page }) => {
  let response = await page.goto(`${baseURL}/`, { waitUntil: 'domcontentloaded' });
  expect(response.status()).toBe(200);
  await expect(page).toHaveTitle(/OPHYTRACK/i);
  await expect(page.locator('body')).toContainText('OPHYTRACK');
  await expect(page.locator('video')).toHaveCount(1);
  await expect(page.locator('body')).not.toContainText('Book services');

  response = await page.goto(`${baseURL}/signup`, { waitUntil: 'domcontentloaded' });
  expect(response.status()).toBe(200);
  await expect(page.locator('body')).toContainText('OPHYTRACK');
  await expect(page.locator('[name="business_nature"] option[value="service_business"]')).toHaveCount(0);
  await expect(page.locator('[name="business_nature"] option[value="carrier_logistics"]')).toHaveCount(1);
  await expect(page.locator('[name="business_operation_type"] option[value="store_delivery_tracking"]')).toHaveText('Shipping and tracking');
  await expect(page.locator('[name="business_operation_type"] option[value="contracts_services"]')).toHaveCount(0);
});

test('public logistics story and signup remain localized in every supported language', async ({ page }) => {
  const expectations = {
    en: ['Every package. Every handoff.', 'Create your OPHYTRACK logistics account', 'Shipping and tracking'],
    es: ['Cada paquete. Cada traspaso.', 'Crea tu cuenta logística OPHYTRACK', 'Envío y rastreo'],
    pt: ['Cada pacote. Cada transferência.', 'Crie sua conta logística OPHYTRACK', 'Envio e rastreamento'],
    fr: ['Chaque colis. Chaque transfert.', 'Créez votre compte logistique OPHYTRACK', 'Expédition et suivi'],
  };

  for (const [locale, [hero, signup, operation]] of Object.entries(expectations)) {
    let response = await page.goto(`${baseURL}/?locale=${locale}`, { waitUntil: 'domcontentloaded' });
    expect(response.status()).toBe(200);
    await expect(page.locator('h1')).toContainText(hero);
    await expect(page.locator('body')).toContainText(/Shopify/);
    await expect(page.locator('body')).not.toContainText(/ophytrack_public\.|Ã.|Â.|â€|�/);

    response = await page.goto(`${baseURL}/signup?locale=${locale}`, { waitUntil: 'domcontentloaded' });
    expect(response.status()).toBe(200);
    await expect(page.locator('.signup-step-title')).toContainText(signup);
    await expect(page.locator('[name="business_operation_type"] option[value="store_delivery_tracking"]')).toHaveText(operation);
    await expect(page.locator('body')).not.toContainText(/ophytrack_public\.|Ã.|Â.|â€|�/);
  }
});

test('seller sees logistics shell and legacy service routes are closed', async ({ page }) => {
  await page.goto(`${baseURL}/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('[name="email"]').fill(sellerEmail);
  await page.locator('[name="password"]').fill(password);
  await page.locator('form').filter({ has: page.locator('[name="email"]') }).locator('button[type="submit"]').click();
  await expect(page).toHaveURL(/panel/);

  await page.goto(`${baseURL}/panel/planner-hub`, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('body')).toContainText('OPHYTRACK');
  await expect(page.locator('#sidebar')).not.toContainText('Services');
  await expect(page.locator('#sidebar')).not.toContainText('Tickets');

  await page.goto(`${baseURL}/panel/planner-hub/store/orders/home`, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('body')).toContainText(/Store|Tienda|Loja/i);

  await page.goto(`${baseURL}/panel/planner-hub/management/orders`, { waitUntil: 'domcontentloaded' });
  await expect(page).not.toHaveURL(/management\/orders(?:$|\?)/);
});

test('membership billing is locked to Brazilian reais', async ({ page }) => {
  await page.goto(`${baseURL}/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('[name="email"]').fill(sellerEmail);
  await page.locator('[name="password"]').fill(password);
  await page.locator('form').filter({ has: page.locator('[name="email"]') }).locator('button[type="submit"]').click();
  await expect(page).toHaveURL(/panel/);

  const response = await page.goto(`${baseURL}/panel/membership/manage?payment_currency=EUR`, { waitUntil: 'domcontentloaded' });
  expect(response.status()).toBe(200);
  await expect(page.locator('body')).toContainText('BRL');
  await expect(page.locator('select[name="payment_currency"]')).toHaveCount(0);
  const submittedCurrencies = await page.locator('input[name="payment_currency"]').evaluateAll(
    inputs => inputs.map(input => input.value)
  );
  expect(submittedCurrencies.length).toBeGreaterThan(0);
  expect(new Set(submittedCurrencies)).toEqual(new Set(['BRL']));
});

test('locked logistics guides a new seller through activation', async ({ page }) => {
  test.setTimeout(90000);
  const runId = Date.now().toString();
  const email = `qa.activation.${runId}@example.test`;

  await page.goto(`${baseURL}/signup`, { waitUntil: 'domcontentloaded' });
  await page.locator('[name="company_name"]').fill(`QA Activation ${runId}`);
  await page.locator('[data-signup-next]').click();
  await page.locator('[name="business_nature"]').selectOption('commerce_operations');
  await page.locator('[name="business_operation_type"]').selectOption('store_delivery_tracking');
  await page.locator('[data-signup-next]').click();
  await page.locator('[name="name"]').fill('Activation');
  await page.locator('[name="lastname"]').fill('Seller');
  await page.locator('[name="email"]').fill(email);
  await page.locator('[name="phone_local_number"]').fill('2025550177');
  await page.locator('[data-signup-next]').click();
  await page.locator('[name="password"]').fill(password);
  await page.locator('[name="passwordConfirmation"]').fill(password);
  await page.locator('[name="terms"]').check();
  await page.locator('[data-signup-submit]').click();
  await expect(page.locator('[data-signup-success-modal]')).toHaveClass(/is-open/, { timeout: 30000 });

  await page.goto(`${baseURL}/login`, { waitUntil: 'domcontentloaded' });
  await page.locator('[name="email"]').fill(email);
  await page.locator('[name="password"]').fill(password);
  await page.locator('form').filter({ has: page.locator('[name="email"]') }).locator('button[type="submit"]').click();
  await expect(page).toHaveURL(/panel/);

  await page.goto(`${baseURL}/panel/planner-hub/institution-profile`, { waitUntil: 'domcontentloaded' });
  await page.locator('[name="address_line1"]').fill('500 Logistics Avenue');
  await page.locator('[name="city"]').fill('Miami');
  await page.locator('[name="state"]').fill('FL');
  await page.locator('[name="zip"]').fill('33101');
  await page.locator('[name="short_description"]').fill('QA logistics activation workspace.');
  await page.getByRole('button', { name: 'Save Profile' }).click();
  await page.waitForLoadState('domcontentloaded');

  await page.locator('#languageDropdownHeader').click();
  await Promise.all([
    page.waitForResponse(response => response.url().includes('/api/change-language') && response.request().method() === 'POST'),
    page.locator('#languageDropdownMenuHeader [data-locale="es"]').click(),
  ]);
  await page.waitForFunction(() => document.documentElement.lang.toLowerCase().startsWith('es'));

  await page.goto(`${baseURL}/panel/planner-hub/no-access?module=inventory_storage`, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#activationGuideModal')).toBeVisible();
  await expect(page.locator('#activationGuideTitle')).toContainText('Activa tu espacio logistico');
  await expect(page.locator('.locked-module-panel')).toContainText('Tienda + Logistica aun no esta activo');
  await expect(page.locator('.locked-module-panel')).toContainText('Modulo bloqueado');
  await expect(page.locator('.locked-module-panel')).toContainText(/Shopify/);
  const continueLink = page.locator('#activationGuideModal a[href*="activation_flow=store_delivery_tracking"]');
  await expect(continueLink).toBeVisible();
  await continueLink.click();
  await expect(page).toHaveURL(/panel\/cards\?activation_flow=store_delivery_tracking&locale=es/);
  await expect(page.locator('body')).toContainText('Paso 1 de 2: agrega tu metodo de pago.');

  const stripeFrame = page.frameLocator('iframe[title*="Secure card payment input frame"]');
  await stripeFrame.locator('[name="cardnumber"]').fill('4242424242424242');
  await stripeFrame.locator('[name="exp-date"]').fill('1230');
  await stripeFrame.locator('[name="cvc"]').fill('123');
  await stripeFrame.locator('[name="postal"]').fill('33101');
  await page.locator('#submit-card').click();
  await expect(page).toHaveURL(/no-access\?module=store_delivery_tracking&activation_ready=1&locale=es/, { timeout: 30000 });
  await expect(page.locator('#activationGuideModal')).toBeVisible();
  await expect(page.locator('#activationGuideTitle')).toContainText(/Activate Store|activar Tienda|Ativar Loja|Activer Boutique/i);
  const activateLink = page.locator('#activationGuideModal a[href*="membership/modules/review"]');
  await expect(activateLink).toBeVisible();
  await activateLink.click();
  await expect(page).toHaveURL(/membership\/modules\/review/);
  await expect(page.locator('a[href*="activation_flow=store_delivery_tracking"]')).toHaveCount(0);
  const confirmPayment = page.locator('form').filter({ has: page.locator('input[name="action"][value="confirm_pay"]') }).locator('button[type="submit"]');
  await expect(confirmPayment).toBeVisible();
  await confirmPayment.click();
  await expect(page).toHaveURL(/membership\/modules\/success/, { timeout: 30000 });

  for (const locale of ['es', 'pt', 'fr', 'en']) {
    await page.goto(`${baseURL}/panel/planner-hub/store/orders/home?status=OUT_FOR_DELIVERY&locale=${locale}`, { waitUntil: 'domcontentloaded' });
    await expect(page.locator('body')).not.toContainText(/(?:store_orders|store_logistics|planner_hub|ui)\.[a-z0-9_.-]+/i);
    await expect(page.locator('body')).not.toContainText(/Ãƒ.|Ã‚.|Ã¢â‚¬|ï¿½/);
  }

  const clientEmail = `qa.client.modal.${runId}@example.test`;
  await page.goto(`${baseURL}/panel/home?locale=es`, { waitUntil: 'domcontentloaded' });
  await page.locator('#ophyraCreateDropdown').click();
  const quickCreateMenu = page.locator('.ophyra-create-menu');
  await expect(quickCreateMenu).toContainText('Pedido de tienda');
  await expect(quickCreateMenu).not.toContainText(/presupuesto|servicio/i);
  await expect(quickCreateMenu.locator('a[href*="/store/orders/manual"]')).toBeVisible();
  await expect(quickCreateMenu.locator('a[href*="/management/orders/orders/create"]')).toHaveCount(0);

  await page.goto(`${baseURL}/panel/planner-hub/management/users/create?locale=es`, { waitUntil: 'domcontentloaded' });
  await page.locator('#startFlowCard button').click();
  await page.locator('.user-type-card[data-type="5"]').click();
  await page.locator('#emailInput').fill(clientEmail);
  await page.locator('#validateEmailBtn').click();
  await expect(page.locator('#mainFormCard')).toBeVisible({ timeout: 15000 });
  await page.locator('#create-user-form [name="name"]').fill('Cliente');
  await page.locator('#create-user-form [name="lastname"]').fill('Logística');
  await page.locator('#create-user-form [name="phone"]').fill('+5511999999999');
  await page.locator('#create-user-form [name="password"]').fill(password);
  await page.locator('#create-user-form [name="password_confirm"]').fill(password);
  await page.locator('#create-user-form button[type="submit"]').click();
  await expect(page.locator('#createEstimateModal')).toBeVisible({ timeout: 30000 });
  await expect(page.locator('#createEstimateModal')).toContainText('Cliente creado');
  await expect(page.locator('#createEstimateModal')).toContainText('¿Quieres crear ahora su primer pedido de tienda?');
  await expect(page.locator('#createEstimateModal')).not.toContainText(/Ã|Â|â€|ï¿½/);
  await page.locator('#createEstimateModal .btn-primary').click();
  await expect(page).toHaveURL(/panel\/planner-hub\/store\/orders\/manual\?client_id=\d+/);
  await expect(page.locator('body')).toContainText('R$');
  await expect(page.locator('body')).not.toContainText(/\b(?:USD|EUR|GBP)\b/);
  await expect(page.locator('body')).not.toContainText(/(^|[^R])\$\d/);
  await expect(page.locator('#display_currency')).toHaveCount(0);
  await expect(page.locator('[name="shipping_zip"]')).toHaveAttribute('placeholder', 'CP');
  await expect(page.locator('[name="shipping_zip"]')).toHaveAttribute('autocomplete', 'postal-code');
  await expect(page.locator('#paymentMode')).toHaveValue('manual_proof');
  await expect(page.locator('#paymentProof')).toHaveAttribute('required', '');
  await page.locator('#paymentMode').evaluate(select => {
    select.value = 'mark_paid';
    select.dispatchEvent(new Event('change', { bubbles: true }));
  });
  await expect(page.locator('#paymentProof')).not.toHaveAttribute('required', '');
  await expect(page.locator('#manualProofBox')).toHaveClass(/d-none/);

  await page.goto(`${baseURL}/panel/planner-hub/settings/payment-providers?locale=es`, { waitUntil: 'domcontentloaded' });
  await expect(page.locator('select[name="currency"]')).toHaveCount(0);
  const providerCurrencies = await page.locator('input[name="currency"]').evaluateAll(inputs => inputs.map(input => input.value));
  expect(providerCurrencies.length).toBeGreaterThan(0);
  expect(new Set(providerCurrencies)).toEqual(new Set(['BRL']));
  await expect(page.locator('body')).not.toContainText(/\b(?:USD|EUR|GBP|CAD|MXN|CLP|COP)\b/);
});
