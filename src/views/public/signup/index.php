<?php

use App\Repositories\UserRepository;
use App\Repositories\InstitutionProfileRepository;
use App\Repositories\UserModulesRepository;
use App\Services\HashService;
use App\Services\AffiliateService;
use App\Services\LoginService;
use App\Services\GeoPricingService;
use App\Services\LegalConsentService;
use App\Services\OphyraPricingService;
use App\Services\OphyraSeoService;
use App\Services\RecaptchaEnterpriseService;
use App\Services\TranslationService;
use App\Utils\FormatPhone;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Response;
use App\Utils\Router;
use App\Utils\TemplateResponse;

$router = new Router();
const OPHYRA_BUSINESS_LEVEL = 2;
const SOCIAL_SIGNUP_ENABLED = false;

$client = new Google\Client();
$client->setClientId($_ENV['GOOGLE_CLIENT_ID']);
$client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']);
$client->setRedirectUri($_ENV['APP_URL'] . "/signup");
$client->addScope("email");
$client->addScope("profile");
$client->addScope("https://www.googleapis.com/auth/calendar.events");

if (\App\Services\LoginService::getSession() !== null) {
    \App\Utils\LocationUtils::redirectInternal("panel/home");
    exit;
}

$router->get(function () use ($client) {
    $level = OPHYRA_BUSINESS_LEVEL;
    $code = $_GET['code'] ?? null;
    $state = $_GET['state'] ?? OPHYRA_BUSINESS_LEVEL;
    $fromAffiliate = $_GET['from_affiliate'] ?? null;

    if ($code) {
        if (!SOCIAL_SIGNUP_ENABLED) {
            MessageUtil::setMessage("Google signup is currently disabled. Please create your account with email and password.");
            LocationUtils::redirectInternal('signup');
            exit;
        }

        handleGoogleCallback($client, $code);
        exit();
    }

    $affiliateService = new AffiliateService();
    $affiliateData = $affiliateService->getAffiliateFromCookie();
    $pricingService = new OphyraPricingService();
    $geoPricingService = new GeoPricingService($pricingService);
    $pricingContext = $geoPricingService->publicContext();
    $recaptchaService = new RecaptchaEnterpriseService();
    $appUrl = rtrim($_ENV['APP_URL'] ?? 'https://ophyra.com', '/');
    $seoService = new OphyraSeoService();
    $seo = $seoService->seoForRoute('signup', $appUrl);

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "level" => $level,
        "from_affiliate" => $fromAffiliate,
        "affiliate_data" => $affiliateData,
        "detectedCountryCode" => $pricingContext['country_code'],
        "detectedCurrencyCode" => \App\Services\ProductProfileService::billingCurrency() ?? $pricingContext['currency_code'],
        "isEuropeanRegion" => $pricingContext['is_european_region'],
        "recaptchaSiteKey" => $recaptchaService->siteKey(),
        "recaptchaAction" => $recaptchaService->expectedAction(),
        "seo" => $seo,
        "schemaJsonList" => $seoService->schemaJsonListForRoute('signup', $appUrl, $seo)
    ]);
});

