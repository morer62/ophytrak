<?php

use App\Repositories\ClientsUsersRepository;
use App\Repositories\Connection;
use App\Repositories\UserRepository;
use App\Services\HashService;
use App\Utils\Cors;
use App\Utils\FormatPhone;
use App\Utils\JsonResponse;
use App\Utils\Router;
use Google\Client;
use Google\Service\Oauth2;

Cors::handle();
$router = new Router();

$router->post(function () {
    $name = trim($_POST["name"] ?? "");
    $lastname = trim($_POST["lastname"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $phone = $_POST["phone"] ?? "";
    $level = isset($_POST["level"]) ? (int)$_POST["level"] : 5;
    $googleToken = $_POST["google_token"] ?? "";
    $businessOwnerId = resolveMobileSignupBusinessOwnerId();

    $userRepo = new UserRepository();
    $clientsUsersRepo = new ClientsUsersRepository();

    try {
        if ($level !== 5) {
            return JsonResponse::createResponse([
                "success" => false,
                "message" => "Mobile signup is only available for final clients. Business signup must use the Ophyra web signup."
            ], 403);
        }

        if ($businessOwnerId <= 0) {
            return JsonResponse::createResponse([
                "success" => false,
                "message" => "Business owner context is required for mobile client signup."
            ], 422);
        }

        if ($googleToken) {
            $client = new Client();
            $client->setClientId($_ENV['GOOGLE_CLIENT_ID'] ?? '');
            $client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET'] ?? '');
            $client->setAccessToken(['access_token' => $googleToken]);

            $googleService = new Oauth2($client);

            try {
                $data = $googleService->userinfo->get();
            } catch (\Throwable $e) {
                return JsonResponse::createResponse([
                    "success" => false,
                    "message" => "Google token invalid or expired"
                ]);
            }

            $email = $data->email ?? null;
            $name = $data->givenName ?? '';
            $lastname = $data->familyName ?? '';

            if (!$email) {
                return JsonResponse::createResponse([
                    "success" => false,
                    "message" => "Unable to get email from Google account"
                ]);
            }

            $existing = $userRepo->getOne(["email" => $email]);
            if ($existing) {
                if ((int)$existing->level !== 5) {
                    return JsonResponse::createResponse([
                        "success" => false,
                        "message" => "This email is already registered as a non-client account."
                    ], 409);
                }

                $clientsUsersRepo->create((int)$existing->id, $businessOwnerId);
                return mobileSignupUserResponse($existing, $businessOwnerId, true);
            }

            $userRepo->add([
                'name' => $name,
                'lastname' => $lastname,
                'email' => $email,
                'password' => '',
                'phone' => FormatPhone::formatPhone($phone),
                'phone_code' => '',
                'phone_validation' => 1,
                'membership_due_date' => null,
                'membership_type' => 'FREE',
                'level' => 5,
                'id_owner' => $businessOwnerId,
                'google_id' => $data->id ?? null,
                'google_token' => json_encode(['access_token' => $googleToken]),
            ]);

            $user = $userRepo->getOne(["email" => $email]);
            $clientsUsersRepo->create((int)$user->id, $businessOwnerId);

            return mobileSignupUserResponse($user, $businessOwnerId);
        }

        if (!$name || !$lastname || !$email || !$password) {
            return JsonResponse::createResponse([
                "success" => false,
                "message" => "Name, lastname, email, and password are required"
            ]);
        }

        $existing = $userRepo->getOne(["email" => $email]);
        if ($existing) {
            if ((int)$existing->level !== 5) {
                return JsonResponse::createResponse([
                    "success" => false,
                    "message" => "This email is already registered as a non-client account."
                ], 409);
            }

            $clientsUsersRepo->create((int)$existing->id, $businessOwnerId);
            return mobileSignupUserResponse($existing, $businessOwnerId, true);
        }

        $userRepo->add([
            'name' => $name,
            'lastname' => $lastname,
            'email' => $email,
            'password' => HashService::hashPassword($password),
            'phone' => FormatPhone::formatPhone($phone),
            'phone_code' => '',
            'phone_validation' => 1,
            'membership_due_date' => null,
            'membership_type' => 'FREE',
            'level' => 5,
            'id_owner' => $businessOwnerId,
        ]);

        $user = $userRepo->getOne(["email" => $email]);
        $clientsUsersRepo->create((int)$user->id, $businessOwnerId);

        return mobileSignupUserResponse($user, $businessOwnerId);
    } catch (Exception $e) {
        return JsonResponse::createResponse([
            "success" => false,
            "message" => $e->getMessage()
        ], 500);
    }
});

$router->run();

function resolveMobileSignupBusinessOwnerId(): int
{
    $postedOwner = (int)(
        $_POST['id_user_business']
        ?? $_POST['id_owner']
        ?? $_POST['business_id']
        ?? $_POST['owner_id']
        ?? 0
    );

    $db = new Connection();

    if ($postedOwner > 0) {
        $db->query("SELECT id FROM users WHERE id = :id AND level IN (1, 2) AND is_active = 1 LIMIT 1");
        $db->bind(':id', $postedOwner);
        if ($db->fetchOne()) {
            return $postedOwner;
        }
    }

    // Legacy branded apps did not always send an owner. Keep client signup working,
    // but never create a business account from mobile.
    $db->query("SELECT id FROM users WHERE level = 1 AND is_active = 1 ORDER BY id ASC LIMIT 1");
    $owner = $db->fetchOne();
    return (int)($owner->id ?? 0);
}

function mobileSignupUserResponse(object $user, int $businessOwnerId, bool $associatedExisting = false): JsonResponse
{
    $token = bin2hex(random_bytes(32));
    (new UserRepository())->updateApiToken((int)$user->id, $token);

    return JsonResponse::createResponse([
        "success" => true,
        "associated_existing_client" => $associatedExisting,
        "token" => $token,
        "api_token" => $token,
        "user" => [
            "id" => $user->id,
            "name" => $user->name,
            "lastname" => $user->lastname,
            "email" => $user->email,
            "level" => $user->level,
            "id_owner" => $user->id_owner ?? $businessOwnerId,
            "id_user_business" => $businessOwnerId,
        ]
    ]);
}
