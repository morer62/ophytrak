<?php

use App\Repositories\UserRepository;
use App\Repositories\CarrierPackageRepository;
use App\Repositories\OphytrackDriverProfileRepository;
use App\Services\HashService;
use App\Services\LoginService;
use App\Services\TranslationService;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;
use App\Utils\FileUtils;

$router = new Router();

$router->get(function () {
    $user = LoginService::getSession();
    $isCarrierOrganization = (new CarrierPackageRepository())->isCarrier((int)$user->getOwner());

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        'user' => $user,
        'isCarrierOrganization' => $isCarrierOrganization,
        'driverProfile' => $isCarrierOrganization ? (new OphytrackDriverProfileRepository())->findByUser((int)$user->getId()) : null,
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
    // Nivel 4 no puede cambiar system_language

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

    $isCarrierOrganization = (new CarrierPackageRepository())->isCarrier((int)$user->getOwner());
    if ($isCarrierOrganization) {
        $profileRepository = new OphytrackDriverProfileRepository();
        $profileData = [
            'bank_account_name' => $_POST['bank_account_name'] ?? '',
            'document_type' => $_POST['document_type'] ?? '',
            'document_number' => $_POST['document_number'] ?? '',
            'bank_name' => $_POST['bank_name'] ?? '',
            'bank_agency' => $_POST['bank_agency'] ?? '',
            'bank_account_type' => $_POST['bank_account_type'] ?? '',
            'bank_account_number' => $_POST['bank_account_number'] ?? '',
            'pix_key' => $_POST['pix_key'] ?? '',
        ];
        if (FileUtils::hasFile($_FILES, 'profile_photo')) {
            $photo = $_FILES['profile_photo'];
            if (!in_array((string)($photo['type'] ?? ''), ['image/jpeg', 'image/png', 'image/webp'], true) || (int)($photo['size'] ?? 0) > 8 * 1024 * 1024) {
                MessageUtil::setMessage(TranslationService::trans('ophytrack_driver_profile.invalid_photo'), 'Error', 'error');
                LocationUtils::redirectInternal('panel/settings');
            }
            $profileData['profile_photo_url'] = FileUtils::saveFile($photo, 'ophytrack-driver-profiles');
        }
        if (!$profileRepository->saveForUser((int)$user->getId(), $profileData)) {
            MessageUtil::setMessage(TranslationService::trans('ophytrack_driver_profile.save_error'), 'Error', 'error');
            LocationUtils::redirectInternal('panel/settings');
        }
    }

    // Actualizar idiomas si se proporcionaron
    // Nivel 4 y 5 solo pueden cambiar ui_language, no system_language
    if ($uiLanguage && TranslationService::isSupportedLocale($uiLanguage)) {
        // Mantener el system_language actual (no permitir cambio)
        $sysLang = TranslationService::normalizeSupportedLocale($user->getSystemLanguage() ?? 'en');
        
        $userRepository->updateUserLanguages($user->getId(), $uiLanguage, $sysLang);
        
        // Actualizar sesión
        $user->setUiLanguage($uiLanguage);
        LoginService::setSession($user);
        
        // Establecer idioma actual
        TranslationService::setLocale($uiLanguage);
    }

    // cerrar sesión y forzar re-login solo si cambió password o email
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
