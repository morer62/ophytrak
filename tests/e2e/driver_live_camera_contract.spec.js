const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

test('delivery close requires complete receiver data and a live camera capture', async () => {
  const root = path.resolve(__dirname, '../..');
  const view = fs.readFileSync(path.join(root, 'src/views/panel/level4/planner-hub/team/driver-mode/index.twig'), 'utf8');
  const controller = fs.readFileSync(path.join(root, 'src/views/panel/level4/planner-hub/team/driver-mode/index.php'), 'utf8');
  expect(view).toContain('delivery-live-camera');
  expect(view).toContain('navigator.mediaDevices.getUserMedia');
  expect(view).toContain('No se permite seleccionar imágenes de la galería.');
  expect(view).toContain('name="evidence_captured_at"');
  expect(view).toContain('placeholder="Observaciones obligatorias" required');
  expect(controller).toContain('$captureAge>600');
  expect(controller).toContain('La foto de evidencia debe tomarse con la cámara al momento de la entrega.');
});
