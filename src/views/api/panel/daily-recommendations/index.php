<?php

use App\Entity\User;
use App\Services\ApiAuthService;
use App\Services\BusinessEvaluatorService;
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
            'message' => 'Debes iniciar sesión para ver las recomendaciones.',
        ], 401);
    }

    $idOwner = $user->getOwner();
    if ($idOwner === null) {
        return JsonResponse::createResponse([
            'success' => false,
            'message' => 'Usuario sin owner asociado.',
        ], 403);
    }

    $query = $request->getQueryParams();
    $dateParam = $query['date'] ?? null;
    $type = $query['type'] ?? null;

    if ($type === 'monthly_snapshot') {
        $year = (int)($query['year'] ?? date('Y'));
        $month = (int)($query['month'] ?? date('n'));
        $service = new BusinessEvaluatorService();
        $snapshot = $service->getOrCreateMonthlySnapshot($idOwner, $year, $month);

        if ($snapshot === null) {
            return JsonResponse::createResponse([
                'success' => false,
                'message' => 'No se pudo obtener o generar el snapshot mensual.',
            ], 500);
        }

        $metrics = $snapshot->metrics_snapshot ?? null;
        if (is_string($metrics)) {
            $metrics = json_decode($metrics, true);
        }

        return JsonResponse::createResponse([
            'success' => true,
            'monthly_snapshot' => [
                'id' => (int)$snapshot->id,
                'snapshot_year' => (int)$snapshot->snapshot_year,
                'snapshot_month' => (int)$snapshot->snapshot_month,
                'snapshot_text' => $snapshot->snapshot_text,
                'recommended_action' => $snapshot->recommended_action ?? null,
                'status' => $snapshot->status ?? null,
                'primary_module_slug' => $snapshot->primary_module_slug ?? null,
                'action_label' => $snapshot->action_label ?? null,
                'action_url' => $snapshot->action_url ?? null,
                'source_type' => $snapshot->source_type ?? null,
                'metrics_snapshot' => $metrics,
                'generated_at' => $snapshot->generated_at ?? null,
                'created_at' => $snapshot->created_at ?? null,
            ],
        ], 200);
    }

    if ($dateParam !== null && $dateParam !== '') {
        $date = $dateParam === 'today' ? date('Y-m-d') : trim($dateParam);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        $service = new BusinessEvaluatorService();
        $recommendation = $service->getOrCreateRecommendationForDate($idOwner, $date);
        if ($recommendation === null) {
            return JsonResponse::createResponse([
                'success' => false,
                'message' => 'No se pudo obtener o generar la recomendación.',
            ], 500);
        }
        $metrics = $recommendation->metrics_snapshot ?? null;
        if (is_string($metrics)) {
            $metrics = json_decode($metrics, true);
        }
        return JsonResponse::createResponse([
            'success' => true,
            'recommendation' => [
                'id' => (int) $recommendation->id,
                'recommendation_date' => $recommendation->recommendation_date,
                'recommendation_text' => $recommendation->recommendation_text,
                'recommendation_type' => $recommendation->recommendation_type ?? 'daily_insight',
                'status' => $recommendation->status ?? null,
                'module_slug' => $recommendation->module_slug ?? null,
                'action_label' => $recommendation->action_label ?? null,
                'action_url' => $recommendation->action_url ?? null,
                'source_type' => $recommendation->source_type ?? null,
                'metrics_snapshot' => $metrics,
                'created_at' => $recommendation->created_at ?? null,
            ],
        ], 200);
    }

    $limit = (int) ($query['limit'] ?? 30);
    $limit = min(max(1, $limit), 100);

    $service = new BusinessEvaluatorService();
    $list = $service->getRecommendationsByOwner($idOwner, $limit);

    $items = array_map(static function ($row) {
        return [
            'id' => (int) $row->id,
            'recommendation_date' => $row->recommendation_date,
            'recommendation_text' => $row->recommendation_text,
            'recommendation_type' => $row->recommendation_type ?? 'daily_insight',
            'status' => $row->status ?? null,
            'module_slug' => $row->module_slug ?? null,
            'action_label' => $row->action_label ?? null,
            'action_url' => $row->action_url ?? null,
            'source_type' => $row->source_type ?? null,
            'metrics_snapshot' => is_string($row->metrics_snapshot)
                ? json_decode($row->metrics_snapshot, true)
                : $row->metrics_snapshot,
            'created_at' => $row->created_at ?? null,
        ];
    }, $list);

    return JsonResponse::createResponse([
        'success' => true,
        'recommendations' => $items,
    ], 200);
});

$router->post(function (Request $request) {
    $user = ApiAuthService::getAuthenticatedUser($request);

    if (!$user instanceof User) {
        return JsonResponse::createResponse([
            'success' => false,
            'message' => 'Debes iniciar sesión para generar recomendaciones.',
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
    $date = $body['date'] ?? $_POST['date'] ?? date('Y-m-d');
    $date = is_string($date) ? trim($date) : date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }

    $service = new BusinessEvaluatorService();
    $recommendation = $service->getOrCreateRecommendationForDate($idOwner, $date);

    if ($recommendation === null) {
        return JsonResponse::createResponse([
            'success' => false,
            'message' => 'No se pudo generar o guardar la recomendación.',
        ], 500);
    }

    $metrics = $recommendation->metrics_snapshot ?? null;
    if (is_string($metrics)) {
        $metrics = json_decode($metrics, true);
    }

    return JsonResponse::createResponse([
        'success' => true,
        'recommendation' => [
            'id' => (int) $recommendation->id,
            'recommendation_date' => $recommendation->recommendation_date,
            'recommendation_text' => $recommendation->recommendation_text,
            'recommendation_type' => $recommendation->recommendation_type ?? 'daily_insight',
            'status' => $recommendation->status ?? null,
            'module_slug' => $recommendation->module_slug ?? null,
            'action_label' => $recommendation->action_label ?? null,
            'action_url' => $recommendation->action_url ?? null,
            'source_type' => $recommendation->source_type ?? null,
            'metrics_snapshot' => $metrics,
            'created_at' => $recommendation->created_at ?? null,
        ],
    ], 200);
});

$router->run();
