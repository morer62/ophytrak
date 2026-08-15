<?php

use App\Services\LoginService;
use App\Services\ModuleGuardService;
use App\Repositories\CrmCategoryRepository;
use App\Utils\TemplateResponse;

ModuleGuardService::requireModule('inventory_storage');

$user = LoginService::getSession();
$repo = new CrmCategoryRepository();

TemplateResponse::renderAndDisplay(__DIR__ . "/index.twig");
