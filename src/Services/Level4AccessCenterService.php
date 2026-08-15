<?php

namespace App\Services;

use App\Entity\User;
use App\Repositories\UserFeaturePermissionsRepository;
use App\Repositories\UserRolesRepository;

class Level4AccessCenterService
{
    private UserRolesRepository $rolesRepo;
    private UserFeaturePermissionsRepository $featurePermissionsRepo;

    public function __construct()
    {
        $this->rolesRepo = new UserRolesRepository();
        $this->featurePermissionsRepo = new UserFeaturePermissionsRepository();
    }

    public function build(User $user, array $teamContext): array
    {
        $institutionId = (int)($teamContext['selectedInstitutionId'] ?? 0);
        $permissions = $institutionId > 0
            ? $this->rolesRepo->getUserRoleAndPermissions((int)$user->getId(), $institutionId)
            : [];
        $featureSlugs = $this->featurePermissionsRepo->getSlugsByUserId((int)$user->getId());

        $permissionModules = $this->permissionModules($permissions);
        $hasInstitution = $institutionId > 0;
        $hasPlannerFeature = in_array('planner', $featureSlugs, true);

        $items = [
            [
                'group' => 'daily',
                'slug' => 'my_work',
                'title_key' => 'level4_access_center.my_work_title',
                'summary_key' => 'level4_access_center.my_work_summary',
                'icon' => 'check-square',
                'url' => 'panel/planner-hub/team/my-work',
                'active' => $hasInstitution,
                'reason_key' => 'level4_access_center.no_company_reason',
            ],
            [
                'group' => 'daily',
                'slug' => 'assigned_orders',
                'title_key' => 'level4_access_center.assigned_orders_title',
                'summary_key' => 'level4_access_center.assigned_orders_summary',
                'icon' => 'calendar',
                'url' => 'panel/planner-hub/team/orders/orders',
                'active' => $hasInstitution && ($hasPlannerFeature || in_array('orders', $permissionModules, true)),
                'reason_key' => 'level4_access_center.orders_locked_reason',
            ],
            [
                'group' => 'daily',
                'slug' => 'team_chat',
                'title_key' => 'level4_access_center.team_chat_title',
                'summary_key' => 'level4_access_center.team_chat_summary',
                'icon' => 'message-circle',
                'url' => 'panel/planner-hub/team/chat',
                'active' => $hasInstitution,
                'reason_key' => 'level4_access_center.no_company_reason',
            ],
            [
                'group' => 'daily',
                'slug' => 'my_contract',
                'title_key' => 'level4_access_center.my_contract_title',
                'summary_key' => 'level4_access_center.my_contract_summary',
                'icon' => 'file-text',
                'url' => 'panel/planner-hub/team/contracts',
                'active' => $hasInstitution,
                'reason_key' => 'level4_access_center.no_company_reason',
            ],
            [
                'group' => 'time',
                'slug' => 'time_clock',
                'title_key' => 'level4_access_center.time_clock_title',
                'summary_key' => 'level4_access_center.time_clock_summary',
                'icon' => 'clock',
                'url' => 'panel/planner-hub/team/payroll/clock',
                'active' => $hasInstitution && in_array('payroll', $permissionModules, true),
                'reason_key' => 'level4_access_center.payroll_locked_reason',
            ],
            [
                'group' => 'time',
                'slug' => 'payroll',
                'title_key' => 'level4_access_center.payroll_title',
                'summary_key' => 'level4_access_center.payroll_summary',
                'icon' => 'dollar-sign',
                'url' => 'panel/planner-hub/team/payroll/pending',
                'active' => $hasInstitution && in_array('payroll', $permissionModules, true),
                'reason_key' => 'level4_access_center.payroll_locked_reason',
            ],
            [
                'group' => 'approved',
                'slug' => 'orders_management',
                'title_key' => 'level4_access_center.orders_management_title',
                'summary_key' => 'level4_access_center.orders_management_summary',
                'icon' => 'briefcase',
                'url' => 'panel/planner-hub/management/orders',
                'active' => in_array('orders', $permissionModules, true),
                'reason_key' => 'level4_access_center.admin_locked_reason',
            ],
            [
                'group' => 'approved',
                'slug' => 'crm',
                'title_key' => 'level4_access_center.crm_title',
                'summary_key' => 'level4_access_center.crm_summary',
                'icon' => 'users',
                'url' => 'panel/planner-hub/management/crm',
                'active' => in_array('crm', $permissionModules, true),
                'reason_key' => 'level4_access_center.admin_locked_reason',
            ],
            [
                'group' => 'approved',
                'slug' => 'storage',
                'title_key' => 'level4_access_center.storage_title',
                'summary_key' => 'level4_access_center.storage_summary',
                'icon' => 'box',
                'url' => 'panel/planner-hub/management/storage',
                'active' => in_array('storage', $permissionModules, true),
                'reason_key' => 'level4_access_center.admin_locked_reason',
            ],
            [
                'group' => 'approved',
                'slug' => 'team_users',
                'title_key' => 'level4_access_center.team_users_title',
                'summary_key' => 'level4_access_center.team_users_summary',
                'icon' => 'user-check',
                'url' => 'panel/planner-hub/management/users',
                'active' => in_array('users', $permissionModules, true),
                'reason_key' => 'level4_access_center.admin_locked_reason',
            ],
        ];

        $activeItems = array_values(array_filter($items, static fn ($item) => $item['active']));
        $lockedItems = array_values(array_filter($items, static fn ($item) => !$item['active']));

        return [
            'items' => $items,
            'activeItems' => $activeItems,
            'lockedItems' => $lockedItems,
            'activeManagementItems' => array_values(array_filter($activeItems, static fn ($item) => $item['group'] === 'approved')),
            'lockedManagementItems' => array_values(array_filter($lockedItems, static fn ($item) => $item['group'] === 'approved')),
            'permissionModules' => $permissionModules,
            'featureSlugs' => $featureSlugs,
            'hasInstitution' => $hasInstitution,
            'hasManagementAccess' => count(array_intersect($permissionModules, ['orders', 'crm', 'payroll', 'storage', 'users'])) > 0,
        ];
    }

    private function permissionModules(array $permissions): array
    {
        $modules = [];
        foreach ($permissions as $permission) {
            $modules[] = strtolower($permission->getModule());
        }

        return array_values(array_unique($modules));
    }
}
