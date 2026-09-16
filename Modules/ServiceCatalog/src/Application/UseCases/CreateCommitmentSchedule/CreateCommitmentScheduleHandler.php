<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\CreateCommitmentSchedule;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CreateCommitmentScheduleHandler
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\Services\ScheduleAccessGuard $scheduleAccessGuard,
        private \Modules\ServiceCatalog\Application\SchedulePolicy $schedulePolicy,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\ServiceCatalog\Application\Repositories\CommitmentScheduleRepository $schedules,
        private \Modules\ServiceCatalog\Application\CatalogCode $codes,
        private \Modules\ServiceCatalog\Application\Services\ScheduleInput $scheduleInput,
        private \Modules\ServiceCatalog\Application\Services\ScheduleChildrenWriter $scheduleChildrenWriter,
        private \Modules\ServiceCatalog\Application\Services\ScheduleChangeRecorder $scheduleChangeRecorder,
        private \Modules\ServiceCatalog\Application\Services\ScheduleReader $scheduleReader,
    )
    {
    }

    public function handle(CreateCommitmentScheduleCommand $command): CreateCommitmentScheduleResult
    {
        return new CreateCommitmentScheduleResult($this->execute($command->actor, $command->input, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        $this->scheduleAccessGuard->assertAccess($actor, 'service_catalog.manage_draft');
        if (isset($input['commitment_policy'])) {
            $this->schedulePolicy->validate($input['commitment_policy'], $input['windows'] ?? [], (string) $actor->hqId);
        }
        return $this->transactions->run(function () use ($actor, $input, $correlationId): array {
            $identityId = $this->identifiers->uuid();
            $versionId = $this->identifiers->uuid();
            $now = $this->clock->now();
            $this->schedules->insertIdentity([
                'commitment_schedule_id' => $identityId,
                'hq_id' => $actor->hqId,
                'owner_key' => $actor->hqId,
                'code' => !empty($input['code']) ? mb_strtoupper((string) $input['code']) : $this->codes->generate('commitment-schedules', (string) $actor->hqId),
                'title' => $input['title'],
                'status' => 'ACTIVE',
                'created_by' => $actor->userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->schedules->insertVersion([
                'commitment_schedule_version_id' => $versionId,
                'commitment_schedule_id' => $identityId,
                'hq_id' => $actor->hqId,
                'version_number' => 1,
                'previous_version_id' => null,
                'status' => 'DRAFT',
                ...$this->scheduleInput->versionColumns($input),
                'lock_version' => 1,
                'created_by' => $actor->userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->scheduleChildrenWriter->replaceChildren($versionId, (string) $actor->hqId, $input);
            $this->scheduleChangeRecorder->record($actor, 'COMMITMENT_SCHEDULE_CREATED', $identityId, $correlationId, ['version_id' => $versionId]);
            return $this->scheduleReader->versionDetail($actor, $versionId);
        });
    }
}
