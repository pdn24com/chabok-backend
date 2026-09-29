<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\AssignInitialRoles;

use Modules\Authorization\Application\UseCases\CreateAssignments\CreateAssignmentsCommand;
use Modules\Authorization\Application\UseCases\CreateAssignments\CreateAssignmentsHandler;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;

final readonly class AssignInitialRolesHandler
{
    public function __construct(
        private CreateAssignmentsHandler $createAssignmentsHandler,
        private UserRepositoryInterface $userRepository,
    ) {}

    public function handle(AssignInitialRolesCommand $command): void
    {
        $hqId = $command->hqId;
        $userId = $command->userId;
        $actorId = $command->actorId;
        $assignments = $command->assignments;
        $correlationId = $command->correlationId;
        $actor = $this->userRepository->find($actorId);
        if ($actor === null || $actor->hq_id !== $hqId) {
            throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
        }
        $principal = new AuthenticatedPrincipal($actorId, '', $hqId, false);
        $this->createAssignmentsHandler->handle(new CreateAssignmentsCommand($principal, $userId, $assignments, $correlationId));

    }
}
