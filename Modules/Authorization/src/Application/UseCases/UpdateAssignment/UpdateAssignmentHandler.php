<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\UpdateAssignment;

use Illuminate\Database\ConnectionInterface;
use Modules\Authorization\Application\UseCases\CreateAssignments\CreateAssignmentsCommand;
use Modules\Authorization\Application\UseCases\CreateAssignments\CreateAssignmentsHandler;
use Modules\Authorization\Application\UseCases\RevokeAssignment\RevokeAssignmentCommand;
use Modules\Authorization\Application\UseCases\RevokeAssignment\RevokeAssignmentHandler;
use Modules\Authorization\Infrastructure\Persistence\Models\AssignmentRecord;

final readonly class UpdateAssignmentHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private RevokeAssignmentHandler $revokeAssignmentHandler,
        private CreateAssignmentsHandler $createAssignmentsHandler,
    ) {}

    public function handle(UpdateAssignmentCommand $command): AssignmentRecord
    {
        $actor = $command->actor;
        $userId = $command->userId;
        $assignmentId = $command->assignmentId;
        $input = $command->input;
        $correlationId = $command->correlationId;

        return $this->connection->transaction(function () use ($actor, $userId, $assignmentId, $input, $correlationId): AssignmentRecord {
            $this->revokeAssignmentHandler->handle(new RevokeAssignmentCommand($actor, $userId, $assignmentId, $correlationId));

            return $this->createAssignmentsHandler->handle(new CreateAssignmentsCommand($actor, $userId, [$input], $correlationId))[0];
        }, attempts: 3);
    }
}
