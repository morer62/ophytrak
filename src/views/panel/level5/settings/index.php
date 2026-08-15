<?php

use App\Repositories\UserRepository;
use App\Services\HashService;
use App\Services\LoginService;
use App\Services\TranslationService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();

$router->get(function () {
    $user = LoginService::getSession();

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        'user' => $user
    ]);
});

$router->post(function () {
    TranslationService::detectLocale();
    $user = LoginService::getSession();
    $userRepository = new UserRepository();

    $name = $_POST["name"];
    $lastname = $_POST["lastname"];
    $email = $_POST["email"];
    $password = $_POST["password"] ?? '';
    $passwordRepeat = $_POST["password_repeat"] ?? '';
    $uiLanguage = TranslationService::normalizeLocale($_POST["ui_language"] ?? null);
    // Nivel 5 no puede cambiar system_language

    if ($password && $password !== $passwordRepeat) {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.passwords_do_not_match'), "Error", "error");
        LocationUtils::redirectInternal("panel/settings");
    }

    $updateData = [
        'name' => $name,
        'lastname' => $lastname,
        'email' => $email,
    ];

    if (!empty($password)) {
        $updateData['password'] = HashService::hashPassword($password);
    }

    $userRepository->updateData($user->getId(), $updateData);

    // Actualizar idiomas si se proporcionaron
    // Nivel 4 y 5 solo pueden cambiar ui_language, no system_language
    if ($uiLanguage && TranslationService::isSupportedLocale($uiLanguage)) {
   
        $sysLang = TranslationService::normalizeSupportedLocale($user->getSystemLanguage() ?? 'en');
        
        $userRepository->updateUserLanguages($user->getId(), $uiLanguage, $sysLang);
        
     
        $user->setUiLanguage($uiLanguage);
        LoginService::setSession($user);
        
       
        TranslationService::setLocale($uiLanguage);
    }

   
    if (!empty($password) || $email !== $user->getEmail()) {
        LoginService::logout();
        MessageUtil::setMessage(TranslationService::trans('planner_hub.profile_updated_relogin'));
        LocationUtils::redirectInternal("login");
    } else {
        MessageUtil::setMessage(TranslationService::trans('planner_hub.profile_updated_successfully'), "Success", "success");
        LocationUtils::redirectInternal("panel/settings");
    }
});

try {
    $router->run();
} catch (Exception $e) {
    echo $e->getMessage();
}
