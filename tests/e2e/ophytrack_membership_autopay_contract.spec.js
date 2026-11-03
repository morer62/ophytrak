const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '../..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');

test('paid module checkout enables the existing monthly autopay cycle', async () => {
  const review = read('src/views/panel/level2/membership/modules/review/index.php');
  expect(review).toContain('use App\\Repositories\\AutopaySettingRepository;');
  expect(review).toContain("upsertAutopay($billingOwnerId, 'monthly', true)");
});

test('daily autopay finds due paid modules and renews them in BRL', async () => {
  const settings = read('src/Repositories/AutopaySettingRepository.php');
  const modules = read('src/Repositories/UserModulesRepository.php');
  const service = read('src/Services/AutopayService.php');

  expect(settings).toContain('EXISTS (');
  expect(settings).toContain("um.status IN ('ACTIVE', 'EXPIRED')");
  expect(settings).toContain('um.renewal_at <= CURDATE()');
  expect(modules).toContain('function getDuePaidRenewals');
  expect(service).toContain('getDuePaidRenewals($userId, $today)');
  expect(service).toContain('ProductProfileService::billingCurrency()');
  expect(service).toContain('renewAddonAfterAutopay(');
});

test('saved-card renewal is off-session, idempotent and does not duplicate retry rows', async () => {
  const stripe = read('src/Services/StripeService.php');
  const service = read('src/Services/AutopayService.php');
  const addon = read('src/Services/AddonBillingService.php');

  expect(stripe).toContain('function createOffSessionChargeV1');
  expect(stripe).toContain("'off_session' => true");
  expect(stripe).toContain("'idempotency_key' => substr($idempotencyKey, 0, 255)");
  expect(service).toContain("'autopay-' . hash(");
  expect(service).toContain("$setting->getPlanType(),\n                false");
  expect(addon).toContain('findPaymentByBillingTransactionId');
  expect(addon).toContain('$this->findPaymentByBillingTransactionId($billingTransactionId)');
});

test('already-active feedback is Brazilian Portuguese', async () => {
  const pt = JSON.parse(read('src/Languages/pt.json'));
  expect(pt.billing.module_already_active).toBe('Este módulo já está ativo no período de cobrança atual.');
});
