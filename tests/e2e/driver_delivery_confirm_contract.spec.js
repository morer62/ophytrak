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
  expect(view).toContain("delivery_live_photos_required");
  expect(view).not.toContain("carrier-manual-claim-form");
  expect(view).not.toContain("carrier_fallback.title");
  expect(controller).toContain("if($photo===''||$photo2==='')");
  expect(controller).toContain("abs(time()-$capturedAt)>600");
  expect(controller).toContain("$meta['evidence_captured_at']=$capturedAt");
});

test('Ophytrack is Portuguese-only and hides language selectors', async () => {
  const service = read('src/Services/TranslationService.php');
  const header = read('src/views/templates/layout/headerLayout.html.twig');
  const normalizedHeader = header.replace(/\r\n/g, '\n');
  const publicSelector = read('src/views/templates/components/public-language-selector.twig');

  expect(service).toContain("if (ProductProfileService::isOphytrack())");
  expect(service).toContain("self::setLocale('pt')");
  expect(service).toContain("ProductProfileService::isOphytrack() ? ['pt'] : self::SUPPORTED_LOCALES");
  expect(normalizedHeader).toContain("{% set _current_locale = locale is defined ? locale : 'en' %}\n      {% if _locales|length > 1 %}\n      <li class=\"nav-item dropdown\" style=\"display: block !important;\">\n        <a class=\"nav-icon dropdown-toggle\" href=\"#\" id=\"languageDropdownHeader\"");
  expect(header).toContain('id="alertsDropdown"');
  expect(publicSelector).toContain('{% if _public_locales|length > 1 %}');
});
