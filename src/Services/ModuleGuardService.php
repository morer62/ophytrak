<?php

namespace App\Services;

use App\Utils\LocationUtils;
use App\Utils\MessageUtil;

class ModuleGuardService
{
    public static function requireModule(string $moduleSlug, string $redirectPath = 'panel/home'): void
    {
        self::requireAnyModule([$moduleSlug], $redirectPath, $moduleSlug);
    }

    public static function requireAnyModule(array $moduleSlugs, string $redirectPath = 'panel/home', ?string $requestedModule = null): void
    {
        $user = LoginService::getSession();

        if (!$user) {
            LocationUtils::redirectInternal($redirectPath);
        }

        if ((int)$user->getLevel() === 1) {
            return;
        }

        $ownerId = self::resolveModuleOwnerId($user);
        if (ProductProfileService::isOphytrack() && filter_var($_ENV['OPHYTRACK_USAGE_BLOCK_OVERDUE'] ?? true, FILTER_VALIDATE_BOOL)) {
            $billing = new \App\Repositories\OphytrackPackageBillingRepository();
            if ($billing->isReady() && (float)($billing->summary($ownerId)['overdue'] ?? 0) > 0) {
                MessageUtil::setMessage('Pay the overdue OPHYTRACK balance to restore operational access.', 'Payment required', 'warning');
                LocationUtils::redirectInternal('panel/ophytrack/payments');
            }
        }
        $moduleAccessService = new ModuleAccessService();

        if ($moduleAccessService->canAccessAnyRouteModule($ownerId, $moduleSlugs)) {
            return;
        }

        MessageUtil::setMessage('This module is locked until activation.', 'Module locked', 'warning');
        $moduleParam = urlencode((string)($requestedModule ?: ($moduleSlugs[0] ?? 'module')));
        $requiredParam = urlencode(implode(',', array_map('strval', $moduleSlugs)));
        LocationUtils::redirectInternal("panel/planner-hub/no-access?module={$moduleParam}&required={$requiredParam}");
    }

    /**
     * Paid modules belong to the business, not to an employee account.
     * A Level 4 user can work for multiple companies, so id_owner alone is
     * not authoritative: the selected institution is the active billing and
     * operational context.
     */
    private static function resolveModuleOwnerId(\App\Entity\User $user): int
    {
        if ((int)$user->getLevel() === 4) {
            try {
                $teamContext = (new UserWorkspaceContextService())->getTeamContext($user);
                $selectedOwnerId = (int)($teamContext['selectedOwnerId'] ?? 0);
                if ($selectedOwnerId > 0) {
                    return $selectedOwnerId;
                }
            } catch (\Throwable $exception) {
                // Preserve the legacy relationship as a safe fallback when
                // an older installation has not created workspace tables yet.
            }
        }

        return (int)($user->getOwner() ?: $user->getId());
    }
}
