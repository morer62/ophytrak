const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

test('delivery close requires complete receiver data and a live camera capture', async () => {
  const root = path.resolve(__dirname, '../..');
  const view = fs.readFileSync(path.join(root, 'src/views/panel/level4/planner-hub/team/driver-mode/index.twig'), 'utf8');
  const controller = fs.readFileSync(path.join(root, 'src/views/panel/level4/planner-hub/team/driver-mode/index.php'), 'utf8');
  expect(view).toContain('delivery-live-camera');
  expect(view).toContain('navigator.mediaDevices.getUserMedia');
  expect(view).toContain("trans('store_logistics.delivery_camera_intro')");
  expect(view).toContain('name="evidence_photo_2"');
  expect(view).toContain('data-photo-slot="1"');
  expect(view).toContain('data-photo-slot="2"');
  expect(view).toContain("trans('store_logistics.take_photo')");
  expect(view).toContain('name="evidence_captured_at"');
  expect(view).toContain("placeholder=\"{{ trans('store_logistics.observations') }}\" required");
  expect(view).not.toContain('Resultado de la visita *');
  expect(controller).toContain('abs(time()-$capturedAt)>600');
  expect(controller).toContain("TranslationService::trans('store_logistics.delivery_live_photos_required')");
});
