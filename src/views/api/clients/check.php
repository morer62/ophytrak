<?php

use App\Repositories\UserRepository;
use App\Repositories\ClientsUsersRepository;
use App\Services\LoginService;
use App\Utils\Cors;
use App\Utils\JsonResponse;
use App\Utils\Router;

Cors::handle();

$router = new Router();

$router->get(function () {
    $email = $_GET["email"] ?? null;
    $session = LoginService::getSession();

    if (!$email || !$session) {
        return JsonResponse::createResponse(["exists" => false]);
    }

    $checkOwnerId = $session->getIdOwner();
    
    // Para nivel 4: obtener el id_owner de la institución activa (igual que en management/users)
    if ($session->getLevel() === 4) {
        $institutionRepo = new \App\Repositories\InstitutionProfileRepository();
        $userInstitutionService = new \App\Services\UserInstitutionService();
        
        $currentInstitutionId = $_SESSION['current_institution_id'] ?? null;
        error_log("[CLIENT_CHECK] Nivel 4 - User ID: " . $session->getId());
        error_log("[CLIENT_CHECK] Nivel 4 - Initial checkOwnerId: " . $checkOwnerId);
        error_log("[CLIENT_CHECK] Nivel 4 - current_institution_id: " . ($currentInstitutionId ?? 'null'));
        
        if ($currentInstitutionId) {
            $institution = $institutionRepo->getById($currentInstitutionId);
            if ($institution && $institution->id_owner) {
                $checkOwnerId = $institution->id_owner;
                error_log("[CLIENT_CHECK] Nivel 4 - checkOwnerId from active institution: " . $checkOwnerId);
            }
        }
        
        // Si no hay institución activa, obtener la primaria
        if (!$currentInstitutionId || !$checkOwnerId) {
            $primaryInstitution = $userInstitutionService->getUserPrimaryInstitution($session->getId());
            error_log("[CLIENT_CHECK] Nivel 4 - primaryInstitution: " . ($primaryInstitution ? json_encode($primaryInstitution) : 'null'));
            if ($primaryInstitution) {
                $institution = $institutionRepo->getById($primaryInstitution->institution_id);
                if ($institution && $institution->id_owner) {
                    $checkOwnerId = $institution->id_owner;
                    error_log("[CLIENT_CHECK] Nivel 4 - checkOwnerId from primary institution: " . $checkOwnerId);
                }
            }
        }
        
        // Si aún no hay ownerId, no puede buscar clientes
        if (!$checkOwnerId) {
            error_log("[CLIENT_CHECK] Nivel 4 - No checkOwnerId found, returning false");
            return JsonResponse::createResponse(["exists" => false]);
        }
        
        error_log("[CLIENT_CHECK] Nivel 4 - Final checkOwnerId: " . $checkOwnerId);
        error_log("[CLIENT_CHECK] Nivel 4 - Searching client with email: " . $email . ", id_owner: " . $checkOwnerId);
    } else {
        error_log("[CLIENT_CHECK] Nivel " . $session->getLevel() . " - checkOwnerId: " . $checkOwnerId);
        error_log("[CLIENT_CHECK] Nivel " . $session->getLevel() . " - Searching client with email: " . $email);
    }

    $repo = new UserRepository();
    
    // Para nivel 4: buscar clientes asociados al owner en clients_users (igual que en management/users)
    // Usamos getClientsByOwner que busca en la tabla de asociaciones
    if ($session->getLevel() === 4) {
        // Primero buscar el cliente por email sin filtro de id_owner
        $client = $repo->getOneWithoutOwnership([
            "email" => $email,
            "level" => 5
        ]);
        
        if (!$client) {
            error_log("[CLIENT_CHECK] Nivel 4 - Client not found by email: " . $email);
            return JsonResponse::createResponse(["exists" => false]);
        }
        
        error_log("[CLIENT_CHECK] Nivel 4 - Client found: YES (ID: " . $client->id . ", id_owner: " . ($client->id_owner ?? 'null') . ")");
        
        // Verificar si el cliente está asociado al owner en clients_users
        $assocRepo = new ClientsUsersRepository();
        $isLinked = $assocRepo->exists($client->id, $checkOwnerId);
        
        error_log("[CLIENT_CHECK] Nivel 4 - Client linked to owner " . $checkOwnerId . ": " . ($isLinked ? "YES" : "NO"));
        
        // Si no está asociado, verificar si tiene el mismo id_owner (por compatibilidad)
        if (!$isLinked && $client->id_owner == $checkOwnerId) {
            $isLinked = true;
            error_log("[CLIENT_CHECK] Nivel 4 - Client has same id_owner, considering as linked");
        }
        
        if (!$isLinked) {
            return JsonResponse::createResponse(["exists" => false]);
        }
    } else {
        // Para otros niveles: buscar cualquier cliente nivel 5
        $client = $repo->getOne([
            "email" => $email,
            "level" => 5
        ]);

        if (!$client) {
            return JsonResponse::createResponse(["exists" => false]);
        }

        $assocRepo = new ClientsUsersRepository();
        $isLinked = $assocRepo->exists($client->id, $checkOwnerId);
    }

    return JsonResponse::createResponse([
        "exists" => true,
        "is_linked" => $isLinked,
        "id" => $client->id,
        "name" => $client->name . " " . $client->lastname,
        "email" => $client->email,
        "phone" => $client->phone
    ]);
});



$router->run();
