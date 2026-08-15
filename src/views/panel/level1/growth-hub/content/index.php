<?php

use App\Services\GrowthHubService;
use App\Services\LoginService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\TemplateResponse;
use App\Utils\Router;

$router = new Router();

$router->get(function () {
    $siteKey = trim((string)($_GET['site_key'] ?? ($_ENV['SEO_AGENT_DEFAULT_SITE_KEY'] ?? 'vnvevents')));
    $filters = [
        'status' => $_GET['status'] ?? '',
        'content_type' => $_GET['content_type'] ?? '',
        'q' => $_GET['q'] ?? '',
    ];

    $service = new GrowthHubService();

    return TemplateResponse::render(__DIR__ . '/index.twig', [
        'growthHub' => $service->contentInventory(LoginService::getSession(), $siteKey, $filters),
    ]);
});

$router->post(function () {
    $user = LoginService::getSession();
    if (!$user || (int)$user->getLevel() !== 1) {
        MessageUtil::setMessage('Only level 1 admins can publish Growth Hub content.');
        LocationUtils::redirectInternal('panel/home');
    }

    $siteKey = trim((string)($_POST['site_key'] ?? ($_ENV['SEO_AGENT_DEFAULT_SITE_KEY'] ?? 'vnvevents')));
    $action = (string)($_POST['action'] ?? '');

    try {
        $service = new GrowthHubService();
        if ($action === 'approve_publish_content') {
            $service->approveAndPublishContent($user, $_POST);
            MessageUtil::setMessage('Content approved and published. The public API can now serve it.');
        } elseif ($action === 'archive_content') {
            $service->archiveContent($user, $_POST);
            MessageUtil::setMessage('Content removed from active inventory and public routes.');
        } elseif ($action === 'restore_archived_content') {
            $service->restoreArchivedContent($user, $_POST);
            MessageUtil::setMessage('Archived content restored as a draft.');
        } elseif ($action === 'permanently_delete_content') {
            $service->permanentlyDeleteContent($user, $_POST);
            MessageUtil::setMessage('Archived content and its registered media were permanently deleted.');
        } else {
            MessageUtil::setMessage('Invalid Growth Hub content action.');
        }
    } catch (Throwable $e) {
        error_log('GrowthHub content action failed: ' . $e->getMessage());
        MessageUtil::setMessage('Growth Hub content action failed: ' . $e->getMessage());
    }

    $status = trim((string)($_POST['return_status'] ?? ''));
    $url = 'panel/growth-hub/content?site_key=' . urlencode($siteKey);
    if ($status !== '') {
        $url .= '&status=' . urlencode($status);
    }
    LocationUtils::redirectInternal($url);
});

$router->run();
