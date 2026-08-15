const { test, expect } = require('@playwright/test');
const baseURL=process.env.E2E_BASE_URL||'http://localhost/ophytrak';
const runId=process.env.E2E_RUN_ID||Date.now().toString();
const email=`qa.carrier.${runId}@example.test`;
test('carrier signup includes logistics without billing',async({page},testInfo)=>{
  await page.goto(`${baseURL}/signup`);await page.locator('[name="company_name"]').fill(`QA Carrier ${runId}`);await page.locator('[data-signup-next]').click();
  await page.locator('[name="business_nature"]').selectOption('carrier_logistics');await page.locator('[name="business_operation_type"]').selectOption('store_delivery_tracking');await page.locator('[data-signup-next]').click();
  await page.locator('[name="name"]').fill('Carrier');await page.locator('[name="lastname"]').fill('Owner');await page.locator('[name="email"]').fill(email);await page.locator('[name="phone_local_number"]').fill('2025550188');await page.locator('[data-signup-next]').click();
  await page.locator('[name="password"]').fill('OphyraQA!2026');await page.locator('[name="passwordConfirmation"]').fill('OphyraQA!2026');await page.locator('[name="terms"]').check();await page.locator('[data-signup-submit]').click();await expect(page.locator('[data-signup-success-modal]')).toHaveClass(/is-open/,{timeout:30000});
  await testInfo.attach('carrier-account.json',{body:Buffer.from(JSON.stringify({email,runId},null,2)),contentType:'application/json'});
});
