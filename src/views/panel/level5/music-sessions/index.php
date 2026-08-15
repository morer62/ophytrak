<?php

use App\Utils\LocationUtils;
use App\Utils\Router;

$router = new Router();

$router->get(function (): void {
    LocationUtils::redirectInternal("panel/home");
});

$router->run();
