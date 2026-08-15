<?php


use App\Services\ApiAuthService;
use App\Utils\JsonResponse;
use App\Utils\Request;
use App\Utils\RouterApi;

$router = new RouterApi();
$router->post(function (Request $request) {

    $payload = ApiAuthService::bodyFromJsonOrPost($request);
    $user = ApiAuthService::getAuthenticatedUser($request, $payload);

    if (!$user) {
        return JsonResponse::createResponse([
            "success" => false,
            "message" => "Unauthorized"
        ], 401);
    }

    return JsonResponse::createResponse([
        "success" => false,
        "message" => "Level changes are no longer destructive. Use company/workspace context switching or create a business account through web signup.",
        "user" => ApiAuthService::userPayload($user),
    ], 409);
});


$router->run();
