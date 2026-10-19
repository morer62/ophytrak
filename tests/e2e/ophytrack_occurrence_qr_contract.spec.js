const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

test('driver delivery occurrence captures reasons and two photos; delivery scanner stays focused', async () => {
  const root = path.resolve(__dirname, '../..');
  const view = fs.readFileSync(path.join(root, 'src/views/panel/level4/planner-hub/team/driver-mode/index.twig'), 'utf8');
  const controller = fs.readFileSync(path.join(root, 'src/views/panel/level4/planner-hub/team/driver-mode/index.php'), 'utf8');
  const repository = fs.readFileSync(path.join(root, 'src/Repositories/CarrierPackageRepository.php'), 'utf8');

  expect(view).toContain('id="carrierOccurrence{{ package.id }}"');
  expect(view).toContain('name="occurrence_reason"');
  for (const reason of ['CUSTOMER_REJECTED', 'RISK_AREA', 'OUT_OF_ROUTE', 'ADDRESS_NOT_FOUND', 'CUSTOMER_ABSENT']) {
    expect(view).toContain(`value="${reason}"`);
    expect(controller).toContain(`'${reason}'`);
  }
  expect(view).toContain('name="occurrence_photo_1"');
  expect(view).toContain('name="occurrence_photo_2"');
  expect(view).toContain("form.querySelector('.driver-bottom-actions')");
  expect(controller).toContain("$stage==='delivery_occurrence'");
  expect(controller).toContain("$meta['occurrence_reason']=$reason");
  expect(controller).toContain("$meta['photo_url_2']=$photo2");
  expect(repository).toContain("'CUSTOMER_ABSENT'=>['received_hub','out_for_delivery']");

  expect(view).toContain("isCarrierOrganization and carrierView == 'deliveries'");
  expect(view).toContain("isCarrierOrganization and carrierView != 'deliveries'");
});
