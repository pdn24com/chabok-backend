<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\ListEntitlements;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListEntitlementsHandler
{
    public function __construct(
        private \Modules\Authorization\Application\Services\AuthorizationGuard $authorizationGuard,
        private \Modules\Authorization\Application\Repositories\AuthorizationRepository $repository,
        private \Modules\Foundation\Application\Contracts\AuditWriter $audit,
    )
    {
    }

    public function handle(ListEntitlementsCommand $command): ListEntitlementsResult
    {
        return new ListEntitlementsResult($this->execute($command->actor, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $correlationId): array
    {
        if ($actor->hqId === null) {
            $this->authorizationGuard->assertPermission($actor, 'iam.entitlements.view');
            $rows = $this->repository->allEntitlements();
            $this->audit->write(null, $actor->userId, 'ENTITLEMENTS_VIEWED', 'PLATFORM', null, $correlationId);
            return $rows;
        }
        $this->authorizationGuard->assertPermission($actor, 'iam.entitlements.view', $actor->hqId);
        $rows = array_map(fn($row): array => (array) $row, $this->repository->tenantEntitlements($actor->hqId));
        $this->audit->write($actor->hqId, $actor->userId, 'ENTITLEMENTS_VIEWED', 'HQ_TENANT', $actor->hqId, $correlationId);
        return $rows;
    }
}
