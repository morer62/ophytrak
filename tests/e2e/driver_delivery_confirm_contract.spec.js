const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '../..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');

test('delivery screen follows the reviewed driver flow', async () => {
  const view = read('src/views/panel/level4/planner-hub/team/driver-mode/index.twig');
  const controller = read('src/views/panel/level4/planner-hub/team/driver-mode/index.php');
  const es = JSON.parse(read('src/Languages/es.json'));
  const pt = JSON.parse(read('src/Languages/pt.json'));

  expect(es.store_logistics.scan_package_qr).toBe('Escanear QR');
  expect(pt.store_logistics.scan_package_qr).toBe('Escanear QR');
  expect(es.store_logistics.confirm_delivery).toBe('Confirmar Entrega');
  expect(pt.store_logistics.confirm_delivery).toBe('Confirmar Entrega');
  expect(view).toContain("{% if carrierView != 'deliveries' %}<button class=\"btn btn-warning\"");
  expect(view).toContain("{{ trans('store_logistics.confirm_delivery') }}");
  expect(view).not.toContain('>Guardar entrega</button>');
  expect(view).toContain("width:{ideal:1280},height:{ideal:720}");
  expect(view).toContain("'image/jpeg',.82");
  expect(view).toContain("if(!input.files||!input.files.length)");
  expect(controller).toContain("if($photo===''||$photo2==='')");
  expect(controller).toContain("$meta['evidence_captured_at']=$capturedAt");
});
