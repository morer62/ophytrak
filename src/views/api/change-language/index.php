<?php

use App\Repositories\UserRepository;
use App\Services\LoginService;
use App\Services\TranslationService;
use App\Utils\JsonResponse;
use App\Utils\Router;

$router = new Router();

$persistLocale = static function (string $locale): void {
    $_SESSION['locale'] = $locale;
    $_SESSION['app_locale'] = $locale;
    setcookie('app_locale', $locale, [
        'expires' => time() + 31536000,
        'path' => '/',
        'samesite' => 'Lax',
    ]);
};

$router->post(function () {
    global $persistLocale;

    $locale = TranslationService::normalizeLocale($_POST['locale'] ?? $_GET['locale'] ?? null);
    $systemLanguage = TranslationService::normalizeLocale($_POST['system_language'] ?? null);
    
    if (!$locale || !TranslationService::isSupportedLocale($locale)) {
        return JsonResponse::createResponse(['success' => false, 'message' => TranslationService::trans('language.invalid')], 400);
    }
    
    // Si hay usuario autenticado, guardar en BD
    $user = LoginService::getSession();
    if ($user) {
        $userRepository = new UserRepository();
        $systemLang = $systemLanguage && TranslationService::isSupportedLocale($systemLanguage) 
            ? $systemLanguage 
            : (TranslationService::normalizeLocale($user->getSystemLanguage() ?? 'en') ?? 'en');
        
        $userRepository->updateUserLanguages($user->getId(), $locale, $systemLang);
        
        // Actualizar la sesiÃ³n del usuario
        $user->setUiLanguage($locale);
        $user->setSystemLanguage($systemLang);
        LoginService::setSession($user);
    }
    
    $success = TranslationService::setLocale($locale);
    $persistLocale($locale);
    
    if ($success) {
        return JsonResponse::createResponse([
            'success' => true,
            'locale' => $locale,
            'message' => TranslationService::trans('language.changed')
        ]);
    }
    
    return JsonResponse::createResponse(['success' => false, 'message' => 'Failed to change language'], 500);
});

$router->get(function () {
    global $persistLocale;

    $locale = TranslationService::normalizeLocale($_GET['locale'] ?? null);
    
    if (!$locale || !TranslationService::isSupportedLocale($locale)) {
        return JsonResponse::createResponse(['success' => false, 'message' => TranslationService::trans('language.invalid')], 400);
    }
    
    $success = TranslationService::setLocale($locale);
    $persistLocale($locale);
    
    if ($success) {
        // Redirigir a la pÃ¡gina anterior o al home
        $redirect = $_GET['redirect'] ?? '/';
        header('Location: ' . $redirect);
        exit;
    }
    
    return JsonResponse::createResponse(['success' => false, 'message' => 'Failed to change language'], 500);
});

$router->run();




