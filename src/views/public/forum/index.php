<?php

use App\Services\LoginService;
use App\Data\OphyraForumKnowledge;
use App\Repositories\ForumCategoryRepository;
use App\Repositories\ForumTopicRepository;
use App\Repositories\ForumAttachmentRepository;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $user = LoginService::getSession();
    $categoryRepo = new ForumCategoryRepository();
    $topicRepo = new ForumTopicRepository();
    $attachmentRepo = new ForumAttachmentRepository();
    
    $forumKnowledgeItems = OphyraForumKnowledge::all();
    $officialCategoryNames = OphyraForumKnowledge::categories($forumKnowledgeItems);
    $categories = array_values(array_filter(
        $categoryRepo->getAllWithStats(),
        static fn ($category): bool => in_array($category->name, $officialCategoryNames, true)
    ));
    $allowedCategoryIds = array_map(static fn ($category): int => (int)$category->id, $categories);
    
    $categoryFilter = $_GET['category'] ?? null;
    $filter = $_GET['filter'] ?? 'recent'; // recent, comments, popular, views
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $perPage = 15;
    $offset = ($page - 1) * $perPage;
    
    if ($categoryFilter && in_array((int)$categoryFilter, $allowedCategoryIds, true)) {
        $categoryFilterId = (int)$categoryFilter;
        $topics = $topicRepo->getTopicsByCategory($categoryFilterId, $perPage, $offset, $filter);
        $totalTopics = $topicRepo->countByCategory($categoryFilterId);
        $selectedCategory = null;
        foreach ($categories as $category) {
            if ((int)$category->id === $categoryFilterId) {
                $selectedCategory = $category;
                break;
            }
        }
    } else {
        // Aplicar filtro según el parámetro con paginación
        switch ($filter) {
            case 'comments':
                $topics = $topicRepo->getTopicsByCategories($allowedCategoryIds, $perPage, $offset, 'comments');
                $totalTopics = $topicRepo->countByCategories($allowedCategoryIds, 'comments');
                break;
            case 'popular':
                $topics = $topicRepo->getTopicsByCategories($allowedCategoryIds, $perPage, $offset, 'popular');
                $totalTopics = $topicRepo->countByCategories($allowedCategoryIds, 'popular');
                break;
            case 'views':
                $topics = $topicRepo->getTopicsByCategories($allowedCategoryIds, $perPage, $offset, 'views');
                $totalTopics = $topicRepo->countByCategories($allowedCategoryIds, 'views');
                break;
            case 'recent':
            default:
                $topics = $topicRepo->getTopicsByCategories($allowedCategoryIds, $perPage, $offset, 'recent');
                $totalTopics = $topicRepo->countByCategories($allowedCategoryIds, 'recent');
                break;
        }
        $selectedCategory = null;
    }
    
    // Obtener la primera imagen de cada topic
    foreach ($topics as &$topic) {
        $images = $attachmentRepo->getImagesByTopic($topic->id);
        $topic->first_image = !empty($images) ? $images[0] : null;
        $topic->images_count = count($images);
    }
    unset($topic);
    
    $totalPages = ceil($totalTopics / $perPage);

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "user" => $user,
        "categories" => $categories,
        "topics" => $topics,
        "selectedCategory" => $selectedCategory,
        "currentPage" => $page,
        "totalPages" => $totalPages,
        "currentFilter" => $filter,
        "pageTitle" => "Community Forum"
    ]);
});

$router->run();

