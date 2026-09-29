<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\ServiceCatalog\Application\Mappers\CatalogDraftInput;
use Modules\ServiceCatalog\Application\Services\OfferingChildrenWriter;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\OfferingCommitmentBindingRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\OfferingOptionRuleRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceAvailabilityBindingRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceCoverageReferenceRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceEligibilityRuleRecord;
use Tests\TestCase;

final class OfferingChildrenWriteTest extends TestCase
{
    public function test_bulk_replacement_and_clone_preserve_json_children_and_independent_identifiers(): void
    {
        config(['database.connections.offering_write_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('offering_write_test');
        foreach (['service_offering_option_rules', 'service_eligibility_rules', 'service_coverage_references', 'service_availability_bindings', 'service_offering_commitment_bindings'] as $table) {
            (require glob(base_path('Modules/ServiceCatalog/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        $writer = $this->app->make(OfferingChildrenWriter::class);
        $writeCounts = [];
        $cloneCounts = [];
        foreach ([1, 40, 101] as $count) {
            $input = [
                'option_rules' => [], 'eligibility_rules' => [], 'coverage_references' => [],
                'availability_bindings' => [['scope_type' => 'TENANT'], ['scope_type' => 'PLATFORM', 'enabled' => false]],
                'commitment_binding' => ['commitment_schedule_version_id' => '100081670', 'pickup_mode' => 'NONE', 'delivery_mode' => 'COMPUTED', 'duration_value' => 12, 'duration_unit' => 'HOUR', 'duration_anchor' => 'PICKUP_COMPLETED'],
            ];
            for ($index = 1; $index <= $count; $index++) {
                $input['option_rules'][] = ['service_option_version_id' => \Tests\Support\FixtureId::from('option-'.$index), 'compatibility' => 'CONDITIONAL', 'condition' => ['fact_key' => 'flag', 'expected_value' => false]];
                $input['eligibility_rules'][] = ['dimension' => 'PHYSICAL', 'fact_key' => 'weight-'.$index, 'operator' => 'MAX', 'expected_value' => $index, 'reason_code' => 'HEAVY', 'priority' => $index];
                $input['coverage_references'][] = ['direction' => 'BOTH', 'reference_type' => 'COUNTRY', 'reference_value' => 'IR', 'priority' => $index];
            }
            $this->measure($writeCounts, fn () => DB::transaction(fn () => $writer->replaceOfferingChildren('69006970', CatalogDraftInput::draft($input), '245213294')));
            $target = 'clone-'.$count;
            $this->measure($cloneCounts, fn () => DB::transaction(fn () => $writer->cloneOfferingChildren('69006970', $target)));
            foreach ([OfferingOptionRuleRecord::class, ServiceEligibilityRuleRecord::class, ServiceCoverageReferenceRecord::class, ServiceAvailabilityBindingRecord::class, OfferingCommitmentBindingRecord::class] as $model) {
                $sortField = match ($model) {
                    OfferingOptionRuleRecord::class => 'service_option_version_id',
                    ServiceEligibilityRuleRecord::class, ServiceCoverageReferenceRecord::class => 'priority',
                    ServiceAvailabilityBindingRecord::class => 'scope_type',
                    default => 'duration_anchor',
                };
                $originals = $model::query()->where('service_offering_version_id', '69006970')->orderBy($sortField)->get();
                $copies = $model::query()->where('service_offering_version_id', $target)->orderBy($sortField)->get();
                $expectedCount = match ($model) {
                    ServiceAvailabilityBindingRecord::class => 2,
                    OfferingCommitmentBindingRecord::class => 1,
                    default => $count,
                };
                self::assertCount($expectedCount, $originals);
                self::assertCount($expectedCount, $copies);
                foreach ($originals as $index => $original) {
                    $copy = $copies[$index];
                    self::assertNotSame($original->getKey(), $copy->getKey());
                    self::assertNotSame($original->getAttribute($original->getRouteKeyName()), $copy->getAttribute($copy->getRouteKeyName()));
                    $ignore = ['id', 'service_offering_version_id', \Modules\Foundation\Infrastructure\Persistence\RecordSchema::IDENTITY_NAMES[$original->getTable()]];
                    self::assertSame(array_diff_key($original->attributesToArray(), array_flip($ignore)), array_diff_key($copy->attributesToArray(), array_flip($ignore)));
                }
            }
            self::assertSame(['fact_key' => 'flag', 'expected_value' => false], OfferingOptionRuleRecord::query()->where('service_offering_version_id', $target)->first()->condition);
            self::assertSame(1, ServiceEligibilityRuleRecord::query()->where('service_offering_version_id', $target)->orderBy('priority')->first()->expected_value);
            self::assertSame('245213294', ServiceAvailabilityBindingRecord::query()->where(['service_offering_version_id' => $target, 'scope_type' => 'TENANT'])->sole()->scope_value);
            self::assertFalse((bool) ServiceAvailabilityBindingRecord::query()->where(['service_offering_version_id' => $target, 'scope_type' => 'PLATFORM'])->sole()->enabled);
            self::assertSame('PICKUP_COMPLETED', OfferingCommitmentBindingRecord::query()->where('service_offering_version_id', $target)->sole()->duration_anchor);
        }
        self::assertSame([10, 10, 13], $writeCounts);
        self::assertSame([10, 10, 13], $cloneCounts);
        self::assertSame(1, OfferingOptionRuleRecord::query()->where('service_offering_version_id', 'clone-1')->count());
    }

    private function measure(array &$counts, callable $write): void
    {
        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();
        try {
            $write();
            $counts[] = count(DB::connection()->getQueryLog());
        } finally {
            DB::connection()->disableQueryLog();
        }
    }
}
