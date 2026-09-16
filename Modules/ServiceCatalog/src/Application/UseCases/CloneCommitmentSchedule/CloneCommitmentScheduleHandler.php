<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\CloneCommitmentSchedule;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class CloneCommitmentScheduleHandler
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\Services\ScheduleAccessGuard $scheduleAccessGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\ServiceCatalog\Application\Repositories\CommitmentScheduleRepository $schedules,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\ServiceCatalog\Application\Services\ScheduleChangeRecorder $scheduleChangeRecorder,
        private \Modules\ServiceCatalog\Application\Services\ScheduleReader $scheduleReader,
    )
    {
    }

    public function handle(CloneCommitmentScheduleCommand $command): CloneCommitmentScheduleResult
    {
        return new CloneCommitmentScheduleResult($this->execute($command->actor, $command->identityId, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $identityId, string $correlationId): array
    {
        $this->scheduleAccessGuard->assertAccess($actor, 'service_catalog.manage_draft');
        return $this->transactions->run(function () use ($actor, $identityId, $correlationId): array {
            if (!$this->schedules->identityExists($actor->hqId, $identityId)) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            }
            if ($this->schedules->hasUnpublishedSuccessor($identityId)) {
                throw new ApiException(ApiErrorCode::Conflict, 409, 'An unpublished successor already exists.');
            }
            $previous = (array) $this->schedules->lockLatestVersion($identityId);
            if ($previous === []) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            }
            $previousId = (string) $previous['commitment_schedule_version_id'];
            $newId = $this->identifiers->uuid();
            unset($previous['approved_by'], $previous['published_by'], $previous['approved_at'], $previous['published_at'], $previous['content_digest']);
            $previous['commitment_schedule_version_id'] = $newId;
            $previous['previous_version_id'] = $previousId;
            $previous['version_number'] = (int) $previous['version_number'] + 1;
            $previous['status'] = 'DRAFT';
            $previous['lock_version'] = 1;
            $previous['valid_from'] = null;
            $previous['valid_to'] = null;
            $previous['created_by'] = $actor->userId;
            $previous['created_at'] = $this->clock->now();
            $previous['updated_at'] = $this->clock->now();
            $this->schedules->insertVersion($previous);
            foreach ($this->schedules->windows($previousId) as $row) {
                $copy = (array) $row;
                $copy['commitment_schedule_window_id'] = $this->identifiers->uuid();
                $copy['commitment_schedule_version_id'] = $newId;
                $this->schedules->insertWindow($copy);
            }
            foreach ($this->schedules->scopes($previousId) as $row) {
                $copy = (array) $row;
                $copy['commitment_schedule_scope_id'] = $this->identifiers->uuid();
                $copy['commitment_schedule_version_id'] = $newId;
                $this->schedules->insertScope($copy);
            }
            $this->scheduleChangeRecorder->record($actor, 'COMMITMENT_SCHEDULE_DRAFT_CLONED', $identityId, $correlationId, ['version_id' => $newId, 'previous_version_id' => $previousId]);
            return $this->scheduleReader->versionDetail($actor, $newId);
        });
    }
}
