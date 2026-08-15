<?php

use App\Entity\User;
use App\Repositories\BusinessEvaluatorSettingsRepository;
use App\Services\ApiAuthService;
use App\Services\LoginService;
use App\Utils\Cors;
use App\Utils\JsonResponse;
use App\Utils\Request;
use App\Utils\RouterApi;

Cors::handle();

$router = new RouterApi();

$router->get(function (Request $request) {
    $user = ApiAuthService::getAuthenticatedUser($request);

    if (!$user instanceof User) {
        return JsonResponse::createResponse([
            'success' => false,
            'message' => 'Debes iniciar sesión.',
        ], 401);
    }

    $idOwner = $user->getOwner();
    if ($idOwner === null) {
        return JsonResponse::createResponse([
            'success' => false,
            'message' => 'Usuario sin owner asociado.',
        ], 403);
    }

    $repo = new BusinessEvaluatorSettingsRepository();
    $settings = $repo->getByOwner($idOwner);

    if ($settings === null) {
        return JsonResponse::createResponse([
            'success' => true,
            'settings' => [
                'business_size' => null,
                'social_media' => [],
                'location' => null,
            ],
        ], 200);
    }

    $socialMedia = $settings->social_media ?? null;
    if (is_string($socialMedia)) {
        $socialMedia = json_decode($socialMedia, true);
    }
    if (!is_array($socialMedia)) {
        $socialMedia = [];
    }

    return JsonResponse::createResponse([
        'success' => true,
        'settings' => [
            'business_size' => $settings->business_size ?? null,
            'social_media' => $socialMedia,
            'location' => $settings->location ?? null,
        ],
    ], 200);
});

$router->put(function (Request $request) {
    $user = ApiAuthService::getAuthenticatedUser($request);

    if (!$user instanceof User) {
        return JsonResponse::createResponse([
            'success' => false,
            'message' => 'Debes iniciar sesión.',
        ], 401);
    }

    $idOwner = $user->getOwner();
    if ($idOwner === null) {
        return JsonResponse::createResponse([
            'success' => false,
            'message' => 'Usuario sin owner asociado.',
        ], 403);
    }

    $body = $request->getBody();
    if (!is_array($body)) {
        $body = [];
    }

    $data = [];
    if (array_key_exists('business_size', $body)) {
        $v = $body['business_size'];
        $allowed = [
            BusinessEvaluatorSettingsRepository::BUSINESS_SIZE_SMALL,
            BusinessEvaluatorSettingsRepository::BUSINESS_SIZE_MEDIUM,
            BusinessEvaluatorSettingsRepository::BUSINESS_SIZE_LARGE,
        ];
        $data['business_size'] = in_array($v, $allowed, true) ? $v : null;
    }
    if (array_key_exists('social_media', $body) && is_array($body['social_media'])) {
        $data['social_media'] = $body['social_media'];
    }
    if (array_key_exists('location', $body)) {
        $data['location'] = is_string($body['location']) ? trim($body['location']) : null;
    }

    $repo = new BusinessEvaluatorSettingsRepository();
    $ok = $repo->upsert($idOwner, $data);

    if (!$ok) {
        return JsonResponse::createResponse([
            'success' => false,
            'message' => 'No se pudo guardar la configuración.',
        ], 500);
    }

    $settings = $repo->getByOwner($idOwner);
    $socialMedia = $settings->social_media ?? null;
    if (is_string($socialMedia)) {
        $socialMedia = json_decode($socialMedia, true);
    }
    if (!is_array($socialMedia)) {
        $socialMedia = [];
    }

    return JsonResponse::createResponse([
        'success' => true,
        'settings' => [
            'business_size' => $settings->business_size ?? null,
            'social_media' => $socialMedia,
            'location' => $settings->location ?? null,
        ],
    ], 200);
});

$router->run();
