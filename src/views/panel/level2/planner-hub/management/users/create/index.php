<?php

use App\Entity\UserPermissions;
use App\Repositories\RolesRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserFeaturePermissionsRepository;
use App\Repositories\UserRolesRepository;
use App\Repositories\CrmCategoryRepository;
use App\Repositories\CrmLeadRepository;
use App\Services\HashService;
use App\Services\EmailService;
use App\Utils\FormatPhone;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;
use App\Utils\Response;
use App\Utils\Router;
use App\Utils\TemplateResponse;
use App\Services\LoginService;
use App\Repositories\ClientsUsersRepository;
use App\Services\UserInstitutionService;
use App\Repositories\InstitutionProfileRepository;
use App\Repositories\UserInstitutionsRepository;
use App\Repositories\TeamMemberContractTemplatesRepository;
use App\Repositories\TeamMemberContractsRepository;
use App\Repositories\PasswordResetRepository;

function sendSecureAccountInvitation(string $email, string $name, string $userType): bool
{
    try {
        // Platform account invitations are transactional Ophyra messages. Never
        // disclose a password selected by an operator (or the legacy default).
        $emailService = new EmailService(null);
        $baseUrl = rtrim((string)($_ENV["APP_URL"] ?? "https://ophyra.com"), '/');
        $token = bin2hex(random_bytes(32));
        (new PasswordResetRepository())->add([
            'email' => $email,
            'token' => $token,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+24 hours')),
        ]);
        $inviteUrl = $baseUrl . '/reset-password?token=' . urlencode($token);
        $userTypeText = ($userType === "4") ? "Team Member" : "Client";
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $message = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto">'
            . '<h1>Welcome to Ophyra</h1><p>Hello ' . $safeName . ',</p>'
            . '<p>An Ophyra business created your <strong>' . $userTypeText . '</strong> account using ' . $safeEmail . '.</p>'
            . '<p>For your security, passwords are never sent by email. Use this private, single-use invitation to establish your password and access your account:</p>'
            . '<p><a href="' . htmlspecialchars($inviteUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#0f766e;color:#fff;padding:12px 18px;border-radius:8px;text-decoration:none">Set password and access Ophyra</a></p>'
            . '<p>This invitation expires in 24 hours. If you did not expect it, you can ignore this message.</p></div>';
        return $emailService->sendSimpleEmail($email, 'Welcome to Ophyra - Activate your account', $message, true);
    } catch (\Throwable $e) {
        error_log('Ophyra account invitation failed: ' . $e->getMessage());
        return false;
    }
}

function assignTeamMemberContractIfRequested(
    int $teamMemberId,
    int $ownerId,
    int $assignedBy,
    int $templateId
): bool {
    if ($teamMemberId <= 0 || $ownerId <= 0 || $templateId <= 0) {
        return false;
    }

    $templateRepo = new TeamMemberContractTemplatesRepository();
    if (!$templateRepo->hasStorage()) {
        return false;
    }

    $template = $templateRepo->getOneByIdAndOwner($templateId, $ownerId);
    if (!$template) {
        return false;
    }

    $contractRepo = new TeamMemberContractsRepository();
    $contractRepo->create([
        'id_owner' => $ownerId,
        'team_member_id' => $teamMemberId,
        'contract_template_id' => $templateId,
        'contract_template_version' => date('YmdHis'),
        'assigned_by' => $assignedBy,
        'status' => 'PENDING',
        'source' => 'digital_signature',
        'sign_token' => bin2hex(random_bytes(32)),
        'sign_token_expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        'contract_snapshot_html' => $template->content ?? '',
        'contract_snapshot_json' => json_encode([
            'template_id' => $templateId,
            'template_table' => 'team_member_contract_templates',
            'template_title' => $template->title ?? null,
        ]),
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    return true;
}

$router = new Router();

$router->get(function () {
    $rolesRepo = new RolesRepository();
    $categoryRepo = new CrmCategoryRepository();
    $candidate = $_SESSION["client_candidate"] ?? null;
    unset($_SESSION["client_candidate"]);

    $newClientForEstimate = $_SESSION["new_client_for_estimate"] ?? null;
    unset($_SESSION["new_client_for_estimate"]);
    $returnTo = ($_GET['return_to'] ?? '') === 'store_manual_order' ? 'store_manual_order' : '';

    $categories = $categoryRepo->getAllBy([
        ...LoginService::getUserIdAsArray(),
        ...LoginService::getOwnerAsArray()
    ]);

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "allowPermissions" => UserPermissions::PERMISSIONS,
        "roles" => $rolesRepo->getAll(),
        "categories" => $categories,
        "candidate" => $candidate,
        "newClientForEstimate" => $newClientForEstimate,
        "returnTo" => $returnTo,
        "team_contract_templates" => (function () {
            try {
                $sessionUser = LoginService::getSession();
                $userInstitutionService = new UserInstitutionService();
                $institutionRepo = new InstitutionProfileRepository();
                $managementContext = (new \App\Services\ManagementOwnerContextService())->resolve($sessionUser, $userInstitutionService, $institutionRepo);
                $ownerId = (int)($managementContext['owner_id'] ?? $sessionUser->getIdOwner());
                $templateRepo = new TeamMemberContractTemplatesRepository();
                return $templateRepo->hasStorage() ? $templateRepo->getAllByOwner($ownerId) : [];
            } catch (Throwable $e) {
                return [];
            }
        })(),
        'base_url' => $_ENV["APP_URL"] ?? ''
    ]);
});

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_roles') {
    header('Content-Type: application/json');
    $rolesRepo = new \App\Repositories\RolesRepository();
    $roles = $rolesRepo->getAll();
    
    echo json_encode([
        "success" => true,
        "roles" => $roles
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && isset($_POST['user_type'])) {
    try {
        $userRepo = new UserRepository();
        $userInstitutionService = new UserInstitutionService();
        $institutionRepo = new InstitutionProfileRepository();
        $email = trim($_POST["email"] ?? "");
        $userType = $_POST["user_type"] ?? "";
        
        if (empty($email) || empty($userType)) {
            header('Content-Type: application/json');
            echo json_encode([
                "success" => false,
                "message" => "Email and user type are required"
            ]);
            exit;
        }
        
        // Validar formato de email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header('Content-Type: application/json');
            echo json_encode([
                "success" => false,
                "message" => "Please enter a valid email address"
            ]);
            exit;
        }
        
        $existing = $userRepo->getOneWithoutOwnership(["email" => $email, "is_active" => 1]);
        
        if ($existing) {
            if ((int)$existing->level === 5 && $userType === "5") {
                $assocRepo = new ClientsUsersRepository();
                $sessionUser = LoginService::getSession();
                $checkOwnerId = null;
                
                if ($sessionUser->getLevel() === 4) {
                    $currentInstitutionId = $_SESSION['current_institution_id'] ?? null;
                    if ($currentInstitutionId) {
                        $currentInstitution = $institutionRepo->getById($currentInstitutionId);
                        $checkOwnerId = $currentInstitution ? $currentInstitution->id_owner : null;
                    }
                    
                    if (!$checkOwnerId) {
                        $primaryInstitution = $userInstitutionService->getUserPrimaryInstitution($sessionUser->getId());
                        if ($primaryInstitution) {
                            $currentInstitution = $institutionRepo->getById($primaryInstitution->institution_id);
                            $checkOwnerId = $currentInstitution ? $currentInstitution->id_owner : null;
                        }
                    }
                } else {
                    $managementContext = (new \App\Services\ManagementOwnerContextService())->resolve($sessionUser, $userInstitutionService, $institutionRepo);
                    $checkOwnerId = $managementContext['owner_id'] ?? $sessionUser->getId();
                }
                
                $isAssociated = $assocRepo->getOne([
                    "client_id" => $existing->id,
                    "id_owner_asociated" => $checkOwnerId
                ]);
                
                if ($isAssociated) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        "success" => false,
                        "exists" => true,
                        "user_type" => "client",
                        "already_associated" => true,
                        "message" => "This client is already associated to your account."
                    ]);
                    exit;
                }
                
                header('Content-Type: application/json');
                echo json_encode([
                    "success" => false,
                    "exists" => true,
                    "user_type" => "client",
                    "message" => (int)($existing->password_updated ?? 0) === 0
                        ? "This client already exists and has not validated access yet. You can associate the client with your business, then edit the pending profile or unlink it later. Unlinking never deletes the global account or its history."
                        : "This client already exists. You can associate the client with your business. The client keeps ownership of the global account and history; removing the association later will not delete either.",
                    "access_validated" => (int)($existing->password_updated ?? 0) === 1,
                    "client_data" => [
                        "id" => $existing->id,
                        "name" => $existing->name,
                        "lastname" => $existing->lastname,
                        "email" => $existing->email,
                        "phone" => $existing->phone
                    ]
                ]);
                exit;
            } elseif ((int)$existing->level === 4 && $userType === "4") {
                $sessionUser = LoginService::getSession();
                $institutionOwnerId = null;
                
                if ($sessionUser->getLevel() === 4) {
                    $currentInstitutionId = $_SESSION['current_institution_id'] ?? null;
                    if ($currentInstitutionId) {
                        $currentInstitution = $institutionRepo->getById($currentInstitutionId);
                        $institutionOwnerId = $currentInstitution ? $currentInstitution->id_owner : null;
                    }
                    
                    if (!$institutionOwnerId) {
                        $primaryInstitution = $userInstitutionService->getUserPrimaryInstitution($sessionUser->getId());
                        if ($primaryInstitution) {
                            $currentInstitution = $institutionRepo->getById($primaryInstitution->institution_id);
                            $institutionOwnerId = $currentInstitution ? $currentInstitution->id_owner : null;
                        }
                    }
                } else {
                    $managementContext = (new \App\Services\ManagementOwnerContextService())->resolve($sessionUser, $userInstitutionService, $institutionRepo);
                    $institutionOwnerId = $managementContext['owner_id'] ?? $sessionUser->getIdOwner();
                }
                
                if ($institutionOwnerId) {
                    $currentInstitution = $institutionRepo->getByOwner($institutionOwnerId);
                    if ($currentInstitution && $userInstitutionService->userBelongsToInstitution($existing->id, $currentInstitution->id)) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            "success" => false,
                            "exists" => true,
                            "user_type" => "team_member",
                            "already_associated" => true,
                            "message" => "This team member is already associated with your institution."
                        ]);
                        exit;
                    }
                }
                
                header('Content-Type: application/json');
                echo json_encode([
                    "success" => false,
                    "exists" => true,
                    "user_type" => "team_member",
                    "message" => "This team member already exists and may work for multiple businesses. Link the member to this business and assign a role, rate, contract and permissions that apply only here.",
                    "user_data" => [
                        "id" => $existing->id,
                        "name" => $existing->name,
                        "lastname" => $existing->lastname,
                        "email" => $existing->email,
                        "phone" => $existing->phone
                    ]
                ]);
                exit;
            } else {
                header('Content-Type: application/json');
                echo json_encode([
                    "success" => false,
                    "exists" => true,
                    "user_type" => "other",
                    "message" => "A user with this email already exists with a different user type."
                ]);
                exit;
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            "success" => true,
            "message" => "Email is available"
        ]);
        exit;
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            "success" => false,
            "message" => "Error validating email: " . $e->getMessage()
        ]);
        exit;
    }
}

