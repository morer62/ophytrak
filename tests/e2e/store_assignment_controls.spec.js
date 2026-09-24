const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

test('external carrier assignment is QR-only and never manually selected', async () => {
  const root=path.resolve(__dirname,'../..');
  const template=fs.readFileSync(path.join(root,'src/views/panel/level2/planner-hub/store/orders/home/index.twig'),'utf8');
  const controller=fs.readFileSync(path.join(root,'src/views/panel/level2/planner-hub/store/orders/home/index.php'),'utf8');
  const carrierRepo=fs.readFileSync(path.join(root,'src/Repositories/CarrierPackageRepository.php'),'utf8');
  expect(template).toContain('value="carrier_qr"');
  expect(template).not.toContain('id="assignCarrierOwnerSelect"');
  expect(controller).toContain("['own_team','carrier_qr']");
  expect(carrierRepo).toContain('only when an authorized carrier scans the secure QR');
  expect(carrierRepo).toContain('recordForCustody($package,$carrierOwner)');
});
