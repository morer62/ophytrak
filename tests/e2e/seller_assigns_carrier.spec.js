const {test,expect}=require('@playwright/test');
const fs=require('fs');const path=require('path');
test('seller cannot manually assign a carrier organization',async()=>{const root=path.resolve(__dirname,'../..');const repo=fs.readFileSync(path.join(root,'src/Repositories/CarrierPackageRepository.php'),'utf8');expect(repo).toContain('Carrier assignment is completed only when an authorized carrier scans the secure QR.');expect(repo).toContain('isAssociated((int)$package->id_owner,$carrierOwner)');});
