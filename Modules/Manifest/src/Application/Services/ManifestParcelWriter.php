<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Manifest\Application\Contracts\ManifestParcelWriterInterface;
use Modules\Manifest\Application\Dto\ManifestParcelAdditionDto;
use Modules\Manifest\Application\Repositories\ManifestParcelRepositoryInterface;
use Modules\Manifest\Application\Serialization\ManifestEligibilityDocument;
use Modules\Manifest\Domain\Enums\ManifestEligibilityReason;
use Modules\Manifest\Domain\Enums\ManifestParcelAddResult;
use Modules\Manifest\Domain\Enums\ManifestParcelStatus;

final readonly class ManifestParcelWriter implements ManifestParcelWriterInterface
{
    private const BATCH_SIZE = 100;

    public function __construct(
        private ClockInterface $clock,
        private ManifestParcelRepositoryInterface $manifestParcelRepository,
    ) {}

    /** @param list<ManifestParcelAdditionDto> $additions Caller owns the manifest transaction and locks. */
    public function insert(array $additions): void
    {
        foreach (array_chunk($additions, self::BATCH_SIZE) as $batch) {
            $attributes = array_map(static fn (ManifestParcelAdditionDto $addition): array => $addition->record->getAttributes(), $batch);
            if ($this->manifestParcelRepository->insertUnlessSlotTaken($attributes)) {
                continue;
            }
            // A concurrent manifest may have claimed a slot after eligibility was read.
            // Only this exceptional batch is retried per row to retain individual outcomes.
            foreach ($batch as $addition) {
                $this->insertAfterConflict($addition);
            }
        }
    }

    /** @param Collection<int, \Modules\Manifest\Infrastructure\Persistence\Models\ManifestParcelRecord> $rows Already locked by the caller. */
    public function saveStates(Collection $rows): void
    {
        $this->manifestParcelRepository->saveDirty($rows);
    }

    private function insertAfterConflict(ManifestParcelAdditionDto $addition): void
    {
        if ($this->manifestParcelRepository->saveUnlessSlotTaken($addition->record)) {
            return;
        }
        $reason = ManifestEligibilityReason::ParcelAlreadyAssigned;
        $addition->outcome->result = ManifestParcelAddResult::Failed;
        $addition->outcome->reason = $reason;
        $addition->record->forceFill([

            'manifest_parcel_status' => ManifestParcelStatus::Failed->value,
            'failure_code' => $reason->value,
            'failure_reason' => ManifestEligibilityDocument::safeReason($reason),
            'active_slot' => null,
            'processed_at' => $this->clock->now(),
        ]);
        $this->manifestParcelRepository->save($addition->record);
    }
}
