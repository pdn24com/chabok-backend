<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\UpdateAssignment;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class UpdateAssignmentHandler
{
    public function __construct(
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Authorization\Application\UseCases\RevokeAssignment\RevokeAssignmentHandler $revokeAssignment,
        private \Modules\Authorization\Application\UseCases\CreateAssignments\CreateAssignmentsHandler $createAssignments,
    )
    {
    }

    public function handle(UpdateAssignmentCommand $command): UpdateAssignmentResult
    {
        return new UpdateAssignmentResult($this->execute($command->actor, $command->userId, $command->assignmentId, $command->input, $command->correlationId));
    }

    private function execute(
        AuthenticatedPrincipal $actor,
        string $userId,
        string $assignmentId,
        array $input,
        string $correlationId,
    ): array
    {
        return $this->transactions->run(function () use ($actor, $userId, $assignmentId, $input, $correlationId): array {
            $this->revokeAssignment->handle(new \Modules\Authorization\Application\UseCases\RevokeAssignment\RevokeAssignmentCommand($actor, $userId, $assignmentId, $correlationId));
            return $this->createAssignments->handle(new \Modules\Authorization\Application\UseCases\CreateAssignments\CreateAssignmentsCommand($actor, $userId, [$input], $correlationId))->data[0];
        });
    }
}
