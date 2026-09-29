<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\CreateCommitmentSchedule;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\VersionLifecycleStatus;
use Modules\ServiceCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogCodeInterface;
use Modules\ServiceCatalog\Application\Contracts\ScheduleChangeRecorderInterface;
use Modules\ServiceCatalog\Application\Contracts\ScheduleChildrenWriterInterface;
use Modules\ServiceCatalog\Application\Contracts\ScheduleInputInterface;
use Modules\ServiceCatalog\Application\Contracts\SchedulePolicyInterface;
use Modules\ServiceCatalog\Application\Contracts\ScheduleReaderInterface;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleVersionRecord;

final readonly class CreateCommitmentScheduleHandler
{
    public function __construct(
        private CatalogAccessGuardInterface $catalogAccessGuard,
        private SchedulePolicyInterface $schedulePolicy,
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private CatalogCodeInterface $catalogCode,
        private ScheduleInputInterface $scheduleInput,
        private ScheduleChildrenWriterInterface $scheduleChildrenWriter,
        private ScheduleChangeRecorderInterface $scheduleChangeRecorder,
        private ScheduleReaderInterface $scheduleReader,
    ) {}

    public function handle(CreateCommitmentScheduleCommand $command): CommitmentScheduleVersionRecord
    {
        $actor = $command->actor;
        $input = $command->input;
        $correlationId = $command->correlationId;
        $this->catalogAccessGuard->assertAccess($actor, 'service_catalog.manage_draft');
        if ($input->commitmentPolicy !== null) {
            $this->schedulePolicy->validate($input->commitmentPolicy, $input->windows, (string) $actor->hqId);
        }

        return $this->connection->transaction(function () use ($actor, $input, $correlationId): CommitmentScheduleVersionRecord {
            $now = $this->clock->now();
            $identityId = (string) CommitmentScheduleRecord::query()->forceCreate([

                'hq_id' => $actor->hqId,
                'owner_key' => $actor->hqId,
                'code' => ! empty($input->code) ? mb_strtoupper((string) $input->code) : $this->catalogCode->generate('commitment-schedules', (string) $actor->hqId),
                'title' => $input->title,
                'status' => 'ACTIVE',
                'created_by' => $actor->userId,
                'created_at' => $now,
                'updated_at' => $now,
            ])->getKey();
            $versionId = (string) CommitmentScheduleVersionRecord::query()->forceCreate([

                'commitment_schedule_id' => $identityId,
                'hq_id' => $actor->hqId,
                'version_number' => 1,
                'previous_version_id' => null,
                'status' => VersionLifecycleStatus::Draft->value,
                ...$this->scheduleInput->versionColumns($input),
                'lock_version' => 1,
                'created_by' => $actor->userId,
                'created_at' => $now,
                'updated_at' => $now,
            ])->getKey();
            $this->scheduleChildrenWriter->replaceChildren($versionId, (string) $actor->hqId, $input);
            $this->scheduleChangeRecorder->record($actor, 'COMMITMENT_SCHEDULE_CREATED', $identityId, $correlationId, ['version_id' => $versionId]);

            return $this->scheduleReader->versionDetail($actor, $versionId);
        }, attempts: 3);
    }
}
