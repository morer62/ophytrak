const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

test('attempted packages expose their status and can start a new delivery attempt', async () => {
  const root = path.resolve(__dirname, '../..');
  const view = fs.readFileSync(path.join(root, 'src/views/panel/level4/planner-hub/team/driver-mode/index.twig'), 'utf8');
  const repository = fs.readFileSync(path.join(root, 'src/Repositories/CarrierPackageRepository.php'), 'utf8');
  expect(view).toContain('data-bs-target="#retryPackage{{ package.id }}"');
  expect(view).toContain('Ver estado y nuevo intento');
  expect(view).toContain('id="retryPackage{{ package.id }}"');
  expect(view).toContain('name="carrier_stage" value="out_for_delivery"');
  expect(view).toContain('Iniciar nuevo intento de entrega');
  expect(repository).toContain("'CUSTOMER_REJECTED'=>['received_hub','out_for_delivery']");
});
