const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const source = fs.readFileSync(path.resolve(__dirname, '../../src/views/panel/level4/planner-hub/team/driver-mode/index.twig'), 'utf8');

test('detailed collection scanner shows only the live QR reader', async () => {
  expect(source).toContain("carrierView == 'collection' and showCarrierDetails and carrierStage == 'collected'");
  expect(source).toContain('data-collection-scanner-only="{{ collectionScannerOnly ? \'1\' : \'0\' }}"');
  expect(source).toContain("{% if not collectionScannerOnly and not (isCarrierOrganization and carrierView == 'deliveries') %}");
  expect(source).toContain("{% if not collectionScannerOnly and isCarrierOrganization and carrierView != 'deliveries' %}");
  expect(source).toContain('<div id="packageQrReader" style="width:100%"></div>');
});
