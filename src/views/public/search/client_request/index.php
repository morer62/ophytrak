<?php

use App\Repositories\ClientsRequestRepository;
use App\Repositories\UserRepository;
use App\Services\EmailService;
use App\Repositories\NotificationsRepository;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Router;
use App\Utils\TemplateResponse;
use App\Utils\PlatformDetector;
use App\Services\LoginService;
use App\Services\ApiAuthService;
use App\Services\StorefrontAccessService;
use App\Entity\User;

$router = new Router();

function businessProfileServicesActive(int $profileId): bool
{
    if ($profileId <= 0) {
        return false;
    }

    $db = new \App\Repositories\Connection();
    $db->query('SELECT id_owner FROM institution_profile WHERE id = :id LIMIT 1');
    $db->bind(':id', $profileId);
    $profile = $db->fetchOne();

    return $profile
        && StorefrontAccessService::ownerCanUseModule((int)$profile->id_owner, 'services');
}

$router->get(function () {
    $source = $_GET['source'] ?? null;

    if ($source === 'app_client') {
        header('X-Frame-Options: SAMEORIGIN');
        $session = LoginService::getSession();
        if (!$session || $session->getLevel() !== User::$CLIENT_USER_LEVEL || !PlatformDetector::isMobileApp()) {
            $msg = !$session ? 'Session required.' : ($session->getLevel() !== User::$CLIENT_USER_LEVEL ? 'Client access only.' : 'Open from the app or enable "Simulate mobile".');
            return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Request</title></head><body style="font-family:sans-serif;padding:1rem;"><p>' . htmlspecialchars($msg) . '</p><p><a href="javascript:window.parent.location.reload()">Reload</a></p></body></html>';
        }

        return TemplateResponse::render(__DIR__ . "/index.twig", [
            'profile_cat' => 'vendor',
            'profile_id' => 0,
            'success' => $_GET['success'] ?? null,
            'app_url' => $_ENV["APP_URL"] ?? '/',
            'source' => 'app_client',
        ]);
    }

    $profileCat = $_GET['profile_cat'] ?? null;
    $profileId = $_GET['id'] ?? null;

    if (!in_array($profileCat, ['venue', 'vendor', 'business_profile']) || !is_numeric($profileId)) {
        return "Invalid request";
    }
    if ($profileCat === 'business_profile' && !businessProfileServicesActive((int)$profileId)) {
        return "Service requests are not available for this business right now.";
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        'profile_cat' => $profileCat,
        'profile_id' => (int)$profileId,
        'success' => $_GET['success'] ?? null,
        'app_url' => $_ENV["APP_URL"] ?? '/',
        'source' => null,
    ]);
});

