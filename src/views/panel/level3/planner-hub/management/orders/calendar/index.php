<?php

use App\Repositories\OrdersRepository;
use App\Repositories\UserRepository;
use App\Services\LoginService;
use App\Services\OrdersCalendarService;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(callback: function () {
    $user = LoginService::getSession();
    $week = $_GET['week'] ?? null;
    $status = $_GET['status'] ?? 'all';

    $calendarService = new OrdersCalendarService();
    [$weekStart, $weekEnd] = $calendarService->getWeekBounds($week);

    $ordersRepo = new OrdersRepository();
    $clientRepo = new UserRepository();

    $orders = $ordersRepo->getFiltered2([
        ...LoginService::getUserIdAsArray(true),
        'is_archived' => 0,
    ], null, $weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d'));

    $clients = $clientRepo->getAllAssociatedClients($user->getOwner());
    $calendar = $calendarService->buildWeek($orders, $clients, $weekStart->format('Y-m-d'), $status);

    return TemplateResponse::render(realpath(__DIR__ . '/../../../../../shared/orders-calendar/index.twig'), [
        'calendar' => $calendar,
        'list_route' => '/panel/planner-hub/management/orders/orders',
        'calendar_route' => '/panel/planner-hub/management/orders/calendar',
    ]);
});
