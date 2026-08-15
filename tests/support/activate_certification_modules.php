<?php
require __DIR__.'/../../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__.'/../../')->load();
$isLocal=str_contains(strtolower((string)($_ENV['APP_URL']??'')),'localhost')||str_contains((string)($_ENV['APP_URL']??''),'127.0.0.1');
if(strtolower((string)($_ENV['ENVIRONMENT']??'prod'))==='prod'&&!$isLocal){fwrite(STDERR,"Refusing certification mutation in production.\n");exit(2);}
$email=$argv[1]??'';$db=new App\Repositories\Connection();$db->query('SELECT id,id_owner FROM users WHERE email=:email LIMIT 1');$db->bind(':email',$email);$user=$db->fetchOne();if(!$user){fwrite(STDERR,"User not found.\n");exit(3);}$owner=(int)($user->id_owner?:$user->id);$repo=new App\Repositories\UserModulesRepository();foreach(['store_delivery_tracking','marketplace_connectors']as$slug){if(!$repo->activateModuleBySlug($owner,$slug,date('Y-m-d',strtotime('+12 months')))){fwrite(STDERR,"Could not activate {$slug}.\n");exit(4);}}echo $owner,PHP_EOL;
