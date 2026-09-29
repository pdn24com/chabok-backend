<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Services;

use Modules\Foundation\Application\Contracts\ScopedAccessInterface;
use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Manifest\Application\Contracts\ManifestAccessGuardInterface;

final readonly class ManifestAccessGuard implements ManifestAccessGuardInterface
{
    public function __construct(
        private AccessContextResolverInterface $accessContextResolver,
        private ScopedAccessInterface $scopedAccess,
    ) {}

    public function access(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $permission,
    ): AccessContextDto {
        if ($actor->hqId === null) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
        }
        $context = $this->accessContextResolver->resolve($actor);
        if (! $context->isModuleEnabled('Manifest')) {
            throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'common.access_denied');
        }
        if (! $context->hasPermission($permission)) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'common.access_denied');
        }
        if (! in_array($nodeId, $this->scopedAccess->nodes($context, $permission), true)) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'common.access_denied');
        }

        return $context;
    }

    public function assertDriverVisibility(AccessContextDto $context, string $target): void
    {
        if (in_array($target, ['PD', 'OD', 'OS'], true) && ! $context->hasPermission('driver.view')) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'common.access_denied');
        }
    }

    public function reviewAccess(AuthenticatedPrincipal $actor, string $node): void
    {
        $this->access($actor, $node, 'manifest.approve');
        $this->requirePermission($actor, 'live_operations.intervene');
    }

    public function requirePermission(AuthenticatedPrincipal $actor, string $permission): void
    {
        $c = $this->accessContextResolver->resolve($actor);
        if (! $c->hasPermission($permission)) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'common.access_denied');
        }
    }
}
