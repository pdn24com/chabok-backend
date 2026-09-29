<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\AddManifestParcels;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Manifest\Application\Contracts\ManifestAccessGuardInterface;
use Modules\Manifest\Application\Contracts\ManifestEligibilityEvaluatorInterface;
use Modules\Manifest\Application\Contracts\ManifestParcelWriterInterface;
use Modules\Manifest\Application\Contracts\ManifestReaderInterface;
use Modules\Manifest\Application\Dto\ManifestParcelAdditionDto;
use Modules\Manifest\Application\Dto\ManifestParcelOutcomeDto;
use Modules\Manifest\Application\Repositories\ManifestCandidateRepositoryInterface;
use Modules\Manifest\Application\Repositories\ManifestParcelRepositoryInterface;
use Modules\Manifest\Application\Repositories\ManifestRepositoryInterface;
use Modules\Manifest\Application\Serialization\ManifestEligibilityDocument;
use Modules\Manifest\Domain\Enums\ManifestEligibilityReason;
use Modules\Manifest\Domain\Enums\ManifestParcelAddResult;
use Modules\Manifest\Domain\Policies\ManifestPolicy;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestParcelRecord;

final readonly class AddManifestParcelsHandler
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
        private ManifestCandidateRepositoryInterface $manifestCandidateRepository,
    ) {}

    public function handle(AddManifestParcelsCommand $command): AddManifestParcelsResult
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $id = $command->id;
        $input = $command->input;
        $correlationId = $command->correlationId;
        $context = $this->manifestAccessGuard->access($actor, $nodeId, 'manifest.edit');
        $outcomes = $this->connection->transaction(function () use ($actor, $nodeId, $id, $input, $correlationId): array {
            $manifest = $this->manifestReader->locked($actor, $nodeId, $id);
            $this->manifestPolicy->assertVersion((int) $manifest->version, $input->expectedVersion);
            $this->manifestPolicy->assertEditable($manifest->state->value);
            $outcomes = [];
            $additions = [];
            $existing = collect($this->manifestParcelRepository->parcelIds($id))->flip();
            $identifiers = array_values(array_unique(array_map('strval', $input->identifiers)));
            $matches = $this->manifestCandidateRepository->lockVisibleByIdentifiers((string) $actor->hqId, $nodeId, $identifiers);
            $eligibilities = $this->manifestEligibilityEvaluator->evaluateMany($matches, $manifest, $nodeId);
            $byParcel = $matches->groupBy('parcel_number');
            $byConsignment = $matches->groupBy(fn ($parcel) => $parcel->consignment->consignment_number);
            foreach ($input->identifiers as $identifier) {
                $parcels = $byParcel->get((string) $identifier, collect())->merge($byConsignment->get((string) $identifier, collect()))->unique('parcel_id')->sortBy('parcel_number');
                if ($parcels->isEmpty()) {
                    $reason = ManifestEligibilityReason::ParcelNotFound;
                    $outcomes[] = new ManifestParcelOutcomeDto((string) $identifier, ManifestParcelAddResult::NotFound, $reason);

                    continue;
                }
                foreach ($parcels as $parcel) {
                    if ($existing->has($parcel->parcel_id)) {
                        $outcomes[] = new ManifestParcelOutcomeDto((string) $identifier, ManifestParcelAddResult::Duplicate, $eligibilities[$parcel->parcel_id], $parcel->parcel_number);

                        continue;
                    }
                    $eligibility = $eligibilities[$parcel->parcel_id];
                    $slot = hash('sha256', "{$actor->hqId}|{$parcel->parcel_id}|{$manifest->manifest_status}");
                    $row = [

                        'hq_id' => $actor->hqId,
                        'manifest_id' => $id,
                        'parcel_id' => $parcel->parcel_id,
                        'manifest_parcel_status' => $eligibility->eligible() ? 'PENDING' : 'FAILED',
                        'failure_code' => $eligibility->eligible() ? null : $eligibility->value,
                        'failure_reason' => $eligibility->eligible() ? null : ManifestEligibilityDocument::safeReason($eligibility),
                        'input_source' => $input->inputSource->value,
                        'input_value' => $identifier,
                        'active_slot' => $eligibility->eligible() ? $slot : null,
                        'created_by' => $actor->userId,
                        'processed_at' => $eligibility->eligible() ? null : $this->clock->now(),
                        'created_at' => $this->clock->now(),
                        'updated_at' => $this->clock->now(),
                    ];
                    $outcome = new ManifestParcelOutcomeDto((string) $identifier, $eligibility->eligible() ? ManifestParcelAddResult::Added : ManifestParcelAddResult::Failed, $eligibility, $parcel->parcel_number);
                    $additions[] = new ManifestParcelAdditionDto((new ManifestParcelRecord)->forceFill($row), $outcome);
                    $outcomes[] = $outcome;
                    $existing->put($parcel->parcel_id, true);
                }
            }
            if ($additions !== []) {
                $this->manifestParcelWriter->insert($additions);
                $this->manifestRepository->update($id, ['version' => $manifest->version + 1, 'updated_at' => $this->clock->now()]);
                $this->auditWriter->write($actor->hqId, $actor->userId, 'MANIFEST_PARCELS_ADDED', 'MANIFEST', $id, $correlationId);
            }

            return $outcomes;
        }, attempts: 3);

        return new AddManifestParcelsResult($this->manifestReader->detail($actor, $nodeId, $id, $context), $outcomes);
    }
}
