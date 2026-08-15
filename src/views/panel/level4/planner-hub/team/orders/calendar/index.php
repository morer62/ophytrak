<?php

use App\Repositories\OrdersRepository;
use App\Repositories\UserRepository;
use App\Services\LoginService;
use App\Services\OrdersCalendarService;
use App\Services\UserWorkspaceContextService;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(callback: function () {
    $user = LoginService::getSession();
    $week = $_GET['week'] ?? null;
    $status = $_GET['status'] ?? 'all';

    $ordersRepo = new OrdersRepository();
    $clientRepo = new UserRepository();
    $workspaceContextService = new UserWorkspaceContextService();
    $calendarService = new OrdersCalendarService();

    $teamContext = $workspaceContextService->getTeamContext($user);
    $selectedOwnerId = (int)($teamContext['selectedOwnerId'] ?? 0);
    $orders = $selectedOwnerId > 0
        ? $ordersRepo->getOrdersByInvitationForOwner((int)$user->getId(), $selectedOwnerId)
        : $ordersRepo->getOrdersByInvitation((int)$user->getId());
    $clients = $clientRepo->getAllBy(['level' => 5]);

    $calendar = $calendarService->buildWeek(
        $orders,
        $clients,
        $week,
        $status,
        '/panel/planner-hub/team/orders/orders/tasks?id=',
        '/panel/planner-hub/team/orders/orders/tasks?id='
    );

    return TemplateResponse::render(realpath(__DIR__ . '/../../../../../shared/orders-calendar/index.twig'), [
        'calendar' => $calendar,
        'list_route' => '/panel/planner-hub/team/orders/orders',
        'calendar_route' => '/panel/planner-hub/team/orders/calendar',
        'calendar_level_label' => $teamContext['selectedInstitution']->company_name ?? 'Team orders',
    ]);
});
