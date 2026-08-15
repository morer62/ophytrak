<?php

use App\Utils\LocationUtils;
use App\Utils\Router;

$router = new Router();

$router->get(function () {
    // Redirigir a la página completa de orders
    LocationUtils::redirectInternal("panel/planner-hub/orders/orders");
});

$router->run();
