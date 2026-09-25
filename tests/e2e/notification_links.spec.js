const {test,expect}=require('@playwright/test');
const fs=require('fs');
const path=require('path');

test('notification links are normalized centrally for every account level',async()=>{
  const root=path.resolve(__dirname,'../..');
  const repository=fs.readFileSync(path.join(root,'src/Repositories/NotificationsRepository.php'),'utf8');
  expect(repository).toContain('use App\\Utils\\LocationUtils;');
  expect(repository).toContain('$this->normalizeLinks($notifications);');
  expect(repository).toContain("LocationUtils::pathFor(ltrim($link, '/'))");
  expect(repository).toContain("preg_match('~^(?:https?:)?//~i', $link)");
});

test('all notification surfaces use the centrally normalized link',async()=>{
  const root=path.resolve(__dirname,'../..');
  const files=[
    'src/views/templates/layout/headerLayout.html.twig',
    'src/views/panel/notifications/index.twig',
    'src/views/panel/level1/notifications/index.twig',
    'src/views/panel/level2/notifications/index.twig',
    'src/views/panel/level3/notifications/index.twig',
    'src/views/panel/level4/notifications/index.twig',
    'src/views/panel/level5/notifications/index.twig'
  ];
  for(const file of files){
    const source=fs.readFileSync(path.join(root,file),'utf8');
    expect(source).toMatch(/notification\.link|n\.link/);
    expect(source).toContain('America/Sao_Paulo');
  }
});

test('carrier scans notify the seller in Spanish at all three intake stages',async()=>{
  const root=path.resolve(__dirname,'../..');
  const repository=fs.readFileSync(path.join(root,'src/Repositories/CarrierPackageRepository.php'),'utf8');
  expect(repository).toContain('La transportadora recolectó el paquete ');
  expect(repository).toContain(' fue recibido en el almacén de la transportadora.');
  expect(repository).toContain(' fue agregado al manifiesto de entrega.');
  expect(repository.match(/\$this->notify\(/g).length).toBeGreaterThanOrEqual(3);
});
