<?php

use App\Utils\Router;
use App\Utils\Response;

$router = new Router();

// Redirigir al buscador unificado con type=vendors
$router->get(function () {
    $queryString = $_SERVER['QUERY_STRING'] ?? '';
    $params = $queryString ? '?' . $queryString . '&type=vendors' : '?type=vendors';
    Response::redirect('/search/main' . $params);
});

try {
    $router->run();
} catch (Exception $e) {
    echo $e->getMessage();
}
