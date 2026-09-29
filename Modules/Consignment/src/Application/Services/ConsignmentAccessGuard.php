<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Services;

use Modules\Consignment\Application\Contracts\ConsignmentAccessGuardInterface;
use Modules\Foundation\Application\Contracts\ScopedAccessInterface;
use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ConsignmentAccessGuard implements ConsignmentAccessGuardInterface
{
    public function __construct(
        private AccessContextResolverInterface $accessContextResolver,
        private ScopedAccessInterface $scopedAccess,
    ) {}

    public function assertAccess(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $permission,
    ): AccessContextDto {
        if ($actor->hqId === null) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
        }
        $context = $this->accessContextResolver->resolve($actor);
        $entitled = $context->isModuleEnabled('Consignment');
        if (! $entitled) {
            throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'common.access_denied');
        }
        if (! $context->hasPermission($permission)) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'common.access_denied');
        }
        if (! in_array($nodeId, $this->scopedAccess->nodes($context, $permission), true)) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'common.access_denied');
        }
        $context = $context->atNode($nodeId, $this->scopedAccess->nodes($context, $permission));

        return $context;
    }
}
