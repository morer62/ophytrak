const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const source = fs.readFileSync(path.resolve(__dirname,
  '../../src/views/panel/level4/planner-hub/team/driver-mode/index.twig'), 'utf8');
const token = '2547d8897b0aff4a28404009601421f629e5c127b0219baf';
const functionSource = (start, end) => source.slice(source.indexOf(start), source.indexOf(end))
  .replace(/{{\s*trans\('([^']+)'\)\|json_encode\|raw\s*}}/g, (_, key) => JSON.stringify(key));

// Execute the actual scanner functions from Twig, not a duplicate implementation.
function scanner({ carrier = true, area = '', row = null, reject = false } = {}) {
  const calls = [], elements = area ? { [area]: {} } : {};
  const modal = { hide: () => calls.push(['hide']), show: () => calls.push(['showTask']) };
  const context = vm.createContext({
    URL, FormData, String, Array, Promise,
    window: { location: { origin: 'https://ophytrack.com', href: 'https://ophytrack.com/panel/planner-hub/team/driver-mode?view=collection' } },
    document: {
      getElementById: id => elements[id] || (id.startsWith('driverTaskModal') ? modal : null),
      querySelectorAll: () => row ? [row] : [],
    },
    bootstrap: { Modal: { getInstance: () => null, getOrCreateInstance: () => modal } },
    isCarrierOrganization: carrier, packageScanBusy: false, packageScanner: { pause: () => calls.push(['pause']) },
    assignedPackageTokens: {},
    showPackageScanFeedback: (message, type) => calls.push(['feedback', message, type]),
    resumePackageScanner: () => calls.push(['resume']),
    stageCollectionToken: value => calls.push(['collection', value]),
    stageAgentChangeToken: value => calls.push(['agentChange', value]),
    packageScanPosition: async () => null,
    incrementReceptionCounter: () => calls.push(['reception']),
    setTimeout: () => {},
    fetch: async (url, options) => {
      calls.push(['request', options.body.get('action'), options.body.get('qr_token'), options.body.get('workflow_context')]);
      return { ok: !reject, json: async () => ({ success: !reject, message: reject ? 'Seller not associated' : 'OK', custody_status: 'RECEIVED_AT_HUB' }) };
    },
  });
  vm.runInContext(functionSource('function scannedPackageKind(', 'function showPackageScanFeedback(')
    + functionSource('async function claimCarrierPackage(', 'const collectionTokens=')
    + functionSource('async function openAssignedPackageFromQr(', "document.getElementById('packageScannerModal').addEventListener('shown.bs.modal'"), context);
  return { context, calls };
}

test('raw label tokens and order-access URLs resolve to the same token', () => {
  const { context } = scanner();
  for (const value of [token, `  ${token}\n`, `https://ophytrack.com/store/order-access?token=${token}`, `/store/order-access?token=${token}`]) {
    expect(context.extractPackageToken(value)).toBe(token);
  }
  for (const value of ['', 'https://ophytrack.com/', 'not a valid label', '/store/order-access']) {
    expect(context.extractPackageToken(value)).toBe('');
  }
});

test('detailed collection stages a raw QR without requiring a seller DELIVERY assignment', async () => {
  const { context, calls } = scanner({ area: 'detailedCollection' });
  await context.openAssignedPackageFromQr(token);
  expect(calls).toContainEqual(['collection', token]);
  expect(calls.some(call => call[0] === 'request' || call[1] === 'store_logistics.qr_not_assigned')).toBe(false);
});

for (const [area, workflow] of [['physicalReception', 'physical_reception'], ['departureManifest', 'departure_manifest'], ['', null]]) {
  test(`carrier QR reaches server validation in ${area || 'deliveries'}`, async () => {
    const { context, calls } = scanner({ area, row: { dataset: { publicToken: token, taskId: '1' } } });
    await context.openAssignedPackageFromQr(token);
    expect(calls).toContainEqual(['request', 'carrier_qr_claim', token, workflow]);
    expect(calls).not.toContainEqual(['showTask']);
  });
}

test('agent change stages its QR and inventory labels never claim custody', async () => {
  const agent = scanner({ area: 'agentChange' });
  await agent.context.openAssignedPackageFromQr(token);
  expect(agent.calls).toContainEqual(['agentChange', token]);
  const storage = scanner({ area: 'detailedCollection' });
  await storage.context.openAssignedPackageFromQr(`https://ophytrack.com/panel/planner-hub/team/storage/item?token=${token}`);
  expect(storage.calls).toEqual([['feedback', 'store_logistics.qr_inventory_label', 'warning']]);
});

test('server custody rejection is shown and the scanner resumes', async () => {
  const { context, calls } = scanner({ reject: true });
  await context.openAssignedPackageFromQr(token);
  expect(calls).toContainEqual(['feedback', 'Seller not associated', 'danger']);
  expect(calls).toContainEqual(['resume']);
});

test('seller drivers still cannot open unassigned orders and assigned raw tokens open their task', async () => {
  const foreign = scanner({ carrier: false });
  await foreign.context.openAssignedPackageFromQr(token);
  expect(foreign.calls).toEqual([['feedback', 'store_logistics.qr_not_assigned', 'danger']]);
  const assigned = scanner({ carrier: false, row: { dataset: { publicToken: token, taskId: '1' } } });
  await assigned.context.openAssignedPackageFromQr(token);
  expect(assigned.calls).toEqual([['showTask']]);
});
