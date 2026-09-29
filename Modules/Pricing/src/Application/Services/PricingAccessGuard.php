<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Pricing\Application\Contracts\PricingAccessGuardInterface;

final readonly class PricingAccessGuard implements PricingAccessGuardInterface
{
    public function __construct(private AccessContextResolverInterface $accessContextResolver) {}

    public function assertAccess(
        AuthenticatedPrincipal $actor,
        string $permission,
        bool $runtime = false,
    ): void {
        if ($actor->hqId === null) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
        }
        $context = $this->accessContextResolver->resolve($actor);
        $allowedModules = $runtime ? ['Pricing', 'Consignment'] : ['Pricing'];
        if (! $context->hasAnyEnabledModule($allowedModules)) {
            throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'common.access_denied');
        }
        if (! $context->hasPermission($permission)) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'common.access_denied');
        }
    }
}