$router->post(function () {
    $userRepo = new UserRepository();
    $permissionsRepo = new UserFeaturePermissionsRepository();
    $roleRepo = new UserRolesRepository();
    $assocRepo = new ClientsUsersRepository();
    $leadRepo = new CrmLeadRepository();
    $sessionUser = LoginService::getSession();
    $userInstitutionService = new UserInstitutionService();
    $institutionRepo = new InstitutionProfileRepository();
    $returnTo = ($_POST['return_to'] ?? '') === 'store_manual_order' ? 'store_manual_order' : '';

    $currentOwnerId = null;
    $currentInstitutionId = null;
    
    $managementContext = (new \App\Services\ManagementOwnerContextService())->resolve($sessionUser, $userInstitutionService, $institutionRepo);
    $currentOwnerId = $managementContext['owner_id'];
    $currentInstitutionId = $managementContext['institution_id'];
    $currentInstitution = $managementContext['institution'];

    if (!$currentInstitution) {
        MessageUtil::setMessage("Error: You must create an institution profile before creating users. Please complete your institution profile first.");
        LocationUtils::redirectInternal("panel/planner-hub/institution-profile");
    }

    // Validar formato de email antes de procesar
    $email = trim($_POST["email"] ?? "");
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        MessageUtil::setMessage("Error: Please enter a valid email address.");
        LocationUtils::reload();
    }
    
    $passIn = trim((string) ($_POST["password"] ?? ""));
    $passConfirmIn = trim((string) ($_POST["password_confirm"] ?? ""));
    $defaultPlain = "12345";

    if ($passIn === "" && $passConfirmIn === "") {
        $password = $defaultPlain;
        $passwordConfirm = $defaultPlain;
        $temporaryPassword = $password;
    } elseif ($passIn !== "" && $passConfirmIn !== "") {
        $password = $passIn;
        $passwordConfirm = $passConfirmIn;
        $temporaryPassword = $password;
    } else {
        MessageUtil::setMessage("Error: Enter both password and confirmation, or leave both empty to use the default (12345, digits 1–5).");
        LocationUtils::reload();
    }
    if (!empty($_POST["associate_client_id"])) {
        $clientId = (int)$_POST["associate_client_id"];
        $assocRepo->create($clientId, $currentOwnerId);
        MessageUtil::setMessage("Client associated successfully.");
        if ($returnTo === 'store_manual_order') {
            LocationUtils::redirectInternal("panel/planner-hub/store/orders/manual?client_id={$clientId}");
        }
        LocationUtils::redirectInternal("panel/planner-hub/management/users");
    }

    if (!empty($_POST["link_existing_user_id"])) {
        $existingUserId = (int)$_POST["link_existing_user_id"];
        $linkRoleId = isset($_POST["link_role_id"]) && $_POST["link_role_id"] !== '' ? (int)$_POST["link_role_id"] : null;
        $linkHourlyRate = isset($_POST["link_hourly_rate"]) && $_POST["link_hourly_rate"] !== '' && is_numeric($_POST["link_hourly_rate"]) ? floatval($_POST["link_hourly_rate"]) : null;
        $linkContractDetail = isset($_POST["link_contract_detail"]) && !empty($_POST["link_contract_detail"]) ? $_POST["link_contract_detail"] : null;
        $linkContractTemplateId = isset($_POST["link_contract_template_id"]) && $_POST["link_contract_template_id"] !== '' ? (int)$_POST["link_contract_template_id"] : 0;
        
        if (!$linkRoleId) {
            MessageUtil::setMessage("Error: Role is required when linking a team member.");
            LocationUtils::redirectInternal("panel/planner-hub/management/users");
        }
        
        if (!$linkHourlyRate || $linkHourlyRate <= 0) {
            MessageUtil::setMessage("Error: Valid hourly rate is required when linking a team member.");
            LocationUtils::redirectInternal("panel/planner-hub/management/users");
        }
        
        if (!$currentInstitutionId) {
            MessageUtil::setMessage("Error: No institution found for current user.");
            LocationUtils::redirectInternal("panel/planner-hub/management/users");
        }
        
        $success = $userInstitutionService->linkExistingUserToInstitution($existingUserId, $currentInstitutionId, $linkRoleId, $linkHourlyRate, $linkContractDetail);
        
        if ($success) {
            $contractAssigned = $linkContractTemplateId > 0
                ? assignTeamMemberContractIfRequested($existingUserId, $currentOwnerId, (int)$sessionUser->getId(), $linkContractTemplateId)
                : false;
            if ($linkRoleId || $linkHourlyRate) {
                MessageUtil::setMessage($contractAssigned
                    ? "User linked with role, hourly rate, and contract assigned for signature."
                    : "User linked to your institution successfully with role and hourly rate assigned.");
            } else {
                MessageUtil::setMessage("User linked to your institution successfully.");
            }
        } else {
            MessageUtil::setMessage("Error linking user to your institution.");
        }
        
        LocationUtils::redirectInternal("panel/planner-hub/management/users");
    }

    if ($password !== $passwordConfirm) {
        return Response::createResponse("Passwords must match");
    }
    
    $hourlyRate = null;
    if ($_POST["level"] == 4 && isset($_POST["hourly_rate"]) && is_numeric($_POST["hourly_rate"])) {
        $hourlyRate = floatval($_POST["hourly_rate"]);
    }
    
    $inactiveUser = $userRepo->findInactiveByEmail($_POST["email"]);
    if ($inactiveUser) {
        if ((int)$inactiveUser->level === 4) {
            $roleId = isset($_POST["role_id"]) && $_POST["role_id"] !== '' ? (int) $_POST["role_id"] : null;
            $contractDetail = isset($_POST["contract_detail"]) && $_POST["contract_detail"] !== '' ? $_POST["contract_detail"] : null;
            $contractTemplateId = isset($_POST["contract_template_id"]) && $_POST["contract_template_id"] !== '' ? (int) $_POST["contract_template_id"] : 0;

            $userRepo->reactivateUser($inactiveUser->id, $currentOwnerId, [
                "name" => $_POST["name"],
                "lastname" => $_POST["lastname"],
                "phone" => FormatPhone::formatPhone($_POST["phone"]),
                "password" => HashService::hashPassword($password),
                "password_updated" => 1,
                "hourly_rate" => $hourlyRate
            ]);

            if ($currentInstitutionId) {
                $userInstitutionsRepo = new UserInstitutionsRepository();
                $existingInstitutionRecord = $userInstitutionsRepo->getUserInstitutionRecord($inactiveUser->id, $currentInstitutionId);

                $institutionUpdateData = [];
                if ($roleId !== null) {
                    $institutionUpdateData["role_id"] = $roleId;
                }
                if ($hourlyRate !== null) {
                    $institutionUpdateData["hourly_rate"] = $hourlyRate;
                }
                if ($contractDetail !== null) {
                    $institutionUpdateData["contract_detail"] = $contractDetail;
                }

                $reactivated = false;
                if ($existingInstitutionRecord) {
                    $reactivated = $userInstitutionsRepo->reactivateUserInstitutionForInstitution(
                        (int) $inactiveUser->id,
                        (int) $currentInstitutionId,
                        $institutionUpdateData
                    );
                }

                if (!$reactivated) {
                    $userInstitutionService->addUserToInstitution(
                        (int) $inactiveUser->id,
                        (int) $currentInstitutionId,
                        $roleId,
                        $hourlyRate,
                        $contractDetail
                    );
                }
            }
            
            sendSecureAccountInvitation($_POST["email"], $_POST["name"], $_POST["level"]);
            $contractAssigned = $contractTemplateId > 0
                ? assignTeamMemberContractIfRequested((int)$inactiveUser->id, $currentOwnerId, (int)$sessionUser->getId(), $contractTemplateId)
                : false;
            MessageUtil::setMessage($contractAssigned
                ? "Team member reactivated, assigned to you, and contract assigned for signature."
                : "Team member reactivated and assigned to you.");
        } elseif ((int)$inactiveUser->level === 5) {
            $userRepo->reactivateUser($inactiveUser->id, 1, [
                "name" => $_POST["name"],
                "lastname" => $_POST["lastname"],
                "phone" => FormatPhone::formatPhone($_POST["phone"]),
                "password" => HashService::hashPassword($password),
                "password_updated" => 1
            ]);
            $assocRepo->create($inactiveUser->id, $currentOwnerId);
            
            sendSecureAccountInvitation($_POST["email"], $_POST["name"], $_POST["level"]);
            MessageUtil::setMessage("Client reactivated and associated to your account.");
        }
        LocationUtils::redirectInternal("panel/planner-hub/management/users");
    }

    $existing = $userRepo->getOne(["email" => $_POST["email"], "is_active" => 1]);
    if ($existing) {
        if ((int)$existing->level === 5) {
            $_SESSION["client_candidate"] = [
                "name" => $existing->name,
                "lastname" => $existing->lastname,
                "email" => $existing->email,
                "phone" => $existing->phone,
                "client_id" => $existing->id
            ];
            MessageUtil::setMessage("Client already exists. You can associate it to your account.");
            LocationUtils::redirectInternal("panel/planner-hub/management/users/create");
        } else {
            $msg = ((int)$existing->level === 4)
                ? "Team member with this email already exists."
                : "A user with this email already exists.";
            MessageUtil::setMessage($msg);
            LocationUtils::reload();
        }
    }

    $ownerForInsert = ($_POST["level"] == 5) ? $currentOwnerId : $currentOwnerId;

    $userRepo->add([
        "name" => $_POST["name"],
        "lastname" => $_POST["lastname"],
        "email" => $_POST["email"],
        "password" => HashService::hashPassword($password),
        "password_updated" => 1,
        "phone" => FormatPhone::formatPhone($_POST["phone"]),
        "phone_validation" => 1,
        "phone_code" => '',
        "membership_due_date" => null,
        "level" => intval($_POST["level"]),
        "id_owner" => $ownerForInsert
    ]);

    $userId = $userRepo->getLastId();
    
    sendSecureAccountInvitation($_POST["email"], $_POST["name"], $_POST["level"]);

    if (!$currentInstitutionId) {
        MessageUtil::setMessage("Error: No institution found for current user.");
        LocationUtils::redirectInternal("panel/planner-hub/management/users");
    }

    if ($_POST["level"] == 4) {
        $roleId = isset($_POST["role_id"]) && !empty($_POST["role_id"]) ? (int) $_POST["role_id"] : null;
        $institutionHourlyRate = $hourlyRate;
        $contractDetail = isset($_POST["contract_detail"]) && !empty($_POST["contract_detail"]) ? $_POST["contract_detail"] : null;
        $contractTemplateId = isset($_POST["contract_template_id"]) && !empty($_POST["contract_template_id"]) ? (int) $_POST["contract_template_id"] : 0;
        
        $userInstitutionService->addUserToInstitution($userId, $currentInstitutionId, $roleId, $institutionHourlyRate, $contractDetail);
        if ($contractTemplateId > 0) {
            assignTeamMemberContractIfRequested((int)$userId, $currentOwnerId, (int)$sessionUser->getId(), $contractTemplateId);
        }
    }

    if ($_POST["level"] == 5) {
        $assocRepo->create($userId, $currentOwnerId);
        
        if (isset($_POST["add_to_crm"]) && $_POST["add_to_crm"] === "1") {
            $address = $_POST["crm_address"] ?? "";
            $categoryId = $_POST["crm_category_id"] ?? null;
            
            if ($categoryId === "new") {
                $categoryName = $_POST["new_category_name"] ?? "New Category";
                $categoryRepo = new CrmCategoryRepository();
                
                $success = $categoryRepo->add([
                    "name" => $categoryName,
                    ...LoginService::getUserIdAsArray(true),
                    ...LoginService::getOwnerAsArray()
                ]);
                
                if ($success) {
                    $categoryId = $categoryRepo->getLastId();
                } else {
                    MessageUtil::setMessage("User created successfully. Note: CRM lead not created due to error creating category.");
                    LocationUtils::redirectInternal("panel/planner-hub/management/users");
                }
            }
            
            if ($categoryId && $categoryId !== "new") {
                $leadRepo->add([
                    "name" => $_POST["name"] . " " . $_POST["lastname"],
                    "email" => $_POST["email"],
                    "phone" => FormatPhone::formatPhone($_POST["phone"]),
                    "address" => $address,
                    "id_category" => $categoryId,
                    "id_status" => 1,
                    ...LoginService::getUserIdAsArray(true),
                    ...LoginService::getOwnerAsArray()
                ]);
                MessageUtil::setMessage("User created successfully and added to CRM.");
            } else {
                MessageUtil::setMessage("User created successfully. Note: CRM lead not created due to missing category.");
            }
        } else {
            MessageUtil::setMessage("User created successfully.");
        }
        if ($returnTo === 'store_manual_order') {
            MessageUtil::setMessage("Client created successfully. Continue creating the Store order.");
            LocationUtils::redirectInternal("panel/planner-hub/store/orders/manual?client_id={$userId}");
        }
        $_SESSION["new_client_for_estimate"] = [
            "id" => $userId,
            "name" => $_POST["name"],
            "lastname" => $_POST["lastname"],
            "email" => $_POST["email"]
        ];
        LocationUtils::redirectInternal("panel/planner-hub/management/users/create");
    } elseif ($_POST["level"] == 4) {
        if (isset($_POST["role_id"]) && !empty($_POST["role_id"])) {
            MessageUtil::setMessage(!empty($_POST["contract_template_id"])
                ? "Team member created with role and contract assigned for signature."
                : "Team member created successfully with role assigned.");
        } else {
            MessageUtil::setMessage("Team member created successfully.");
        }
    } else {
        MessageUtil::setMessage("User created successfully.");
    }

    LocationUtils::redirectInternal("panel/planner-hub/management/users");
});

$router->run();