$router->post(function () {
    $userRepository = new UserRepository();
    $institutionProfileRepository = new InstitutionProfileRepository();
    $affiliateService = new AffiliateService();
    $recaptchaService = new RecaptchaEnterpriseService();

    if ($recaptchaService->isConfigured()) {
        $captcha = $recaptchaService->verifySignupToken(
            $_POST['recaptcha_token'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null
        );

        if (empty($captcha['success'])) {
            error_log('Signup reCAPTCHA rejected: ' . json_encode($captcha));
            $message = 'Security verification failed. Please refresh the page and try again.';

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                return \App\Utils\JsonResponse::createResponse([
                    'success' => false,
                    'message' => $message
                ], 400);
            }

            MessageUtil::setMessage($message, 'Error', 'error');
            LocationUtils::redirectInternal('signup');
        }
    }
    
    $password = $_POST["password"];
    $passwordConfirmation = $_POST["passwordConfirmation"];

    if ($password !== $passwordConfirmation) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            return \App\Utils\JsonResponse::createResponse([
                'success' => false,
                'message' => 'Passwords must match'
            ]);
        }
        return Response::createResponse("Passwords must match");
    }

    $userExists = $userRepository->getOne(["email" => $_POST["email"]]);
    if ($userExists != null) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            return \App\Utils\JsonResponse::createResponse([
                "success" => false,
                "message" => "Email already registered"
            ]);
        }
        MessageUtil::setMessage("User already exists");
        LocationUtils::redirectInternal('signup');
    }

    $dueDate = null;
    $level = OPHYRA_BUSINESS_LEVEL;
    $id_owner = null;
    $companyName = trim((string)($_POST['company_name'] ?? ''));
    $businessNature = trim((string)($_POST['business_nature'] ?? ''));
    $businessOperationType = trim((string)($_POST['business_operation_type'] ?? ''));
    if (\App\Services\ProductProfileService::isOphytrack()) {
        $allowedNatures = ['commerce_operations','logistics_delivery','carrier_logistics','inventory_storage','local_store','wholesale_distribution'];
        $allowedOperations = ['inventory_fulfillment','store_delivery_tracking'];
        if (!in_array($businessNature, $allowedNatures, true) || !in_array($businessOperationType, $allowedOperations, true)) {
            MessageUtil::setMessage('Select a store, inventory, carrier or delivery operation for OPHYTRACK.');
            LocationUtils::redirectInternal('signup');
        }
    }
    $phoneNumber = normalizeSignupPhone($_POST);
    $pricingService = new OphyraPricingService();
    $geoPricingService = new GeoPricingService($pricingService);
    $detectedCountry = trim((string)($_POST['detected_country_code'] ?? ''));
    $preferredCurrency = \App\Services\ProductProfileService::billingCurrency()
        ?? $pricingService->normalizePaymentCurrency($_POST['preferred_currency'] ?? $geoPricingService->resolveCurrencyForCountry($detectedCountry));

    if ($companyName === '') {
        MessageUtil::setMessage('Business name is required.');
        LocationUtils::redirectInternal('signup');
    }

    if (empty($_POST['terms'])) {
        MessageUtil::setMessage('You must accept the Terms and acknowledge the Privacy Policy before creating an account.', 'Error', 'error');
        LocationUtils::redirectInternal('signup');
    }

    $userData = [
        'name' => $_POST["name"],
        'lastname' => $_POST["lastname"],
        'email' => $_POST["email"],
        'password' => HashService::hashPassword($password),
        'phone' => FormatPhone::formatPhone($phoneNumber),
        'phone_code' => '',
        'phone_validation' => 1,
        'membership_due_date' => $dueDate,
        'membership_type' => 'FREE',
        'level' => $level,
        'id_owner' => $id_owner,
        'preferred_currency' => $preferredCurrency
    ];

    $userRepository->add($userData);
    $user_id = $userRepository->getLastId();

    $userRepository->update(["id_owner" => $user_id], ["id" => $user_id]);
    $institutionProfileRepository->upsertBasicBusinessProfile(
        $user_id,
        $companyName,
        $businessNature !== '' ? $businessNature : null,
        $businessOperationType !== '' ? $businessOperationType : null,
        FormatPhone::formatPhone($phoneNumber),
        $_POST["email"]
    );
    if ($businessNature === 'carrier_logistics') {
        $institutionProfileRepository->update(['organization_type' => 'CARRIER'], ['id_owner' => $user_id]);
        (new UserModulesRepository())->provisionCarrierLogisticsLicense($user_id);
    }
    (new LegalConsentService())->recordSignupConsents($user_id, (string)$_POST['email'], $detectedCountry ?: null, $preferredCurrency);

    try {
        $affiliateData = $affiliateService->getAffiliateFromCookie();
        if ($affiliateData && isset($affiliateData['referrer_id']) && $affiliateData['referrer_id']) {
            $affiliateService->registerReferral($user_id, $affiliateData);
        }
    } catch (\Exception $e) {
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        return \App\Utils\JsonResponse::createResponse([
            "success" => true,
            "message" => TranslationService::trans('auth.signup_success_ready'),
            "redirect" => LocationUtils::assetFor('login')
        ]);
    }

    MessageUtil::setMessage(TranslationService::trans('auth.signup_success_ready'));
    LocationUtils::redirectInternal('login');
});