$router->post(function () {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $sendJson = function (array $data): never {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    };

    try {
        $repo = new ClientsRequestRepository();

        $source = $_POST['source'] ?? null;

        $data = [
            'profile_cat'     => trim($_POST['profile_cat'] ?? ''),
            'profile_id'      => (int)($_POST['profile_id'] ?? 0),
            'event_date'      => trim($_POST['event_date'] ?? ''),
            'event_time'      => trim($_POST['event_time'] ?? ''),
            'event_duration'  => floatval($_POST['event_duration'] ?? 0),
            'guests'          => !empty($_POST['guests']) ? (int)$_POST['guests'] : null,
            'budget'          => !empty($_POST['budget']) ? floatval($_POST['budget']) : null,
            'event_type'      => trim($_POST['event_type'] ?? ''),
            'details'         => trim(substr($_POST['details'] ?? '', 0, 240)),
            'client_name'     => trim($_POST['client_name'] ?? ''),
            'client_phone'    => trim($_POST['client_phone'] ?? ''),
            'client_email'    => trim($_POST['client_email'] ?? ''),
            'client_address'  => trim($_POST['client_address'] ?? ''),
            'created_at'      => date('Y-m-d H:i:s'),
            'status'            => 'PENDING' 
        ];

        $errors = [];

        if (empty($data['client_name'])) {
            $errors[] = "Full name is required";
        }
        
        if (empty($data['client_email'])) {
            $errors[] = "Email address is required";
        } elseif (!filter_var($data['client_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please enter a valid email address";
        }
        
        if (empty($data['event_date'])) {
            $errors[] = "Event date is required";
        } elseif (!validateDate($data['event_date'])) {
            $errors[] = "Please enter a valid event date";
        } elseif (strtotime($data['event_date']) < strtotime('today')) {
            $errors[] = "Event date cannot be in the past";
        }
        
        if (empty($data['event_time'])) {
            $errors[] = "Event time is required";
        }
        
        if ($data['event_duration'] <= 0) {
            $errors[] = "Event duration must be greater than 0";
        }

        if ($source !== 'app_client') {
            if (!in_array($data['profile_cat'], ['venue', 'vendor', 'business_profile'])) {
                $errors[] = "Invalid profile category";
            }

            if ($data['profile_id'] <= 0) {
                $errors[] = "Invalid profile ID";
            }
            if ($data['profile_cat'] === 'business_profile' && !businessProfileServicesActive($data['profile_id'])) {
                $errors[] = "Service requests are not available for this business right now.";
            }
        }
        
        if (empty($data['client_address'])) {
            $errors[] = "Event address is required";
        }

        if (!empty($errors)) {
            if ($isAjax) {
                $sendJson(['success' => false, 'errors' => $errors]);
            }
            MessageUtil::setMessage("Please correct the following errors: " . implode(", ", $errors));
            LocationUtils::redirectTo($_SERVER['HTTP_REFERER'] ?? "/search");
            return;
        }

        $result = $repo->add($data);

        if ($result) {
            try {
                $userRepo = new UserRepository();
                $notificationsRepo = new NotificationsRepository();

                $ownerInfo = null;
                if ($source === 'app_client') {
                    $session = LoginService::getSession();
                    if (!$session) {
                        $token = ApiAuthService::getToken(null, $_POST);
                        if ($token !== '') {
                            $session = LoginService::validateToken($token);
                            if ($session instanceof User) {
                                LoginService::setSession($session);
                                PlatformDetector::setMobileApp(true);
                            }
                        }
                    }

                    if ($session && $session->getLevel() === User::$CLIENT_USER_LEVEL && PlatformDetector::isMobileApp()) {
                        $ownerId = (int)($_POST['id_user_business'] ?? $session->getOwner());
                        try {
                            $db = new \App\Repositories\Connection();
                            $db->query("SELECT u.*, u.id as user_id FROM users u WHERE u.id = :id AND u.level IN (1, 2) AND u.is_active = 1");
                            $db->bind(':id', $ownerId);
                            $ownerInfo = $db->fetchOne();
                        } catch (\Exception $e) {
                            $ownerInfo = null;
                        }
                    }
                } else {
                    if ($data['profile_cat'] === 'venue') {
                        try {
                            $db = new \App\Repositories\Connection();
                            $venueQuery = "SELECT v.*, u.name, u.lastname, u.email FROM venues v 
                                           INNER JOIN users u ON v.user_id = u.id 
                                           WHERE v.id = :venue_id";
                            $db->query($venueQuery);
                            $db->bind(':venue_id', $data['profile_id']);
                            $ownerInfo = $db->fetchOne();
                        } catch (\Exception $e) {
                            $ownerInfo = null;
                        }
                    } elseif ($data['profile_cat'] === 'vendor') {
                        try {
                            $db = new \App\Repositories\Connection();
                            $vendorQuery = "SELECT s.*, u.name, u.lastname, u.email FROM service s 
                                            INNER JOIN users u ON s.user_id = u.id 
                                            WHERE s.id = :service_id";
                            $db->query($vendorQuery);
                            $db->bind(':service_id', $data['profile_id']);
                            $ownerInfo = $db->fetchOne();
                        } catch (\Exception $e) {
                            $ownerInfo = null;
                        }
                    } elseif ($data['profile_cat'] === 'business_profile') {
                        try {
                            $db = new \App\Repositories\Connection();
                            $profileQuery = "SELECT ip.*, u.id AS user_id, u.name, u.lastname, u.email
                                             FROM institution_profile ip
                                             INNER JOIN users u ON u.id = ip.id_owner
                                             WHERE ip.id = :profile_id";
                            $db->query($profileQuery);
                            $db->bind(':profile_id', $data['profile_id']);
                            $ownerInfo = $db->fetchOne();
                        } catch (\Exception $e) {
                            $ownerInfo = null;
                        }
                    }
                }

                if ($ownerInfo && $ownerInfo->email) {
                    $emailService = new EmailService($ownerInfo->user_id ?? null);
                    $requestTypeLabel = $data['profile_cat'] === 'business_profile'
                        ? 'business services'
                        : ($data['profile_cat'] === 'venue' ? 'venue' : 'service');

                    $emailTypeLabel = $data['profile_cat'] === 'business_profile'
                        ? 'Business Profile'
                        : ($data['profile_cat'] === 'venue' ? 'Venue' : 'Service');

                    $profileTypeLabel = $data['profile_cat'] === 'business_profile'
                        ? 'business profile'
                        : ($data['profile_cat'] === 'venue' ? 'venue' : 'service');

                    $notificationMessage = "📨 New Request Received - A client has requested your " . 
                                         ($source === 'app_client'
                                             ? 'planner services'
                                             : $requestTypeLabel) .
                                         " for " . date("F j, Y", strtotime($data['event_date'])) . 
                                         " at " . date("g:i A", strtotime($data['event_time']));

                    if ($source === 'app_client') {
                        $notificationUrl = ($_ENV["APP_URL"] ?? "http://localhost/vnv-venue") . "/panel/planner-hub/management/orders/orders";
                    } elseif ($data['profile_cat'] === 'business_profile') {
                        $notificationUrl = ($_ENV["APP_URL"] ?? "http://localhost/vnv-venue") . "/panel/planner-hub/management/crm";
                    } else {
                        $notificationUrl = ($data['profile_cat'] === 'venue') 
                            ? ($_ENV["APP_URL"] ?? "http://localhost/vnv-venue") . "/panel/venues/home"
                            : ($_ENV["APP_URL"] ?? "http://localhost/vnv-venue") . "/panel/service/home";
                    }
                    
                    $notificationsRepo->add([
                        "id_user" => $ownerInfo->user_id,
                        "mensaje" => $notificationMessage,
                        "link" => $notificationUrl,
                        "leido" => 0
                    ]);

                    $subject = "📨 New Client Request - " . (
                        $source === 'app_client'
                            ? 'Planner'
                            : $emailTypeLabel
                    ) . " Inquiry";
                    
                    $templateData = [
                        'ownerName' => $ownerInfo->name . ' ' . $ownerInfo->lastname,
                        'profileType' => $source === 'app_client'
                            ? 'planner'
                            : $profileTypeLabel,
                        'profileName' => $ownerInfo->company_name ?? $ownerInfo->name,
                        'clientName' => $data['client_name'],
                        'clientEmail' => $data['client_email'],
                        'clientPhone' => $data['client_phone'] ?: 'Not provided',
                        'eventDate' => date("F j, Y", strtotime($data['event_date'])),
                        'eventTime' => date("g:i A", strtotime($data['event_time'])),
                        'eventDuration' => $data['event_duration'] . ' hours',
                        'guests' => $data['guests'] ?: 'Not specified',
                        'budget' => $data['budget'] ? '$' . number_format($data['budget'], 2) : 'Not specified',
                        'eventType' => $data['event_type'] ?: 'Not specified',
                        'eventAddress' => $data['client_address'],
                        'details' => $data['details'] ?: 'No additional details provided',
                        'requestUrl' => $notificationUrl
                    ];
                    
                    $templatePath = \App\Utils\LocationUtils::getTemplatePath("emails/client_request_notification.php");
                    
                    $emailService->sendTemplateEmail(
                        $ownerInfo->email,
                        $subject,
                        $templatePath,
                        $templateData
                    );

                    try {
                        $globalEmail = "info@vnvevents.com";
                        $globalMailer = new EmailService(null);
                        $globalMailer->sendTemplateEmail(
                            $globalEmail,
                            $subject,
                            $templatePath,
                            $templateData
                        );
                    } catch (\Exception $e) {
                    }
                }
            } catch (\Exception $e) {
            }

            if ($isAjax) {
                $sendJson(['success' => true]);
            }
            $currentUrl = $_SERVER['REQUEST_URI'];
            $separator = strpos($currentUrl, '?') !== false ? '&' : '?';
            $redirectUrl = $currentUrl . $separator . 'success=1';
            LocationUtils::redirectTo($redirectUrl);
        } else {
            if ($isAjax) {
                $sendJson(['success' => false, 'errors' => ['Sorry, there was an error processing your request. Please try again.']]);
            }
            MessageUtil::setMessage("Sorry, there was an error processing your request. Please try again.");
            LocationUtils::redirectTo($_SERVER['HTTP_REFERER'] ?? "/search");
        }

    } catch (Exception $e) {
        error_log("Client Request Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        if ($isAjax) {
            $sendJson(['success' => false, 'errors' => ['A technical problem occurred. Please try again later.']]);
        }
        MessageUtil::setMessage("Sorry, there was a technical problem. Please try again later.");
        LocationUtils::redirectTo($_SERVER['HTTP_REFERER'] ?? "/search");
    }
});

function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

try {
    $router->run();
} catch (Exception $e) {
    error_log("Router Error: " . $e->getMessage());
    MessageUtil::setMessage("Sorry, there was a system error. Please try again.");
    LocationUtils::redirectTo("/search");
}
