const { test, expect } = require('@playwright/test');
const baseURL = process.env.E2E_BASE_URL || 'http://localhost/ophytrak';
const proof = { name: 'delivery-proof.png', mimeType: 'image/png', buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', 'base64') };

test('delivery controller closes the assigned order with local evidence', async ({ page }) => {
  await page.goto(`${baseURL}/login`);
  await page.locator('[name="email"]').fill('qa.delivery.20260716a@example.test');
  await page.locator('[name="password"]').fill('OphyraQA!2026');
  await page.locator('form').filter({ has: page.locator('[name="email"]') }).locator('button[type="submit"]').click();
  const response = await page.request.post(`${baseURL}/panel/planner-hub/team/my-work`, { multipart: {
    task_id: '9', action: 'delivered', location_lat: '25.7617', location_long: '-80.1918', location_accuracy: '10',
    location_platform: 'qa_controller', location_source: 'qa', location_permission_status: 'granted', location_context: 'qa_delivery_close',
    receiver_type: 'BUYER', receiver_name: 'QA Receiver', document_type: 'ID', document_number: 'QA-12345',
    notes: 'Entrega QA completada con evidencia local.', delivery_proof: proof,
  }});
  expect(response.ok()).toBeTruthy();
  await page.goto(`${baseURL}/store/order-access?token=473747ca7d5a9c2465ad7885d7b562635aeec4edeeafb680`);
  await expect(page.locator('body')).toContainText(/DELIVERED|Delivered|Entregado/i);
});
