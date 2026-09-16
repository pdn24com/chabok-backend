<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\AssignInitialRoles;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class AssignInitialRolesHandler
{
    public function __construct(
        private \Modules\Authorization\Application\Repositories\AuthorizationRepository $repository,
        private \Modules\Authorization\Application\UseCases\CreateAssignments\CreateAssignmentsHandler $createAssignments,
    )
    {
    }

    public function handle(AssignInitialRolesCommand $command): AssignInitialRolesResult
    {
        $this->execute($command->hqId, $command->userId, $command->actorId, $command->assignments, $command->correlationId);
        return new AssignInitialRolesResult();
    }

    private function execute(string $hqId, string $userId, string $actorId, array $assignments, string $correlationId): void
    {
        $actor = $this->repository->user($actorId);
        if ($actor === null || $actor->hq_id !== $hqId) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        }
        $principal = new AuthenticatedPrincipal($actorId, '', $hqId, false);
        $this->createAssignments->handle(new \Modules\Authorization\Application\UseCases\CreateAssignments\CreateAssignmentsCommand($principal, $userId, $assignments, $correlationId))->data;
    }
}
