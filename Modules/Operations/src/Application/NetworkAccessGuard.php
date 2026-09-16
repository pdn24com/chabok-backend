<?php

declare(strict_types=1);

namespace Modules\Operations\Application;

use Modules\Foundation\Application\Contracts\AuthorizationContextResolver;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class NetworkAccessGuard
{
    public function __construct(private AuthorizationContextResolver $authorization)
    {
    }

    public function assert(AuthenticatedPrincipal $actor, string $permission): string
    {
        if ($actor->hqId === null) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
        $context = $this->authorization->resolve($actor);
        $entitled = array_filter($context['module_entitlements'], fn(array $entry): bool => $entry['module_code'] === 'LiveOperations' && $entry['status'] === 'ENABLED') !== [];
        if (!$entitled) {
            throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'Access denied.');
        }
        if (!in_array($permission, $context['permissions'], true)) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
        }
        return $actor->hqId;
    }
}
