<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Application\Contracts\ScopedAccessInterface;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Contracts\MovementAccessGuardInterface;

final readonly class MovementAccessGuard implements MovementAccessGuardInterface
{
    public function __construct(
        private AccessContextResolverInterface $accessContextResolver,
        private ScopedAccessInterface $scopedAccess,
    ) {}

    public function access(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $permission,
    ): void {
        if ($actor->hqId === null) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
        }
        $c = $this->accessContextResolver->resolve($actor);
        if (! $c->isModuleEnabled('LiveOperations')) {
            throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'common.access_denied');
        }
        if (! $c->hasPermission($permission)) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'common.access_denied');
        }
        if (! in_array($nodeId, $this->scopedAccess->nodes($c, $permission), true)) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'common.access_denied');
        }
    }
}