function normalizeSignupPhone(array $post): string
{
    $fullPhone = trim((string)($post['phoneNumber'] ?? ''));
    if ($fullPhone !== '') {
        return $fullPhone;
    }

    $countryCode = preg_replace('/[^\d+]/', '', (string)($post['phone_country_code'] ?? ''));
    $localNumber = preg_replace('/\D/', '', (string)($post['phone_local_number'] ?? ''));

    if ($countryCode === '' || $localNumber === '') {
        return '';
    }

    if (!str_starts_with($countryCode, '+')) {
        $countryCode = '+' . $countryCode;
    }

    return $countryCode . $localNumber;
}

function handleGoogleCallback($client, $code)
{
    try {
        $userRepository = new UserRepository();
        $institutionProfileRepository = new InstitutionProfileRepository();
        $affiliateService = new AffiliateService();

        $token = $client->fetchAccessTokenWithAuthCode($code);
        $client->setAccessToken($token);

        $google_service = new Google\Service\Oauth2($client);
        $data = $google_service->userinfo->get();

        $existingUser = $userRepository->getOne(["email" => $data->email]);
        if ($existingUser != null) {
            MessageUtil::setMessage("User already exists");
            LocationUtils::redirectInternal('signup');
            return;
        }

        $dueDate = null;
        $level = OPHYRA_BUSINESS_LEVEL;
        $id_owner = null;
        $preferredCurrency = (new OphyraPricingService())->getDefaultCurrency();

        $userData = [
            'name' => $data->given_name ?? $data->name,
            'lastname' => $data->family_name ?? '',
            'email' => $data->email,
            'password' => HashService::hashPassword(bin2hex(random_bytes(16))),
            'phone' => '',
            'phone_code' => '',
            'phone_validation' => 1,
            'membership_due_date' => $dueDate,
            'membership_type' => 'FREE',
            'level' => $level,
            'google_id' => $data->id,
            'google_token' => json_encode($token),
            'id_owner' => $id_owner,
            'preferred_currency' => $preferredCurrency
        ];

        $userRepository->add($userData);
        $user_id = $userRepository->getLastId();

        $userRepository->update(["id_owner" => $user_id], ["id" => $user_id]);
        $institutionProfileRepository->upsertBasicBusinessProfile(
            $user_id,
            $data->name ?? $data->email,
            null,
            null,
            '',
            $data->email
        );

        try {
            $affiliateData = $affiliateService->getAffiliateFromCookie();
            if ($affiliateData && isset($affiliateData['referrer_id']) && $affiliateData['referrer_id']) {
                $affiliateService->registerReferral($user_id, $affiliateData);
            }
        } catch (\Exception $e) {
        }

        LoginService::authenticateFromUserDbo($userRepository->getOneWithoutOwnership(['id' => $user_id]));
        MessageUtil::setMessage("Your business account was created. Let's build your OPHYTRACK workspace.");
        LocationUtils::redirectInternal('panel/onboarding');
    } catch (Exception $e) {
        MessageUtil::setMessage("Error with Google signup: " . $e->getMessage());
        LocationUtils::redirectInternal('signup');
    }
}

try {
    $router->run();
} catch (Exception $e) {
    echo $e->getMessage();
}
