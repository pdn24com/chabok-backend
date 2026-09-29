<?php

declare(strict_types=1);

namespace Tests\Support;

use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Application\Dto\ModuleEntitlementDto;
use Modules\Foundation\Application\Dto\TenantSummaryDto;
use Modules\Foundation\Domain\Enums\EntitlementStatus;
use Modules\Foundation\Domain\Enums\ScopeType;
use Modules\Foundation\Domain\ValueObjects\PermissionScope;

/** Decodes legacy-shaped test fixtures only; runtime code constructs typed facts. */
final class AccessContexts
{
    public static function make(array $fixture): AccessContextDto
    {
        $permissionScopes = [];
        foreach ($fixture['permission_scopes'] ?? [] as $permission => $scopes) {
            $permissionScopes[$permission] = self::scopes($scopes);
        }
        $entitlements = [];
        foreach ($fixture['module_entitlements'] ?? [] as $item) {
            $entitlements[] = new ModuleEntitlementDto($item['module_code'], EntitlementStatus::from($item['status']));
        }
        $tenant = $fixture['tenant'] ?? null;

        return new AccessContextDto(
            hqId: $fixture['hq_id'] ?? null,
            tenant: $tenant === null ? null : new TenantSummaryDto($tenant['hq_id'], $tenant['code'], $tenant['title']),
            isPlatformAdmin: $fixture['is_platform_admin'] ?? false,
            roleCodes: $fixture['role_codes'] ?? [],
            permissions: $fixture['permissions'] ?? [],
            permissionScopes: $permissionScopes,
            menuKeys: $fixture['menu_keys'] ?? [],
            scopes: self::scopes($fixture['scopes'] ?? []),
            accessibleNodeIds: $fixture['accessible_node_ids'] ?? [],
            moduleEntitlements: $entitlements,
            defaultNodeId: $fixture['default_node_id'] ?? null,
            actingNodeId: $fixture['acting_node_id'] ?? null,
        );
    }

    private static function scopes(array $scopes): array
    {
        $result = [];
        foreach ($scopes as $scope) {
            $result[] = new PermissionScope(ScopeType::from($scope['scope_type']), $scope['scope_id'], (bool) $scope['includes_descendants']);
        }

        return $result;
    }
}
