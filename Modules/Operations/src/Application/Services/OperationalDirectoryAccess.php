<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Application\Contracts\ScopedAccessInterface;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Contracts\OperationalDirectoryAccessInterface;

final readonly class OperationalDirectoryAccess implements OperationalDirectoryAccessInterface
{
    public function __construct(
        private AccessContextResolverInterface $accessContextResolver,
        private ScopedAccessInterface $scopedAccess,
    ) {}

    public function access(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $permission,
        string $module,
    ): void {
        if ($actor->hqId === null) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
        }
        $context = $this->accessContextResolver->resolve($actor);
        if (! $context->isModuleEnabled($module)) {
            throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'common.access_denied');
        }
        if (! $context->hasPermission($permission)) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'common.access_denied');
        }
        if (! in_array($nodeId, $this->scopedAccess->nodes($context, $permission), true)) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'common.access_denied');
        }
    }
}
