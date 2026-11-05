const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '../..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');

test('Ophytrack is locked to Portuguese without an English translation fallback', async () => {
  const service = read('src/Services/TranslationService.php');
  expect(service).toMatch(/if \(ProductProfileService::isOphytrack\(\)\) \{\r?\n\s+self::setLocale\('pt'\);/);
  expect(service).toContain('!ProductProfileService::isOphytrack()');

  const flatten = (value, prefix = '', output = {}) => {
    for (const [key, item] of Object.entries(value)) {
      const name = prefix ? `${prefix}.${key}` : key;
      if (item && typeof item === 'object' && !Array.isArray(item)) flatten(item, name, output);
      else output[name] = item;
    }
    return output;
  };
  const english = flatten(JSON.parse(read('src/Languages/en.json')));
  const portuguese = flatten(JSON.parse(read('src/Languages/pt.json')));
  expect(Object.keys(english).filter(key => !(key in portuguese))).toEqual([]);
});

test('carrier collection remains open for continuous scans and uses pt-BR', async () => {
  const driver = read('src/views/panel/level4/planner-hub/team/driver-mode/index.twig');
  const detailedBranch = driver.slice(
    driver.indexOf("if (isCarrierOrganization && document.getElementById('detailedCollection'))"),
    driver.indexOf("if (isCarrierOrganization && String(decodedText||'').trim())")
  );

  expect(detailedBranch).toContain('stageCollectionToken');
  expect(detailedBranch).toContain('setTimeout(resumePackageScanner');
  expect(detailedBranch).not.toContain("getInstance(document.getElementById('packageScannerModal'))?.hide()");
  expect(driver).toContain("new Intl.DateTimeFormat('pt-BR'");
  expect(driver).not.toContain("new Intl.DateTimeFormat('es-ES'");
  expect(driver).toContain('pendente de confirmação');
});

test('manifest accepts persisted address and CEP without requiring a duplicate recipient entry', async () => {
  const manifest = read('src/Repositories/DeliveryManifestRepository.php');
  expect(manifest).toContain("trim((string)$intake->shipping_address_1)===''||trim((string)$intake->shipping_zip)===''");
  expect(manifest).not.toContain("trim((string)$intake->guest_name)==='Datos del destinatario pendientes'");
  expect(manifest).toContain('Complete o endereço e o CEP');
});

test('corrected carrier workflow has no Spanish operational messages', async () => {
  const combined = [
    read('src/views/panel/level4/planner-hub/team/driver-mode/index.twig'),
    read('src/views/panel/level4/planner-hub/team/driver-mode/index.php'),
    read('src/Repositories/DeliveryManifestRepository.php'),
    read('src/Repositories/CarrierPackageRepository.php'),
  ].join('\n');
  for (const phrase of [
    'No se encontró', 'Paquete recolectado', 'Escanea al menos', 'Manifiesto de salida',
    'Recolección detallada', 'Tipo de lectura', 'pendiente de confirmar', 'La colección',
  ]) expect(combined).not.toContain(phrase);
});
