<?php

use App\Services\OpenAIService;
use App\Services\TranslationService;
use App\Utils\FileUtils;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    return TemplateResponse::render(__DIR__ . "/index.twig", [
        'audioGenerated' => isset($_SESSION['audioPath']),
        'audioPath' => $_SESSION['audioPath'] ?? null,
        'originalText' => $_SESSION['originalText'] ?? null,
    ]);
});

$router->post(function () {
    $text = $_POST['textInput'] ?? '';

    if (empty($text)) {
        TranslationService::detectLocale();
        MessageUtil::setMessage(TranslationService::trans('planner_hub.please_enter_some_text'));
        LocationUtils::redirectInternal("panel/mark-tools/voice-generator");
        
    }

    try {
        $audioBinary = OpenAIService::generateOpenAIAudio($text);
        $audioPath = FileUtils::saveFileFromContent($audioBinary, "audios");

        if ($audioPath) {
            $_SESSION['audioPath'] = $audioPath;
            $_SESSION['originalText'] = $text;
            TranslationService::detectLocale();
            MessageUtil::setMessage("✅ " . TranslationService::trans('planner_hub.voice_generated_successfully'));
        } else {
            TranslationService::detectLocale();
            MessageUtil::setMessage("❌ " . TranslationService::trans('planner_hub.error_generating_voice'));
        }

  
        LocationUtils::redirectInternal("panel/mark-tools/voice-generator");
    } catch (Exception $e) {
        MessageUtil::setMessage($e->getMessage());
    }

    return TemplateResponse::render(__DIR__ . "/index.twig");
});

$router->run();