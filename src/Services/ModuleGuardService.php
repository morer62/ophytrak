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

        $ownerId = (int)($user->getOwner() ?: $user->getId());
        $moduleAccessService = new ModuleAccessService();

        if ($moduleAccessService->canAccessAnyRouteModule($ownerId, $moduleSlugs)) {
            return;
        }

        MessageUtil::setMessage('This module is locked until activation.', 'Module locked', 'warning');
        $moduleParam = urlencode((string)($requestedModule ?: ($moduleSlugs[0] ?? 'module')));
        $requiredParam = urlencode(implode(',', array_map('strval', $moduleSlugs)));
        LocationUtils::redirectInternal("panel/planner-hub/no-access?module={$moduleParam}&required={$requiredParam}");
    }
}
