<?php

namespace App\Services;

use App\Repositories\InstitutionProfileRepository;

class ManagementOwnerContextService
{
    public function resolve($sessionUser, UserInstitutionService $userInstitutionService, InstitutionProfileRepository $institutionRepo): array
    {
        $institutionId = null;
        $ownerId = null;
        $institution = null;

        if (!$sessionUser) {
            return [
                'institution_id' => null,
                'owner_id' => null,
                'institution' => null,
            ];
        }

        if ((int) $sessionUser->getLevel() === 4) {
            $institutionId = $_SESSION['current_institution_id'] ?? null;
            if ($institutionId) {
                $institution = $institutionRepo->getById($institutionId);
                $ownerId = $institution ? $institution->id_owner : null;
            }

            if (!$institutionId || !$institution) {
                $primaryInstitution = $userInstitutionService->getUserPrimaryInstitution($sessionUser->getId());
                if ($primaryInstitution) {
                    $institutionId = $primaryInstitution->institution_id;
                    $institution = $institutionRepo->getById($institutionId);
                    $ownerId = $institution ? $institution->id_owner : null;
                }
            }
        } else {
            $ownerId = (int) $sessionUser->getId();

            if ((int) $sessionUser->getLevel() === 1) {
                $operationOwnerId = (new CentralOperationsContextService())->getOwnerId($sessionUser);
                if ($operationOwnerId > 0) {
                    $ownerId = $operationOwnerId;
                }
            }

            $institution = $institutionRepo->getByOwner($ownerId);
            $institutionId = $institution ? $institution->id : null;
        }

        return [
            'institution_id' => $institutionId,
            'owner_id' => $ownerId,
            'institution' => $institution,
        ];
    }
}
