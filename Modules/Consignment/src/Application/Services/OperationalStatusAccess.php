<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Services;

use Modules\Consignment\Application\Contracts\OperationalStatusAccessInterface;
use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class OperationalStatusAccess implements OperationalStatusAccessInterface
{
    public function __construct(private AccessContextResolverInterface $accessContextResolver) {}

    public function access(AuthenticatedPrincipal $actor, bool $write): AccessContextDto
    {
        $c = $this->accessContextResolver->resolve($actor);
        if (! empty($c->isPlatformAdmin)) {
            return $c;
        }
        if (! $actor->hqId) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
        }
        $consignment = $c->isModuleEnabled('Consignment');
        $manifest = $c->isModuleEnabled('Manifest');
        $read = $consignment && ($c->hasPermission('consignment.view') || $this->tenantManager($c)) || $manifest && $c->hasPermission('manifest.view');
        if (! $read || $write && (! $consignment || ! $this->tenantManager($c))) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'common.access_denied');
        }

        return $c;
    }

    public function tenantManager(AccessContextDto $context): bool
    {
        return $context->hasPermission('operational_status.manage') && $context->hasTenantScope();
    }
}
