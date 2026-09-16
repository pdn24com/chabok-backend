<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Persistence;

final class CatalogStorageMap
{
    public const MAP = [
        'service-types' => ['service_types', 'service_type_versions', 'service_type_id', 'service_type_version_id'],
        'shipping-methods' => ['shipping_methods', 'shipping_method_versions', 'shipping_method_id', 'shipping_method_version_id'],
        'offerings' => ['service_offerings', 'service_offering_versions', 'service_offering_id', 'service_offering_version_id'],
        'options' => ['service_options', 'service_option_versions', 'service_option_id', 'service_option_version_id'],
        'commitment-schedules' => [
            'commitment_schedules',
            'commitment_schedule_versions',
            'commitment_schedule_id',
            'commitment_schedule_version_id',
        ],
    ];
    /** Storage-only mapping; no query builder crosses the repository boundary. */

    public static function query(string $table): \Illuminate\Database\Query\Builder
    {
        $model = match ($table) {
            'commitment_schedules' => Models\CommitmentScheduleRecord::class,
            'commitment_schedule_scopes' => Models\CommitmentScheduleScopeRecord::class,
            'commitment_schedule_versions' => Models\CommitmentScheduleVersionRecord::class,
            'commitment_schedule_windows' => Models\CommitmentScheduleWindowRecord::class,
            'service_offering_commitment_bindings' => Models\OfferingCommitmentBindingRecord::class,
            'service_offering_option_rules' => Models\OfferingOptionRuleRecord::class,
            'service_availability_bindings' => Models\ServiceAvailabilityBindingRecord::class,
            'service_coverage_references' => Models\ServiceCoverageReferenceRecord::class,
            'service_eligibility_rules' => Models\ServiceEligibilityRuleRecord::class,
            'service_offerings' => Models\ServiceOfferingRecord::class,
            'service_offering_versions' => Models\ServiceOfferingVersionRecord::class,
            'service_options' => Models\ServiceOptionRecord::class,
            'service_option_versions' => Models\ServiceOptionVersionRecord::class,
            'service_types' => Models\ServiceTypeRecord::class,
            'service_type_versions' => Models\ServiceTypeVersionRecord::class,
            'shipping_methods' => Models\ShippingMethodRecord::class,
            'shipping_method_versions' => Models\ShippingMethodVersionRecord::class,
            default => throw new \InvalidArgumentException('Unknown catalog storage.'),
        };
        return $model::query()->toBase();
    }
}
