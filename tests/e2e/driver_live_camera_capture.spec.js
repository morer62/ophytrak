const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '../..');
const read = relative => fs.readFileSync(path.join(root, relative), 'utf8');

test('occurrence evidence is generated only by the live camera', async () => {
  const view = read('src/views/panel/level4/planner-hub/team/driver-mode/index.twig');
  const controller = read('src/views/panel/level4/planner-hub/team/driver-mode/index.php');
  const es = JSON.parse(read('src/languages/es.json'));
  const pt = JSON.parse(read('src/languages/pt.json'));

  expect(es.store_logistics.occurrence_capture_button).toBe('Tirar Foto');
  expect(pt.store_logistics.occurrence_capture_button).toBe('Tirar Foto');
  expect(view).toContain("getUserMedia({video:{facingMode:{ideal:'environment'}}");
  expect(view).toContain("new File([blob],'occurrence-live-");
  expect(view).toContain('const transfer=new DataTransfer()');
  expect(view).not.toContain('if(next)next.click()');
  expect(view).toContain('name="occurrence_photo_1" accept="image/jpeg" hidden');
  expect(view).toContain('name="occurrence_photo_2" accept="image/jpeg" hidden');
  expect(controller).toContain("abs(time()-$capturedAt)>600");
  expect(controller).toContain('Las dos fotos deben tomarse con la cámara');
});

test('driver profile photo is generated only by the live camera', async () => {
  const view = read('src/views/panel/level4/settings/index.twig');
  const controller = read('src/views/panel/level4/settings/index.php');
  const pt = JSON.parse(read('src/languages/pt.json'));

  expect(pt.ophytrack_driver_profile.take_photo).toBe('Tirar Foto');
  expect(view).toContain('id="driverProfilePhotoCapture"');
  expect(view).not.toContain('label for="driverProfilePhoto"');
  expect(view).toContain("getUserMedia({video:{facingMode:{ideal:'user'}}");
  expect(view).toContain("new File([blob], 'driver-profile-live.jpg'");
  expect(view).toContain("name=\"profile_photo_captured_at\"");
  expect(view).not.toMatch(/name="profile_photo"[^>]*capture=/);
  expect(controller).toContain("$_POST['profile_photo_captured_at']");
  expect(controller).toContain('abs(time() - $capturedAt) > 600');
});
