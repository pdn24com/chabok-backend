<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Dto;

use Modules\Foundation\Domain\Enums\EntitlementStatus;
use Modules\Foundation\Domain\Enums\ScopeType;
use Modules\Foundation\Domain\ValueObjects\PermissionScope;

final readonly class AccessContextDto
{
    /**
     * @param  list<string>  $roleCodes
     * @param  list<string>  $permissions
     * @param  array<string, list<PermissionScope>>  $permissionScopes
     * @param  list<string>|null  $menuKeys
     * @param  list<PermissionScope>  $scopes
     * @param  list<string>  $accessibleNodeIds
     * @param  list<ModuleEntitlementDto>  $moduleEntitlements
     */
    public function __construct(
        public ?string $hqId = null,
        public ?TenantSummaryDto $tenant = null,
        public bool $isPlatformAdmin = false,
        public array $roleCodes = [],
        public array $permissions = [],
        public array $permissionScopes = [],
        public ?array $menuKeys = null,
        public array $scopes = [],
        public array $accessibleNodeIds = [],
        public array $moduleEntitlements = [],
        public ?string $defaultNodeId = null,
        public ?string $actingNodeId = null,
    ) {}

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    public function isModuleEnabled(string $module, bool $ignoreCase = false): bool
    {
        foreach ($this->moduleEntitlements as $entitlement) {
            $matches = $ignoreCase ? strcasecmp($entitlement->moduleCode, $module) === 0 : $entitlement->moduleCode === $module;
            if ($matches && $entitlement->status === EntitlementStatus::ENABLED) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $modules */
    public function hasAnyEnabledModule(array $modules): bool
    {
        foreach ($modules as $module) {
            if ($this->isModuleEnabled($module)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<PermissionScope> */
    public function scopesFor(string $permission): array
    {
        return $this->permissionScopes[$permission] ?? [];
    }

    public function hasTenantScope(): bool
    {
        foreach ($this->scopes as $scope) {
            if ($scope->type === ScopeType::TENANT) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string>|null $accessibleNodeIds */
    public function atNode(string $nodeId, ?array $accessibleNodeIds = null): self
    {
        return new self($this->hqId, $this->tenant, $this->isPlatformAdmin, $this->roleCodes, $this->permissions,
            $this->permissionScopes, $this->menuKeys, $this->scopes, $accessibleNodeIds ?? $this->accessibleNodeIds,
            $this->moduleEntitlements, $this->defaultNodeId, $nodeId);
    }
}
