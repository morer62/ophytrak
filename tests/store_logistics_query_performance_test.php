<?php
require __DIR__.'/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__.'/..')->safeLoad();
$pdo=new PDO($_ENV['DATABASE_URL']);$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->beginTransaction();
try{
  $stmt=$pdo->prepare("INSERT INTO store_orders (id_owner,guest_name,guest_email,total,payment_status,status,public_token,created_at) VALUES (1450,'QA Load',:email,1.00,'PENDING','NEW',:token,NOW())");
  for($i=0;$i<1000;$i++)$stmt->execute([':email'=>"qa.load.{$i}@example.test",':token'=>bin2hex(random_bytes(24))]);
  $start=microtime(true);$q=$pdo->prepare("SELECT * FROM store_orders WHERE id_owner=:owner AND created_at BETWEEN :from AND :to ORDER BY created_at DESC LIMIT 300");$q->execute([':owner'=>1450,':from'=>date('Y-m-d H:i:s',strtotime('-1 day')),':to'=>date('Y-m-d H:i:s',strtotime('+1 day'))]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);$elapsed=microtime(true)-$start;
  if(count($rows)!==300||$elapsed>1.0)throw new RuntimeException('Store order query performance regression: '.count($rows).' rows in '.$elapsed.'s');
  echo 'Store logistics query performance OK: '.number_format($elapsed*1000,2).' ms for 300/1000 rows'.PHP_EOL;
}finally{$pdo->rollBack();}
