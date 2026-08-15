<?php

use App\Services\ModuleGuardService;


use App\Repositories\EventsRepository;
use App\Services\LoginService;
use App\Services\TranslationService;
use App\Utils\FileUtils;
use App\Utils\Router;
use App\Utils\TemplateResponse;
use App\Utils\MessageUtil;
use App\Utils\LocationUtils;

ModuleGuardService::requireModule('tickets_rsvp');


$router = new Router();

$router->get(function () {
    $user = LoginService::getSession();
$eventTypes = EventsRepository::EVENT_TYPES;
    
    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "eventTypes" => $eventTypes,
        "paymentSetupUrl" => "panel/planner-hub/settings/payment-providers"
]);
});

$router->post(function () {
    $user = LoginService::getSession();
    $eventsRepo = new EventsRepository();
    
    $slug = $eventsRepo->generateUniqueSlug($_POST["event_name"]);
    
    $coverImageUrl = null;
    if (!empty($_FILES) && isset($_FILES["cover_image"]) && $_FILES["cover_image"]["error"] == 0) {
        try {
            $coverImageUrl = FileUtils::saveFile($_FILES["cover_image"], "event-cover-images");
        } catch (Exception $e) {
            error_log("Error uploading cover image: " . $e->getMessage());
        }
    }
    
    $eventData = [
        "id_user" => $user->getId(),
        "id_owner" => $user->getOwner(),
        "event_name" => $_POST["event_name"],
        "event_type" => $_POST["event_type"],
        "event_description" => $_POST["event_description"] ?? null,
        "event_date" => $_POST["event_date"],
        "original_event_date" => $_POST["event_date"],
        "event_time" => $_POST["event_time"],
        "event_end_time" => $_POST["event_end_time"] ?? null,
        "venue_name" => $_POST["venue_name"] ?? null,
        "venue_address" => $_POST["venue_address"] ?? null,
        "venue_city" => $_POST["venue_city"] ?? null,
        "venue_state" => $_POST["venue_state"] ?? null,
        "venue_zip" => $_POST["venue_zip"] ?? null,
        "max_guests" => intval($_POST["max_guests"] ?? 0),
        "expected_guests" => intval($_POST["expected_guests"] ?? 0),
        "template_id" => intval($_POST["template_id"] ?? 1),
        "primary_color" => $_POST["primary_color"] ?? '#3B82F6',
        "secondary_color" => $_POST["secondary_color"] ?? '#8B5CF6',
        "font_family" => $_POST["font_family"] ?? 'Poppins',
        "header_text_color" => $_POST["header_text_color"] ?? '#FFFFFF',
        "cover_image_url" => $coverImageUrl,
        "custom_message" => $_POST["custom_message"] ?? null,
        "allow_plus_ones" => isset($_POST["allow_plus_ones"]) ? 1 : 0,
        "max_plus_ones_per_guest" => intval($_POST["max_plus_ones_per_guest"] ?? 5),
        "rsvp_deadline" => $_POST["rsvp_deadline"] ?? null,
        "dress_code" => $_POST["dress_code"] ?? null,
        "slug" => $slug,
        "status" => "active",
        "is_paid" => 1,
        "date_changes_count" => 0
    ];
    
    $eventsRepo->add($eventData);
    $eventId = $eventsRepo->getLastId();
    
    TranslationService::detectLocale();
    MessageUtil::setMessage(TranslationService::trans('planner_hub.ei_event_created'));
    LocationUtils::redirectInternal("panel/event-invitations/guests?id=" . $eventId);
});

try {
    $router->run();
} catch (Exception $e) {
    echo $e->getMessage();
}

