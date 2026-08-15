<?php

use App\Repositories\UserRepository;
use App\Repositories\ClientsUsersRepository;
use App\Services\LoginService;
use App\Services\UserInstitutionService;
use App\Services\UserDeactivationService;
use App\Services\EmailService;
use App\Utils\TemplateResponse;
use App\Utils\Router;
use App\Utils\LocationUtils;
use App\Utils\MessageUtil;

$router = new Router();

$router->get(function () {
    $user = LoginService::getSession();
    if (!$user) {
        LocationUtils::redirectInternal("login");
    }

    $repo = new UserRepository();
    $clientsUsersRepo = new ClientsUsersRepository();
    $userInstitutionService = new UserInstitutionService();
    
    $filters = [];
    
    $activeTab = $_GET["active_tab"] ?? "members";
    
    $memberName = $_GET["member_name"] ?? null;
    $memberEmail = $_GET["member_email"] ?? null;
    $clientName = $_GET["client_name"] ?? null;
    $clientEmail = $_GET["client_email"] ?? null;

    if ($activeTab === "members") {
        if ($memberName) {
            $filters["name"] = $memberName;
        }
        if ($memberEmail) {
            $filters["email"] = $memberEmail;
        }
    } elseif ($activeTab === "clients") {
        if ($clientName) {
            $filters["name"] = $clientName;
        }
        if ($clientEmail) {
            $filters["email"] = $clientEmail;
        }
    }

    $institutionRepo = new \App\Repositories\InstitutionProfileRepository();
    
    $institutionId = null;
    $currentOwnerId = null;
    
    $managementContext = (new \App\Services\ManagementOwnerContextService())->resolve($user, $userInstitutionService, $institutionRepo);
    $institutionId = $managementContext['institution_id'];
    $currentOwnerId = $managementContext['owner_id'];
    
    $teamMembers = [];
    $clients = [];
    
    if ($institutionId && $currentOwnerId) {
        $userInstitutionsRepo = new \App\Repositories\UserInstitutionsRepository();
        
        // Get team members from user_institutions (level 4 users linked to this institution)
        $memberFilters = $filters;
        $memberFilters["level"] = 4;
        $linkedTeamMembers = $userInstitutionsRepo->getUsersForInstitution($institutionId, $memberFilters);
        
        // Get team members directly owned by the institution owner
        $directTeamMembers = $repo->getAllFlexible(array_merge($filters, [
            "id_owner" => $currentOwnerId,
            "level" => 4
        ]));
        
        // Add user_institutions data to direct team members
        foreach ($directTeamMembers as $member) {
            $userInstitutionRecord = $userInstitutionsRepo->getUserInstitutionRecord($member->id, $institutionId);
            if ($userInstitutionRecord) {
                $member->institution_role_id = $userInstitutionRecord->role_id;
                $member->institution_hourly_rate = $userInstitutionRecord->hourly_rate;
                $member->role_name = $userInstitutionRecord->role_name ?? null;
                $member->institution_relationship = $userInstitutionRecord->secondary_institution_id ? 'secondary' : 'primary';
                $teamMembers[] = $member;
            }
        }

        // Merge and remove duplicates based on user ID
        $allTeamMembers = array_merge($linkedTeamMembers, $teamMembers);
        $teamMembersById = [];
        foreach ($allTeamMembers as $member) {
            $teamMembersById[$member->id] = $member;
        }
        $teamMembers = array_values($teamMembersById);

        $contractRepo = new \App\Repositories\TeamMemberContractsRepository();
        $contractStatuses = $contractRepo->getLatestByMembers(array_map(fn($member) => (int)$member->id, $teamMembers), $currentOwnerId);
        foreach ($teamMembers as $member) {
            $member->team_contract = $contractStatuses[(int)$member->id] ?? null;
        }
        
        $clients = $clientsUsersRepo->getClientsByOwner($currentOwnerId, $filters);
        
        $currentInstitution = $userInstitutionService->getCurrentInstitutionContext($user->getId());
    } else {
        $currentInstitution = null;
    }

    $availableInstitutions = $userInstitutionService->getUserAvailableInstitutions($user->getId());

    if (isset($_GET["export"]) && in_array($_GET["export"], ["members", "clients"], true)) {
        $type = $_GET["export"];
        $format = $_GET["format"] ?? "csv";

        $baseName = "users_export_" . $type . "_" . date("Ymd_His");

        if ($format === "excel") {
            $filename = $baseName . ".xls";
            header("Content-Type: application/vnd.ms-excel; charset=utf-8");
            header("Content-Disposition: attachment; filename={$filename}");
        } else {
            $filename = $baseName . ".csv";
            header("Content-Type: text/csv; charset=utf-8");
            header("Content-Disposition: attachment; filename={$filename}");
        }

        $output = fopen("php://output", "w");

        if ($type === "members") {
            $headerRow = ["ID", "Name", "Email", "Phone", "Role", "Hourly Rate"];
            if ($format === "excel") {
                echo implode("\t", $headerRow) . "\r\n";
            } else {
                fputcsv($output, $headerRow);
            }

            foreach ($teamMembers as $member) {
                $roleName = $member->role_name ?? "";
                $hourlyRate = isset($member->institution_hourly_rate) ? $member->institution_hourly_rate : "";
                $row = [
                    $member->id ?? "",
                    trim(($member->name ?? "") . " " . ($member->lastname ?? "")),
                    $member->email ?? "",
                    $member->phone ?? "",
                    $roleName,
                    $hourlyRate
                ];

                if ($format === "excel") {
                    echo implode("\t", $row) . "\r\n";
                } else {
                    fputcsv($output, $row);
                }
            }
        } else {
            $headerRow = ["ID", "Name", "Email", "Phone"];
            if ($format === "excel") {
                echo implode("\t", $headerRow) . "\r\n";
            } else {
                fputcsv($output, $headerRow);
            }

            foreach ($clients as $client) {
                $row = [
                    $client->id ?? "",
                    trim(($client->name ?? "") . " " . ($client->lastname ?? "")),
                    $client->email ?? "",
                    $client->phone ?? ""
                ];

                if ($format === "excel") {
                    echo implode("\t", $row) . "\r\n";
                } else {
                    fputcsv($output, $row);
                }
            }
        }

        if ($format !== "excel") {
            fclose($output);
        }
        exit;
    }

    return TemplateResponse::render(__DIR__ . "/index.twig", [
        "team_members" => $teamMembers,
        "clients" => $clients,
        "user" => $user,
        "current_user" => $user,
        "active_tab" => $activeTab,
        "filter_member_name" => $memberName,
        "filter_member_email" => $memberEmail,
        "filter_client_name" => $clientName,
        "filter_client_email" => $clientEmail,
        "current_institution" => $currentInstitution,
        "available_institutions" => $availableInstitutions
    ]);
});

