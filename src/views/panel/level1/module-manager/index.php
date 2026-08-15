<?php

use App\Services\Level1MembershipOperationsService;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $service = new Level1MembershipOperationsService();
    $filters = [
        'search' => $_GET['search'] ?? '',
        'filter' => $_GET['filter'] ?? 'all',
        'module' => $_GET['module'] ?? '',
        'page' => $_GET['page'] ?? 1,
        'per_page' => $_GET['per_page'] ?? 25,
    ];
    $customers = $service->listCustomers($filters);

    return TemplateResponse::render(__DIR__ . '/index.twig', [
        'customers' => $customers,
        'filters' => $filters,
        'filterOptions' => [
            'all' => 'All customers',
            'active_membership' => 'Active membership',
            'expired_membership' => 'Expired membership',
            'due_soon' => 'Due soon',
            'past_due' => 'Past due',
            'failed_payment' => 'Failed payment',
            'has_payment_method' => 'Has payment method',
            'no_payment_method' => 'No payment method',
            'has_paid_modules' => 'Has paid modules',
            'courtesy_modules' => 'Courtesy modules',
            'no_modules' => 'No modules',
            'pending_cancellation' => 'Pending cancellation',
            'spam_suspected' => 'Spam suspected',
            'new_signups' => 'New signups',
            'email_not_verified' => 'Email not verified',
        ],
    ]);
});

try {
    $router->run();
} catch (Exception $e) {
    echo $e->getMessage();
}
