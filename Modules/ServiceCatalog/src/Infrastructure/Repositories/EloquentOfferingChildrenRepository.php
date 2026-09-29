<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\ServiceCatalog\Application\Repositories\OfferingChildrenRepositoryInterface;
use Modules\ServiceCatalog\Domain\Enums\OfferingChild;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\OfferingCommitmentBindingRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\OfferingOptionRuleRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceAvailabilityBindingRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceCoverageReferenceRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceEligibilityRuleRecord;

final class EloquentOfferingChildrenRepository implements OfferingChildrenRepositoryInterface
{
    /** Rows written per statement, so a wide Offering never builds one oversized query. */
    private const BATCH_SIZE = 100;

    public function deleteAll(string $versionId): void
    {
        foreach (OfferingChild::cases() as $child) {
            $this->query($child)->where('service_offering_version_id', $versionId)->delete();
        }
    }

    public function insert(OfferingChild $child, array $rows): void
    {
        $attributes = array_map(fn (array $row): array => $this->model($child)->forceFill($row)->getAttributes(), $rows);
        foreach (array_chunk($attributes, self::BATCH_SIZE) as $chunk) {
            $this->query($child)->insert($chunk);
        }
    }

    public function cloneAll(string $from, string $to): void
    {
        foreach (OfferingChild::cases() as $child) {
            $copies = [];
            foreach ($this->query($child)->where('service_offering_version_id', $from)->get() as $row) {
                $copies[] = $row->replicate()->forceFill([
                    'service_offering_version_id' => $to,
                ])->getAttributes();
            }
            foreach (array_chunk($copies, self::BATCH_SIZE) as $chunk) {
                $this->query($child)->insert($chunk);
            }
        }
    }

    private function model(OfferingChild $child): Model
    {
        return match ($child) {
            OfferingChild::OptionRule => new OfferingOptionRuleRecord,
            OfferingChild::EligibilityRule => new ServiceEligibilityRuleRecord,
            OfferingChild::CoverageReference => new ServiceCoverageReferenceRecord,
            OfferingChild::AvailabilityBinding => new ServiceAvailabilityBindingRecord,
            OfferingChild::CommitmentBinding => new OfferingCommitmentBindingRecord,
        };
    }

    private function query(OfferingChild $child): Builder
    {
        return $this->model($child)->newQuery();
    }
}
