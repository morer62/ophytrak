const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '../..');
const view = fs.readFileSync(
  path.join(root, 'src/views/panel/level4/planner-hub/team/driver-mode/index.twig'),
  'utf8'
);

test('driver delivery tools stay hidden behind the gear menu and use real actions', async () => {
  expect(view).toContain('id="deliveryToolsMenu"');
  expect(view).toContain('data-bs-toggle="dropdown"');
  expect(view).toContain("driver-mode?export_manifest='~activeManifest.id");
  expect(view).toContain('driver-mode?view=deliveries');
  expect(view).toContain('driver-mode?view=collection&carrier_stage=route');
  expect(view).not.toContain('>Ir a Coletas</a>');
});

test('delivery completion requires the requested receiver type before receiver name', async () => {
  const receiverType = view.indexOf('id="receiverType{{ package.id }}"');
  const receiverName = view.indexOf(
    'name="receiver_name" placeholder="Nombre de quien recibe"',
    receiverType
  );

  expect(receiverType).toBeGreaterThan(-1);
  expect(receiverName).toBeGreaterThan(receiverType);
  for (const value of ['RECIPIENT', 'HOUSEHOLD', 'NEIGHBOR', 'DOORMAN', 'RECEPTION']) {
    expect(view.slice(receiverType, receiverName)).toContain(`value="${value}"`);
  }
  expect(view).toContain(
    '[name="receiver_type"],[name="receiver_name"],[name="document_number"]'
  );
});
