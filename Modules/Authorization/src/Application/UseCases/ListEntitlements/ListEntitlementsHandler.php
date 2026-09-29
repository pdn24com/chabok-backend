<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\ListEntitlements;

use Modules\Authorization\Application\Contracts\AuthorizationGuardInterface;
use Modules\Authorization\Application\Repositories\TenantEntitlementRepositoryInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;

final readonly class ListEntitlementsHandler
{
    public function __construct(
        private AuthorizationGuardInterface $authorizationGuard,
        private AuditWriterInterface $auditWriter,
        private TenantEntitlementRepositoryInterface $tenantEntitlementRepository,
    ) {}

    public function handle(ListEntitlementsCommand $command): array
    {
        $actor = $command->actor;
        $correlationId = $command->correlationId;
        if ($actor->hqId === null) {
            $this->authorizationGuard->assertPermission($actor, 'iam.entitlements.view');
            $rows = $this->tenantEntitlementRepository->acrossTenants();
            $this->auditWriter->write(null, $actor->userId, 'ENTITLEMENTS_VIEWED', 'PLATFORM', null, $correlationId);

            return $rows;
        }
        $this->authorizationGuard->assertPermission($actor, 'iam.entitlements.view', $actor->hqId);
        $rows = $this->tenantEntitlementRepository->forTenant((string) $actor->hqId);
        $this->auditWriter->write($actor->hqId, $actor->userId, 'ENTITLEMENTS_VIEWED', 'HQ_TENANT', $actor->hqId, $correlationId);

        return $rows;
    }
}
