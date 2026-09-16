<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class OperationalStatusProjection
{
    public function __construct(private \Modules\Consignment\Application\Services\OperationalStatusAccess $operationalStatusAccess)
    {
    }

    public function present(array $row, array $context, AuthenticatedPrincipal $actor): array
    {
        foreach (['is_system', 'is_active', 'is_terminal', 'manifest_enabled'] as $key) {
            $row[$key] = (bool) $row[$key];
        }
        $row['can_manage'] = $row['hq_id'] === null ? !empty($context['is_platform_admin']) : $row['hq_id'] === $actor->hqId && $this->operationalStatusAccess->tenantManager($context);
        return $row;
    }
}
