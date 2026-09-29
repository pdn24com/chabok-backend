<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\ValidateManifest;

use Illuminate\Database\ConnectionInterface;
use Modules\Consignment\Application\Repositories\ParcelRepositoryInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Manifest\Application\Contracts\ManifestAccessGuardInterface;
use Modules\Manifest\Application\Contracts\ManifestEligibilityEvaluatorInterface;
use Modules\Manifest\Application\Contracts\ManifestParcelWriterInterface;
use Modules\Manifest\Application\Contracts\ManifestReaderInterface;
use Modules\Manifest\Application\Dto\ManifestDetailDto;
use Modules\Manifest\Application\Repositories\ManifestParcelRepositoryInterface;
use Modules\Manifest\Application\Repositories\ManifestRepositoryInterface;
use Modules\Manifest\Application\Serialization\ManifestEligibilityDocument;
use Modules\Manifest\Domain\Enums\ManifestEligibilityReason;
use Modules\Manifest\Domain\Enums\ManifestParcelStatus;
use Modules\Manifest\Domain\Enums\ManifestState;
use Modules\Manifest\Domain\Policies\ManifestPolicy;

final readonly class ValidateManifestHandler
{
    public function __construct(
        private ManifestAccessGuardInterface $manifestAccessGuard,
        private ConnectionInterface $connection,
        private ManifestReaderInterface $manifestReader,
        private ManifestPolicy $manifestPolicy,
        private ManifestEligibilityEvaluatorInterface $manifestEligibilityEvaluator,
        private ClockInterface $clock,
        private AuditWriterInterface $auditWriter,
        private ManifestParcelWriterInterface $manifestParcelWriter,
        private ManifestRepositoryInterface $manifestRepository,
        private ManifestParcelRepositoryInterface $manifestParcelRepository,
        private ParcelRepositoryInterface $parcelRepository,
    ) {}

    public function handle(ValidateManifestCommand $command): ManifestDetailDto
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $id = $command->id;
        $expected = $command->expected;
        $correlationId = $command->correlationId;
        $context = $this->manifestAccessGuard->access($actor, $nodeId, 'manifest.edit');
        $this->connection->transaction(function () use ($actor, $nodeId, $id, $expected, $correlationId): void {
            $m = $this->manifestReader->locked($actor, $nodeId, $id);
            $this->manifestPolicy->assertVersion((int) $m->version, $expected);
            $this->manifestPolicy->assertEditable($m->state->value);
            $rows = $this->manifestParcelRepository->lockManifestRowsWithStatus($id, [ManifestParcelStatus::Pending->value, ManifestParcelStatus::Validated->value]);
            $parcels = $this->parcelRepository->byIds((string) $actor->hqId, $rows->pluck('parcel_id')->all());
            $eligibilities = $this->manifestEligibilityEvaluator->evaluateMany($parcels, $m, $nodeId);
            foreach ($rows as $row) {
                $eligibility = $eligibilities[$row->parcel_id] ?? ManifestEligibilityReason::ParcelNotFound;
                $row->forceFill($eligibility->eligible() ? ['manifest_parcel_status' => 'VALIDATED', 'updated_at' => $this->clock->now()] : [
                    'manifest_parcel_status' => 'FAILED',
                    'failure_code' => $eligibility->value,
                    'failure_reason' => ManifestEligibilityDocument::safeReason($eligibility),
                    'active_slot' => null,
                    'processed_at' => $this->clock->now(),
                    'updated_at' => $this->clock->now(),
                ]);
            }
            $this->manifestParcelWriter->saveStates($rows);
            $this->manifestRepository->update($id, [
                'state' => ManifestState::Open->value,
                'version' => $m->version + 1,
                'updated_at' => $this->clock->now(),
            ]);
            $this->auditWriter->write($actor->hqId, $actor->userId, 'MANIFEST_VALIDATED', 'MANIFEST', $id, $correlationId);
        }, attempts: 3);

        return $this->manifestReader->detail($actor, $nodeId, $id, $context);
    }
}
