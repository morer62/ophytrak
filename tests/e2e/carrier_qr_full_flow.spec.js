const {test,expect}=require('@playwright/test');
const baseURL=process.env.E2E_BASE_URL||'http://localhost/ophyra';
const proof={name:'evidence.png',mimeType:'image/png',buffer:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=','base64')};
test('secure QR enters carrier custody without approval and completes full chain',async({page})=>{
 await page.goto(`${baseURL}/login`);await page.locator('[name="email"]').fill('qa.carrier.delivery.20260806carrier1@example.test');await page.locator('[name="password"]').fill('OphyraQA!2026');await page.locator('form').filter({has:page.locator('[name="email"]')}).locator('button[type="submit"]').click();
 const claim=await page.request.post(`${baseURL}/panel/planner-hub/team/driver-mode`,{headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},multipart:{action:'carrier_qr_claim',qr_token:'2547d8897b0aff4a28404009601421f629e5c127b0219baf',latitude:'25.7617',longitude:'-80.1918',qr_photo:proof}});expect(claim.ok()).toBeTruthy();const payload=await claim.json();expect(payload.success).toBeTruthy();const packageId=String(payload.package_id);
 async function stage(carrierStage,extra={}){const response=await page.request.post(`${baseURL}/panel/planner-hub/team/driver-mode`,{multipart:{action:'carrier_advance',package_id:packageId,carrier_stage:carrierStage,notes:`QA ${carrierStage}`,latitude:'25.7617',longitude:'-80.1918',evidence_photo:proof,...extra}});expect(response.ok()).toBeTruthy();}
 await stage('received_hub');await stage('sorted_hub');await stage('out_for_delivery');await stage('customer_absent');await stage('out_for_delivery');await stage('delivered',{receiver_type:'BUYER',receiver_name:'QA Recipient',document_type:'ID',document_number:'QA-7788'});
 await page.goto(`${baseURL}/panel/planner-hub/team/driver-mode`);await expect(page.locator('body')).toContainText('OPH-1366-6-01');await expect(page.locator('body')).toContainText(/DELIVERED/i);
});
