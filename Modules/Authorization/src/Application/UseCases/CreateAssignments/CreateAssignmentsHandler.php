<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\CreateAssignments;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class CreateAssignmentsHandler
{
    public function __construct(
        private \Modules\Authorization\Application\Services\AuthorizationGuard $authorizationGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Authorization\Application\Repositories\AuthorizationRepository $repository,
        private \Modules\Authorization\Application\Services\TenantAssignmentWriter $tenantAssignmentWriter,
        private \Modules\Authorization\Application\Services\AuthorizationCacheInvalidator $authorizationCacheInvalidator,
        private \Modules\Foundation\Application\Contracts\AuditWriter $audit,
        private \Modules\Foundation\Application\Contracts\OutboxWriter $outbox,
    )
    {
    }

    public function handle(CreateAssignmentsCommand $command): CreateAssignmentsResult
    {
        return new CreateAssignmentsResult($this->execute($command->actor, $command->userId, $command->assignments, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $userId, array $assignments, string $correlationId): array
    {
        $hqId = $this->authorizationGuard->tenantId($actor);
        $this->authorizationGuard->assertPermission($actor, 'iam.roles.assign', $hqId);
        return $this->transactions->run(function () use ($actor, $userId, $assignments, $correlationId, $hqId): array {
            $user = $this->repository->lockedUser($userId);
            if ($user === null || $user->hq_id !== $hqId) {
                throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
            }
            $created = [];
            foreach ($assignments as $input) {
                $created[] = $this->tenantAssignmentWriter->createTenantAssignment($actor, $userId, $hqId, $input);
            }
            $this->authorizationCacheInvalidator->invalidateUser($userId);
            $this->audit->write($hqId, $actor->userId, 'ROLE_ASSIGNMENTS_CREATED', 'USER', $userId, $correlationId, after: ['assignment_ids' => array_column($created, 'assignment_id')]);
            $this->outbox->write($hqId, 'USER', $userId, 'iam.user.assignments_changed', $correlationId, ['user_id' => $userId]);
            return $created;
        });
    }
}
