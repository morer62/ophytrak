<?php

use App\Repositories\StorageContainerRepository;
use App\Services\LoginService;
use App\Services\TranslationService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;
use App\Utils\UserContext;

$router = new Router();

$router->get(function () {
    $context = UserContext::get();

    if ($context["level"] === 3 && !$context["can"]("storage", "edit")) {
        LocationUtils::redirectInternal("panel/no-access");
        return "";
    }

    $containerRepo = new StorageContainerRepository();

    $container = $containerRepo->getOne([
        "id" => $_GET["id"] ?? null,
        ...LoginService::getOwnerAsArray()
    ]);

    if (!$container) {
        TranslationService::detectLocale();
        MessageUtil::setMessage(TranslationService::trans('planner_hub.container_not_found'));
        LocationUtils::redirectInternal("panel/planner-hub/management/storage/containers");
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "container" => $container,
        ...$context
    ]);
});

$router->post(function () {
    TranslationService::detectLocale();
    $context = UserContext::get();

    if ($context["level"] === 3 && !$context["can"]("storage", "edit")) {
        LocationUtils::redirectInternal("panel/no-access");
        return "";
    }

    $containerRepo = new StorageContainerRepository();
    $user = LoginService::getSession();

    $containerId = $_GET["id"] ?? null;
    $name = trim($_POST["name"] ?? "");

    if (!$containerId || $name === "") {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.all_fields_required'));
        LocationUtils::reload();
    }

    $container = $containerRepo->getOne([
        "id" => $containerId,
        ...LoginService::getOwnerAsArray()
    ]);

    if (!$container) {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.container_not_found'));
        LocationUtils::redirectInternal("panel/planner-hub/management/storage/containers");
    }

    $updateData = ["name" => $name];

    if (\App\Utils\FileUtils::hasFile($_FILES, "img_reference")) {
        try {
            // Opcional: borrar la anterior
            if (!empty($container->img_reference)) {
                \App\Utils\FileUtils::removeFile($container->img_reference);
            }

            $imgPath = \App\Utils\FileUtils::saveFile($_FILES["img_reference"], "container_img_reference");
            $updateData["img_reference"] = $imgPath;
        } catch (Exception $e) {
            MessageUtil::setMessage(TranslationService::trans('planner_hub.error_uploading_image') . ": " . $e->getMessage());
            LocationUtils::reload();
        }
    }

    $containerRepo->update($updateData, ["id" => $containerId]);

    MessageUtil::setMessage(TranslationService::trans('planner_hub.container_updated'));
    LocationUtils::redirectInternal("panel/planner-hub/management/storage/containers");
});


$router->run();
