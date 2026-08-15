<?php

use App\Services\LoginService;
use App\Services\TranslationService;
use App\Repositories\MusicSessionRepository;
use App\Repositories\MusicSessionsCategoryRepository;
use App\Repositories\MusicSessionsKeywordRepository;
use App\Repositories\MusicSessionsKeywordsRelationsRepository;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;
use App\Utils\UserContext;

$router = new Router();

$router->get(function () {
    $context = UserContext::get();
    $user = LoginService::getSession();
    $sessionRepo = new MusicSessionRepository();
    $categoryRepo = new MusicSessionsCategoryRepository();
    $keywordRepo = new MusicSessionsKeywordRepository();

    $id = $_GET["id"] ?? null;
    if (!$id) {
        TranslationService::detectLocale();
        MessageUtil::setMessage(TranslationService::trans('planner_hub.invalid_session_id'));
        LocationUtils::redirectInternal("panel/multimedia-sessions");
    }

    $userId = $user->getId();
    $session = $sessionRepo->getOneWithCategory($id, $userId);

    if (!$session) {
        TranslationService::detectLocale();
        MessageUtil::setMessage(TranslationService::trans('planner_hub.session_not_found'));
        LocationUtils::redirectInternal("panel/multimedia-sessions");
    }

    $categories = $categoryRepo->getAllByUser($userId);
    $keywords = $keywordRepo->getAllByUser($userId);

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        ...$context,
        "session" => $session,
        "categories" => $categories,
        "keywords" => $keywords
    ]);
});

$router->post(function () {
    $user = LoginService::getSession();
    $sessionRepo = new MusicSessionRepository();
    $keywordRepo = new MusicSessionsKeywordRepository();
    $keywordRelationsRepo = new MusicSessionsKeywordsRelationsRepository();

    $id = $_POST["id"] ?? null;
    $title = trim($_POST["title"] ?? '');
    $description = trim($_POST["description"] ?? '');
    $url = trim($_POST["url"] ?? '');
    $platform = trim($_POST["platform"] ?? '');
    $idCategory = !empty($_POST["id_category"]) ? (int)$_POST["id_category"] : null;
    $isActive = isset($_POST["is_active"]) ? 1 : 0;

    if (!$id || empty($title) || empty($url) || empty($platform)) {
        TranslationService::detectLocale();
        MessageUtil::setMessage(TranslationService::trans('planner_hub.title_url_platform_required'));
        LocationUtils::reload();
    }

    $embedCode = $sessionRepo->generateEmbedCode($url, $platform);

    $data = [
        "title" => $title,
        "description" => $description ?: null,
        "url" => $url,
        "platform" => $platform,
        "embed_code" => $embedCode ?: null,
        "id_category" => $idCategory,
        "is_active" => $isActive
    ];

    if ($sessionRepo->update($data, ["id" => $id])) {
        $keywordIds = [];
        $userId = $user->getId();
        
        if (!empty($_POST["keywords"])) {
            if (is_array($_POST["keywords"])) {
                foreach ($_POST["keywords"] as $keywordId) {
                    $keywordIds[] = (int)$keywordId;
                }
            } else {
                $keywordIds[] = (int)$_POST["keywords"];
            }
        }
        
        if (!empty($_POST["new_keywords"])) {
            $newKeywords = explode(',', $_POST["new_keywords"]);
            foreach ($newKeywords as $keyword) {
                $keyword = trim($keyword);
                if (!empty($keyword)) {
                    $keywordId = $keywordRepo->getOrCreate($keyword, $userId);
                    if ($keywordId > 0) {
                        $keywordIds[] = $keywordId;
                    }
                }
            }
        }
        
        $keywordRelationsRepo->setSessionKeywords($id, $keywordIds);
        
        TranslationService::detectLocale();
        MessageUtil::setMessage(TranslationService::trans('planner_hub.multimedia_session_updated_successfully'));
        LocationUtils::redirectInternal("panel/multimedia-sessions");
    } else {
        TranslationService::detectLocale();
        MessageUtil::setMessage(TranslationService::trans('planner_hub.error_updating_multimedia_session'));
        LocationUtils::reload();
    }
});

$router->run();

