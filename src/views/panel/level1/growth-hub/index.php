<?php

use App\Services\GrowthHubService;
use App\Services\LoginService;
use App\Services\SearchConsoleImportService;
use App\Services\SerpResearchService;
use App\Utils\JsonResponse;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $siteKey = trim((string)($_GET['site_key'] ?? ($_ENV['SEO_AGENT_DEFAULT_SITE_KEY'] ?? 'vnvevents')));
    $service = new GrowthHubService();

    return TemplateResponse::render(__DIR__ . '/index.twig', [
        'growthHub' => $service->dashboard(LoginService::getSession(), $siteKey, $_GET),
    ]);
});

$router->post(function () {
    $user = LoginService::getSession();
    if (!$user || (int)$user->getLevel() !== 1) {
        MessageUtil::setMessage('Only level 1 admins can manage Growth Hub.');
        LocationUtils::redirectInternal('panel/home');
    }

    $service = new GrowthHubService();
    $action = (string)($_POST['action'] ?? '');
    $siteKey = trim((string)($_POST['site_key'] ?? 'vnvevents'));

    try {
        if ($action === 'generate_title_ideas') {
            return JsonResponse::createResponse($service->generateTitleIdeas($user, $_POST));
        } elseif ($action === 'create_draft') {
            $contentId = $service->createDraft($user, $_POST);
            MessageUtil::setMessage('Growth Hub draft created. It is not public until approved and published.');
            LocationUtils::redirectInternal('panel/growth-hub?site_key=' . urlencode($siteKey) . '&step=3&preview_content_id=' . $contentId . '#article-preview');
        } elseif ($action === 'update_site_settings') {
            $service->updateSiteSettings($user, $_POST);
            MessageUtil::setMessage('Growth Hub site URL settings updated.');
        } elseif ($action === 'refresh_receiver_sitemap') {
            $service->refreshReceiverSitemap($user, $_POST);
            MessageUtil::setMessage('Receiver sitemap refresh was queued/executed according to this site environment.');
        } elseif ($action === 'update_site_offers') {
            $service->updateSiteOffers($user, $_POST);
            MessageUtil::setMessage('Growth Hub services and offers updated for Opportunity Discovery.');
        } elseif ($action === 'update_template_css') {
            $service->updateTemplateCss($user, $_POST);
            MessageUtil::setMessage('CMS template CSS updated for this brand.');
        } elseif ($action === 'create_template') {
            $service->createTemplate($user, $_POST);
            MessageUtil::setMessage('CMS template added. You can select it when creating a page.');
        } elseif ($action === 'create_planned_draft') {
            $contentId = $service->createPlannedDraft($user, $_POST);
            if (!empty($_POST['as_json'])) {
                return JsonResponse::createResponse([
                    'id_content' => $contentId,
                    'preview_url' => '/panel/growth-hub?site_key=' . urlencode($siteKey) . '&step=3&preview_content_id=' . $contentId . '#article-preview',
                ]);
            }
            MessageUtil::setMessage('Page created. Review it in Pages & articles, then publish it when ready.');
            LocationUtils::redirectInternal('panel/growth-hub?site_key=' . urlencode($siteKey) . '&step=3&preview_content_id=' . $contentId . '#article-preview');
        } elseif ($action === 'update_content') {
            $service->updateContent($user, $_POST);
            MessageUtil::setMessage('Growth Hub content updated.');
        } elseif ($action === 'approve_publish_content') {
            $service->approveAndPublishContent($user, $_POST);
            MessageUtil::setMessage('Content approved and published. The public API can now serve it.');
        } elseif ($action === 'archive_content') {
            $service->archiveContent($user, $_POST);
            MessageUtil::setMessage('Content removed from active inventory and public routes.');
        } elseif ($action === 'create_opportunity') {
            $service->createOpportunity($user, $_POST);
            MessageUtil::setMessage('Growth opportunity saved for this brand.');
        } elseif ($action === 'dismiss_opportunity') {
            $service->dismissOpportunity($user, $_POST);
            MessageUtil::setMessage('Growth opportunity dismissed.');
        } elseif ($action === 'upload_media') {
            $service->uploadMedia($user, $siteKey, $_FILES['media_file'] ?? [], $_POST);
            MessageUtil::setMessage('Image uploaded to Cloudinary and registered in CMS media.');
        } elseif ($action === 'create_location') {
            $service->createTargetLocation($user, $_POST);
            MessageUtil::setMessage('Target location registered for local SEO tracking.');
        } elseif ($action === 'create_competitor') {
            $service->createCompetitor($user, $_POST);
            MessageUtil::setMessage('Competitor registered for SERP monitoring.');
        } elseif ($action === 'generate_competitor_ai_opportunities') {
            $count = $service->createCompetitorAiOpportunities($user, $_POST);
            MessageUtil::setMessage($count . ' competitor-backed opportunities generated. Use these while SERP validation is unavailable or incomplete.');
        } elseif ($action === 'create_keyword') {
            $service->createKeyword($user, $_POST);
            MessageUtil::setMessage('Keyword registered for Search Console, Trends and SERP monitoring.');
        } elseif ($action === 'import_search_console') {
            $importer = new SearchConsoleImportService();
            $summary = $importer->importRequiredProperties((int)$user->getOwner());
            $connected = 0;
            $failed = 0;
            $rows = 0;
            foreach ($summary['properties'] as $property) {
                $connected += !empty($property['connected']) ? 1 : 0;
                $failed += empty($property['connected']) ? 1 : 0;
                $rows += (int)($property['rows_imported'] ?? 0);
            }

            MessageUtil::setMessage(
                'Search Console checked '
                . count($summary['properties'])
                . ' properties: '
                . $connected
                . ' connected, '
                . $failed
                . ' unavailable, '
                . $rows
                . ' rows imported from '
                . $summary['date_from']
                . ' to '
                . $summary['date_to']
                . '.'
            );
        } elseif ($action === 'run_serp_research') {
            $serp = new SerpResearchService();
            $summary = $serp->research(
                $siteKey,
                trim((string)($_POST['keyword_text'] ?? '')),
                trim((string)($_POST['location_name'] ?? '')) ?: null,
                trim((string)($_POST['language_code'] ?? '')) ?: null,
                trim((string)($_POST['device'] ?? '')) ?: null,
                !empty($_POST['depth']) ? (int)$_POST['depth'] : null
            );
            MessageUtil::setMessage('SERP research completed: task created, results fetched and ' . $summary['rows_saved'] . ' top results saved.');
        } else {
            MessageUtil::setMessage('Invalid Growth Hub action.');
        }
    } catch (Throwable $e) {
        error_log('GrowthHub level1 action failed: ' . $e->getMessage());
        if ($action === 'generate_title_ideas' || !empty($_POST['as_json'])) {
            return JsonResponse::createResponse(['error' => $e->getMessage()], 422);
        }
        MessageUtil::setMessage('Growth Hub action failed: ' . $e->getMessage());
    }

    $redirect = 'panel/growth-hub?site_key=' . urlencode($siteKey);
    if (!empty($_POST['step'])) {
        $redirect .= '&step=' . urlencode((string)$_POST['step']);
    }
    if (!empty($_POST['analysis_service'])) {
        $redirect .= (strpos($redirect, '&step=') === false ? '&step=1' : '') . '&analysis_service=' . urlencode((string)$_POST['analysis_service']);
    }
    if (!empty($_POST['analysis_competitor'])) {
        $redirect .= (strpos($redirect, '&step=') === false ? '&step=1' : '') . '&analysis_competitor=' . urlencode((string)$_POST['analysis_competitor']);
    }
    if (!empty($_POST['analysis_location'])) {
        $redirect .= '&analysis_location=' . urlencode((string)$_POST['analysis_location']);
    }
    LocationUtils::redirectInternal($redirect);
});

$router->run();
