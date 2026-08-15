const { test, expect } = require('@playwright/test');
const baseURL = process.env.E2E_BASE_URL || 'http://localhost/ophyra';

async function login(page, email) {
  await page.goto(`${baseURL}/login`);
  await page.locator('[name="email"]').fill(email);
  await page.locator('[name="password"]').fill('OphyraQA!2026');
  await page.locator('form').filter({ has: page.locator('[name="email"]') }).locator('button[type="submit"]').click();
}

test('delivery attempted, returned and redelivery are persisted visibly', async ({ browser }) => {
  const deliveryContext=await browser.newContext();const delivery=await deliveryContext.newPage();await login(delivery,'qa.delivery.20260716a@example.test');
  for (const action of [{task_id:'10',action:'delivery_attempted',notes:'Cliente ausente; se requiere nuevo intento.'},{task_id:'11',action:'returned_to_business',notes:'Paquete devuelto al negocio sin daños.'}]) {
    const response=await delivery.request.post(`${baseURL}/panel/planner-hub/team/my-work`,{form:{...action,location_lat:'25.7617',location_long:'-80.1918',location_accuracy:'10',location_permission_status:'granted',location_context:'qa_exception'}});
    expect(response.ok()).toBeTruthy();
  }
  await deliveryContext.close();

  const ownerContext=await browser.newContext();const owner=await ownerContext.newPage();await login(owner,'qa.owner.20260716a@example.test');
  await owner.goto(`${baseURL}/panel/planner-hub/store/orders/history`);
  const attempted=owner.locator('tr').filter({hasText:'#7'});const returned=owner.locator('tr').filter({hasText:'#8'});
  await expect(attempted).toContainText(/attempt/i);await expect(returned).toContainText(/returned|devuelto/i);
  const response=await owner.request.post(`${baseURL}/panel/planner-hub/store/orders/history`,{form:{order_id:'8',status:'REDELIVERY_SCHEDULED',return_admin_message:'Nuevo intento autorizado por Level 2.'}});
  expect(response.ok()).toBeTruthy();
  await owner.goto(`${baseURL}/panel/planner-hub/store/orders/history`);
  await expect(owner.locator('tr').filter({hasText:'#8'})).toContainText(/redelivery|reentrega/i);
  await ownerContext.close();
});