$router->post(function () {
    $repo = new UserRepository();
    $clientsUsersRepo = new ClientsUsersRepository();
    $userInstitutionService = new UserInstitutionService();
    $userDeactivationService = new UserDeactivationService();
    $user = LoginService::getSession();
    $currentInstitutionId = $_SESSION['current_institution_id'] ?? null;

    if (isset($_POST["switch_institution"])) {
        $institutionId = (int) $_POST["switch_institution"];
        $result = $userInstitutionService->switchInstitutionContext($user->getId(), $institutionId);
        
        if ($result['success']) {
            MessageUtil::setMessage("Institution context switched successfully.");
        } else {
            MessageUtil::setMessage("Error switching institution: " . $result['message']);
        }
        LocationUtils::reload();
    }

    $institutionRepo = new \App\Repositories\InstitutionProfileRepository();
    $managementContext = (new \App\Services\ManagementOwnerContextService())->resolve($user, $userInstitutionService, $institutionRepo);
    $currentOwnerId = (int) ($managementContext['owner_id'] ?? 0);
    if (!$currentInstitutionId) {
        $currentInstitutionId = $managementContext['institution_id'] ?? null;
    }

    if (isset($_POST["unlink_client"])) {
        $clientId = (int) $_POST["unlink_client"];
        $clientsUsersRepo->deleteRelation($clientId, $currentOwnerId);
        MessageUtil::setMessage("Client unlinked successfully.");
        LocationUtils::reload();
    }

    if (isset($_POST["delete_client"])) {
        $clientId = (int) $_POST["delete_client"];
        $targetClient = $repo->getOne(["id" => $clientId]);
        
        if ($targetClient) {
            if ($targetClient->password_updated == 0) {
                $clientsUsersRepo->deleteRelation($clientId, $currentOwnerId);
                $repo->delete_level_5(["id" => $clientId]);
                MessageUtil::setMessage("Client completely deleted successfully (unvalidated user).");
            } else {
                $clientsUsersRepo->deleteRelation($clientId, $currentOwnerId);
                MessageUtil::setMessage("Client unlinked from your account.");
            }
        } else {
            MessageUtil::setMessage("Client not found.");
        }
        LocationUtils::reload();
    }

    if (isset($_POST["deactivate_user"])) {
        $userId = (int) $_POST["deactivate_user"];
        $reason = $_POST["deactivation_reason"] ?? null;
        
        if ($currentInstitutionId) {
            $result = $userDeactivationService->deactivateUserFromInstitution($userId, $currentInstitutionId, $user->getId(), $reason);
            
            if ($result['success']) {
                MessageUtil::setMessage("User deactivated from institution successfully.");
            } else {
                MessageUtil::setMessage("Error deactivating user: " . $result['message']);
            }
        } else {
            MessageUtil::setMessage("No institution context selected.");
        }
        LocationUtils::reload();
    }

    if (isset($_POST["toggle_chat"])) {
        $targetId = (int) $_POST["toggle_chat"];
        $target = $repo->getOne(["id" => $targetId]);

        if ($target && $target->level == 4) {
            $newValue = $target->allow_chat_with_clients ? 0 : 1;
            $repo->update([
                "allow_chat_with_clients" => $newValue
            ], [
                "id" => $targetId
            ]);
            MessageUtil::setMessage("🔄Status updated.");
        }

        LocationUtils::reload();
    }

    if (isset($_POST["id"])) {
        $id = (int) $_POST["id"];
        if ($user->getLevel() === 4) {
            MessageUtil::setMessage("This action is only allowed for administrator users.");
            LocationUtils::reload();
        }
        
        $userInstitution = $institutionRepo->getByOwner($currentOwnerId);
        $currentInstitutionId = $userInstitution ? $userInstitution->id : null;
        
        if ($currentInstitutionId) {
            $userInstitutionsRepo = new \App\Repositories\UserInstitutionsRepository();
            
            $targetUser = $repo->getOne(["id" => $id]);
            
            if ($targetUser) {
                $userInstitutionRecord = $userInstitutionsRepo->getUserInstitutionRecord($id, $currentInstitutionId);

                if ((int) $targetUser->level === 4) {
                    if ($userInstitutionRecord) {
                        $userInstitutionsRepo->removeUserFromInstitution($id, $currentInstitutionId);
                        MessageUtil::setMessage("Team member unlinked from institution successfully.");
                    } else {
                        MessageUtil::setMessage("Team member not linked to this institution.");
                    }
                } elseif ((int) $targetUser->level === 5) {
                    if ($userInstitutionRecord) {
                        $userInstitutionsRepo->removeUserFromInstitution($id, $currentInstitutionId);
                    }
                    $repo->delete_level_5(["id" => $id]);
                    MessageUtil::setMessage("Client association removed successfully.");
                } else {
                    if ($userInstitutionRecord) {
                        $userInstitutionsRepo->removeUserFromInstitution($id, $currentInstitutionId);
                        MessageUtil::setMessage("User unlinked from institution successfully.");
                    } else {
                        MessageUtil::setMessage("User not found in current institution.");
                    }
                }
            } else {
                MessageUtil::setMessage("User not found.");
            }
        } else {
            MessageUtil::setMessage("No institution context found.");
        }
        
        LocationUtils::reload();
    }

    if (isset($_POST["resend_email"])) {
        $userId = (int) $_POST["resend_email"];
        $targetUser = $repo->getOne(["id" => $userId]);
        
        if ($targetUser) {
            $temporaryPassword = generateTemporaryPassword();
            $hashedPassword = \App\Services\HashService::hashPassword($temporaryPassword);
            
            $repo->update([
                "password" => $hashedPassword,
                "password_updated" => 1
            ], ["id" => $userId]);
            
            $subject = "Your Temporary Password - VNV Venue";
            $message = "
                <h2>Welcome to VNV Venue!</h2>
                <p>Hello {$targetUser->name},</p>
                <p>A new temporary password has been generated for your account:</p>
                <p><strong>Email:</strong> {$targetUser->email}</p>
                <p><strong>Temporary Password:</strong> {$temporaryPassword}</p>
                <p>Please log in using these credentials and set your permanent password.</p>
                <p><a href='" . \App\Utils\LocationUtils::getBasePath() . "/login' style='background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Login Now</a></p>
                <p>Best regards,<br>VNV Venue Team</p>
            ";
            
            $ownerId = LoginService::getSession() ? LoginService::getSession()->getOwner() : null;
            $emailService = new EmailService($ownerId);
            $emailService->sendSimpleEmail($targetUser->email, $subject, $message, true);
            
            MessageUtil::setMessage("Temporary password email sent successfully to {$targetUser->email}.");
        } else {
            MessageUtil::setMessage("User not found.");
        }
        
        LocationUtils::reload();
    }
});

function generateTemporaryPassword() {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $password = '';
    for ($i = 0; $i < 12; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

$router->run();
