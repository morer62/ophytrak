<?php

use App\Services\LoginService;
use App\Repositories\ForumTopicRepository;
use App\Repositories\ForumReplyRepository;
use App\Repositories\ForumLikeRepository;
use App\Repositories\ForumAttachmentRepository;
use App\Utils\Router;
use App\Utils\TemplateResponse;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;

$router = new Router();

$router->get(function () {
    $user = LoginService::getSession();
    $topicId = $_GET['id'] ?? null;

    if (!$topicId) {
        MessageUtil::setMessage("Topic not found.");
        LocationUtils::redirectInternal("forum");
        return;
    }

    $topicRepo = new ForumTopicRepository();
    $replyRepo = new ForumReplyRepository();
    $likeRepo = new ForumLikeRepository();
    $attachmentRepo = new ForumAttachmentRepository();

    $topic = $topicRepo->getTopicWithAuthor((int)$topicId);

    if (!$topic || !$topic->is_approved) {
        MessageUtil::setMessage("Topic not found.");
        LocationUtils::redirectInternal("forum");
        return;
    }

    $topicRepo->incrementViewCount((int)$topicId);

    $replies = $replyRepo->getRepliesWithNested((int)$topicId);
    $attachments = $attachmentRepo->getAttachmentsByTopic((int)$topicId);
    
    // DEBUG
    error_log("Topic ID: " . $topicId);
    error_log("Attachments count: " . count($attachments));
    error_log("Attachments data: " . json_encode($attachments));

    $userLikedTopic = false;
    $userLikedReplies = [];

    if ($user) {
        $userLikedTopic = $likeRepo->hasUserLikedTopic($user->getId(), (int)$topicId);
        foreach ($replies as $reply) {
            if ($likeRepo->hasUserLikedReply($user->getId(), (int)$reply->id)) {
                $userLikedReplies[] = (int)$reply->id;
            }
        }
    }

    $appUrl = rtrim($_ENV['APP_URL'] ?? 'https://ophyra.com', '/');
    $canonical = $appUrl . '/forum/topic?id=' . (int)$topicId;
    $description = trim(strip_tags((string)$topic->content));
    if (strlen($description) > 155) {
        $description = substr($description, 0, 152) . '...';
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "user" => $user,
        "topic" => $topic,
        "replies" => $replies,
        "attachments" => $attachments,
        "userLikedTopic" => $userLikedTopic,
        "userLikedReplies" => $userLikedReplies,
        "seo" => [
            "title" => $topic->title . " | Ophyra Community Knowledge Base",
            "description" => $description ?: "Official Ophyra community knowledge-base answer.",
            "canonical" => $canonical,
            "robots" => "index, follow",
        ],
        "schemaJson" => [
            "@context" => "https://schema.org",
            "@type" => "DiscussionForumPosting",
            "@id" => $canonical . "#discussion",
            "headline" => $topic->title,
            "text" => strip_tags((string)$topic->content),
            "url" => $canonical,
            "datePublished" => $topic->created_at ?? null,
            "dateModified" => $topic->updated_at ?? $topic->created_at ?? null,
            "articleSection" => $topic->category_name ?? "Ophyra Knowledge Base",
            "author" => [
                "@type" => "Person",
                "name" => $topic->author_name ?? "Ophyra",
            ],
            "publisher" => [
                "@type" => "Organization",
                "name" => "Ophyra",
                "url" => $appUrl . "/",
            ],
            "commentCount" => count($replies),
        ],
    ]);
});

$router->post(function () {
    $user = LoginService::getSession();
    
    if (!$user) {
        MessageUtil::setMessage("You must be logged in to reply.");
        LocationUtils::redirectInternal("login");
        return;
    }

    $topicId = $_POST['topic_id'] ?? null;
    $action = $_POST['action'] ?? 'reply';
    $content = trim($_POST['content'] ?? '');
    $parentReplyId = !empty($_POST['parent_reply_id']) ? (int)$_POST['parent_reply_id'] : null;

    if (!$topicId) {
        MessageUtil::setMessage("Topic not found.");
        LocationUtils::redirectInternal("forum");
        return;
    }

    $topicRepo = new ForumTopicRepository();
    $topic = $topicRepo->getOne(['id' => $topicId]);

    if ($action === 'toggle_lock') {
        if ((int)$user->getLevel() !== 1 || !$topic) {
            MessageUtil::setMessage("You do not have permission to moderate this topic.");
            LocationUtils::redirectInternal("forum/topic?id=" . $topicId);
            return;
        }

        $topicRepo->update(
            ['is_locked' => $topic->is_locked ? 0 : 1],
            ['id' => $topicId]
        );

        MessageUtil::setMessage($topic->is_locked ? "Thread reopened for replies." : "Thread closed for new replies.");
        LocationUtils::redirectInternal("forum/topic?id=" . $topicId);
        return;
    }

    if (empty($content)) {
        MessageUtil::setMessage("Please provide a reply.");
        LocationUtils::redirectInternal("forum/topic?id=" . $topicId);
        return;
    }

    if (!$topic || $topic->is_locked) {
        MessageUtil::setMessage("This topic is locked.");
        LocationUtils::redirectInternal("forum/topic?id=" . $topicId);
        return;
    }

    $replyRepo = new ForumReplyRepository();
    $replyRepo->add([
        'id_topic' => $topicId,
        'id_user' => $user->getId(),
        'id_parent_reply' => $parentReplyId,
        'content' => $content,
        'is_approved' => 1
    ]);

    $topicRepo->update([
        'replies_count' => $replyRepo->countByTopic((int)$topicId),
        'last_reply_at' => date('Y-m-d H:i:s'),
        'last_reply_user_id' => $user->getId(),
    ], ['id' => $topicId]);

    MessageUtil::setMessage("✅ Reply added successfully!");
    LocationUtils::redirectInternal("forum/topic?id=" . $topicId);
});

$router->run();

