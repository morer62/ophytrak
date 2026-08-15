<?php

use App\Services\LoginService;
use App\Services\TranslationService;
use App\Repositories\ForumTopicRepository;
use App\Repositories\ForumCategoryRepository;
use App\Utils\Router;
use App\Utils\TemplateResponse;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\UserContext;

$router = new Router();

$router->get(function () {
    $context = UserContext::get();
    $user = LoginService::getSession();

    $topicRepo = new ForumTopicRepository();
    $categoryRepo = new ForumCategoryRepository();

    $categoryFilter = $_GET['category'] ?? null;
    $search = $_GET['search'] ?? '';

    if (!empty($search)) {
        $topics = $topicRepo->searchTopics($search, 100);
    } elseif ($categoryFilter) {
        $topics = $topicRepo->getTopicsByCategory((int)$categoryFilter, 100, 0);
    } else {
        $topics = $topicRepo->getRecentTopics(100);
    }

    $categories = $categoryRepo->getActiveCategories();

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        ...$context,
        "topics" => $topics,
        "categories" => $categories,
        "categoryFilter" => $categoryFilter,
        "search" => $search
    ]);
});

$router->post(function () {
    $user = LoginService::getSession();
    $action = $_POST['action'] ?? '';
    $topicId = $_POST['topic_id'] ?? null;

    if (!$topicId) {
        TranslationService::detectLocale();
        MessageUtil::setMessage("⚠️ " . TranslationService::trans('planner_hub.topic_id_required'));
        LocationUtils::redirectInternal("panel/forum");
        return;
    }

    $topicRepo = new ForumTopicRepository();
    TranslationService::detectLocale();

    switch ($action) {
        case 'toggle_pin':
            $topic = $topicRepo->getOne(['id' => $topicId]);
            if ($topic) {
                $topicRepo->update(
                    ['is_pinned' => $topic->is_pinned ? 0 : 1],
                    ['id' => $topicId]
                );
                $message = $topic->is_pinned ? TranslationService::trans('planner_hub.topic_unpinned_successfully') : TranslationService::trans('planner_hub.topic_pinned_successfully');
                MessageUtil::setMessage("✅ " . $message);
            }
            break;

        case 'toggle_lock':
            $topic = $topicRepo->getOne(['id' => $topicId]);
            if ($topic) {
                $topicRepo->update(
                    ['is_locked' => $topic->is_locked ? 0 : 1],
                    ['id' => $topicId]
                );
                $message = $topic->is_locked ? TranslationService::trans('planner_hub.topic_unlocked_successfully') : TranslationService::trans('planner_hub.topic_locked_successfully');
                MessageUtil::setMessage("✅ " . $message);
            }
            break;

        case 'delete':
            $topicRepo->delete(['id' => $topicId]);
            MessageUtil::setMessage("✅ " . TranslationService::trans('planner_hub.topic_deleted_successfully'));
            break;
    }

    LocationUtils::redirectInternal("panel/forum");
});

$router->run();





