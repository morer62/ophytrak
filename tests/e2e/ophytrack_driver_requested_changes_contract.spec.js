const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

test('driver requested controls and profile persistence remain in contract', async () => {
  const root = path.resolve(__dirname, '../..');
  const read = file => fs.readFileSync(path.join(root, file), 'utf8');
  const driver = read('src/views/panel/level4/planner-hub/team/driver-mode/index.twig');
  const controller = read('src/views/panel/level4/planner-hub/team/driver-mode/index.php');
  const sidebar = read('src/views/templates/layout/sidebars/ophytrack-4.twig');
  const settings = read('src/views/panel/level4/settings/index.twig');
  const settingsController = read('src/views/panel/level4/settings/index.php');

  expect(driver).toContain('<option value="order">Número de pedido</option>');
  expect(driver).toContain('<option value="remittance">Remesa</option>');
  expect(driver).toContain('<option value="invoice">Nota fiscal</option>');
  expect(driver).toContain('<option value="custom">Personalizado</option>');
  expect(driver).not.toContain('id="packageQrPhotoInput"');
  expect(driver).not.toContain(`class="btn btn-outline-secondary">{{ trans('store_logistics.my_work') }}`);
  expect(controller).toContain("$action==='collection_reference_resolve'");
  expect(sidebar).toContain('{% if not is_carrier_organization %}<li class="sidebar-item"><a class="sidebar-link" href="{{ path(\'panel/planner-hub/team/payroll/clock\') }}"');
  expect(settings).toContain('name="profile_photo"');
  expect(settings).toContain('capture="user"');
  for (const field of ['bank_account_name', 'document_type', 'document_number', 'bank_name', 'bank_agency', 'bank_account_type', 'bank_account_number', 'pix_key']) {
    expect(settings).toContain(`name="${field}"`);
    expect(settingsController).toContain(`'${field}'`);
  }
});
