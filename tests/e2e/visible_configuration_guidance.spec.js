const { test, expect } = require('@playwright/test');
const baseURL = process.env.E2E_BASE_URL || 'http://localhost/ophyra';

test('Level 2 sees clean payment brands and actionable payment/SMTP alternatives', async ({ page }) => {
  await page.goto(`${baseURL}/login`);await page.locator('[name="email"]').fill('qa.owner.20260716a@example.test');await page.locator('[name="password"]').fill('OphyraQA!2026');await page.locator('form').filter({has:page.locator('[name="email"]')}).locator('button[type="submit"]').click();
  await page.goto(`${baseURL}/panel/planner-hub/settings/payment-providers`);
  await expect(page.locator('.alert-primary')).toContainText(/manual payment|pago manual/i);
  await expect(page.getByRole('link',{name:/manual payment|pago manual/i})).toHaveAttribute('href',/store\/orders\/manual/);
  const paymentText=await page.locator('body').innerText();expect(paymentText).not.toMatch(/ðŸ|âž|âœ|âš/);
  await page.goto(`${baseURL}/panel/planner-hub/settings/smtp`);
  await expect(page.locator('.alert-primary')).toContainText(/private chat|chat privado/i);
  await expect(page.getByRole('link',{name:/private chat|chat privado/i})).toHaveAttribute('href',/panel\/chat/);
  const smtpText=await page.locator('body').innerText();expect(smtpText).not.toMatch(/ðŸ|âž|âœ|âš/);
});
