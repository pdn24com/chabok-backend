<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class OperationalStatusAccess
{
    public function __construct(private \Modules\Foundation\Application\Contracts\AuthorizationContextResolver $authorization)
    {
    }

    public function access(AuthenticatedPrincipal $actor, bool $write): array
    {
        $c = $this->authorization->resolve($actor);
        if (!empty($c['is_platform_admin'])) {
            return $c;
        }
        if (!$actor->hqId) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
        $consignment = array_filter($c['module_entitlements'] ?? [], fn($e) => $e['module_code'] === 'Consignment' && $e['status'] === 'ENABLED') !== [];
        $manifest = array_filter($c['module_entitlements'] ?? [], fn($e) => $e['module_code'] === 'Manifest' && $e['status'] === 'ENABLED') !== [];
        $read = $consignment && (in_array('consignment.view', $c['permissions'] ?? [], true) || $this->tenantManager($c)) || $manifest && in_array('manifest.view', $c['permissions'] ?? [], true);
        if (!$read || $write && (!$consignment || !$this->tenantManager($c))) {
            throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
        }
        return $c;
    }

    public function tenantManager(array $context): bool
    {
        return in_array('operational_status.manage', $context['permissions'] ?? [], true) && array_filter($context['scopes'] ?? [], fn($s) => ($s['scope_type'] ?? null) === 'TENANT') !== [];
    }
}
