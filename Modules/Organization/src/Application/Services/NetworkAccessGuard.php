<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Services;

use Modules\Foundation\Application\Contracts\ScopedAccessInterface;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Application\Services\ScopedAccess;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\ScopeType;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Organization\Application\Contracts\NetworkAccessGuardInterface;
use Modules\Organization\Application\Repositories\AreaRepositoryInterface;

final readonly class NetworkAccessGuard implements NetworkAccessGuardInterface
{
    public function __construct(
        private AccessContextResolverInterface $accessContextResolver,
        private ScopedAccessInterface $scopedAccess,
        private AreaRepositoryInterface $areaRepository,
    ) {}

    public function scopeAreas(AuthenticatedPrincipal $actor, string $permission): array
    {
        $scopes = ScopedAccess::scopes($this->accessContextResolver->resolve($actor), $permission);
        $coverage = $this->scopedAccess->coverage($actor->hqId);

        return array_values(array_filter($this->areaRepository->idsByTenant((string) $actor->hqId),
            fn ($id) => $coverage->covers($scopes, ScopeType::AREA, $id)));
    }

    public function assertAreaScope(
        AuthenticatedPrincipal $actor,
        string $permission,
        ?string $areaId,
        bool $descendants = false,
    ): void {
        $scopes = ScopedAccess::scopes($this->accessContextResolver->resolve($actor), $permission);
        $coverage = $this->scopedAccess->coverage($actor->hqId);
        if (! $coverage->covers($scopes, $areaId === null ? ScopeType::TENANT : ScopeType::AREA, $areaId, $descendants)) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'common.access_denied');
        }
    }

    public function assertNodeScope(
        AuthenticatedPrincipal $actor,
        string $permission,
        string $nodeId,
    ): void {
        if (! in_array($nodeId, $this->scopedAccess->nodes($this->accessContextResolver->resolve($actor), $permission, false), true)) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'common.access_denied');
        }
    }

    public function access(AuthenticatedPrincipal $actor, string $permission): string
    {
        if ($actor->hqId === null) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
        }
        $context = $this->accessContextResolver->resolve($actor);
        if (! $context->isModuleEnabled('LiveOperations')) {
            throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'common.access_denied');
        }
        if (! $context->hasPermission($permission)) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'common.access_denied');
        }

        return $actor->hqId;
    }
}
