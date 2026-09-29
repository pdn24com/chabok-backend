<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\ServiceCatalog\Application\Mappers\OfferingSelectionInput;
use Modules\ServiceCatalog\Application\Serialization\EligibilityDecisionDocument;
use Modules\ServiceCatalog\Application\Serialization\SelectedServiceOptionDocument;
use Modules\ServiceCatalog\Application\Services\OfferingEligibility;
use Modules\ServiceCatalog\Application\Services\OfferingOptions;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingVersionRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RecordFixtureQuery;
use Tests\TestCase;

final class OfferingOptionResolutionTest extends TestCase
{
    public function test_historical_binding_resolves_latest_published_option_and_preserves_response(): void
    {
        $this->addOption('124333115');
        $result = $this->resolvedOptions();
        self::assertSame([[
            'service_option_id' => '124333115', 'service_option_version_id' => '248718664', 'code' => '124333115',
            'labels' => ['fa' => 'گزینه'], 'definition' => ['enabled' => true], 'compatibility' => 'ALLOWED',
            'required' => false, 'selectable' => true, 'reason_code' => null,
        ]], $result);
        self::assertSame('ELIGIBLE', $this->eligibility(['one-1'])['outcome']);
        self::assertSame('ELIGIBLE', $this->eligibility(['124333115'])['outcome']);
    }

    public static function unavailableOptions(): array
    {
        return [['219161186'], ['106329882'], ['136079804'], ['missing']];
    }

    #[DataProvider('unavailableOptions')]
    public function test_unavailable_optional_is_omitted_but_required_and_selected_are_rejected(string $state): void
    {
        $this->addOption('124333115');
        match ($state) {
            '219161186' => RecordFixtureQuery::table('service_options')->update(['status' => 'INACTIVE']),
            '106329882' => RecordFixtureQuery::table('service_options')->update(['hq_id' => '227711138']),
            '136079804' => RecordFixtureQuery::table('service_option_versions')->update(['status' => 'DRAFT']),
            'missing' => RecordFixtureQuery::table('service_option_versions')->delete(),
        };
        self::assertSame([], $this->resolvedOptions());
        foreach (['REQUIRED', 'SELECTED'] as $mode) {
            try {
                if ($mode === 'REQUIRED') {
                    RecordFixtureQuery::table('service_offering_option_rules')->update(['compatibility' => 'REQUIRED']);
                    $this->resolvedOptions();
                } else {
                    $this->eligibility(['one-1']);
                }
                self::fail('Unavailable options must be rejected.');
            } catch (ApiException $error) {
                self::assertSame(422, $error->httpStatus);
                self::assertSame(['reason_code' => 'CATALOG_DEPENDENCY_UNAVAILABLE', 'resource' => 'options'], $error->details);
            }
        }
    }

    public function test_required_forbidden_conditional_and_unbound_selection_rules_are_preserved(): void
    {
        $this->addOption('218773041', 'REQUIRED');
        $this->addOption('71226821', 'FORBIDDEN');
        $this->addOption('110271195', 'CONDITIONAL');
        $this->addOption('14853517');
        RecordFixtureQuery::table('service_offering_option_rules')->where('offering_option_rule_id', '14853517')->delete();
        $options = collect($this->resolvedOptions())->keyBy('service_option_id');
        self::assertTrue($options['218773041']['required']);
        self::assertFalse($options['71226821']['selectable']);
        self::assertFalse($options['110271195']['selectable']);
        self::assertSame('SERVICE_OPTION_CONDITION_NOT_MET', $options['110271195']['reason_code']);
        self::assertSame(['SERVICE_OPTION_REQUIRED'], $this->eligibility([])['reason_codes']);
        self::assertEqualsCanonicalizing(['SERVICE_OPTION_FORBIDDEN', 'SERVICE_OPTION_CONDITION_NOT_MET'], $this->eligibility(['required-1', 'forbidden-1', 'conditional-1'])['reason_codes']);
        self::assertSame(['SERVICE_OPTION_NOT_ALLOWED'], $this->eligibility(['required-1', 'unbound-1'])['reason_codes']);
        self::assertSame('ELIGIBLE', $this->eligibility(['required-1', 'conditional-1'], ['flag' => true])['outcome']);
        $allowed = collect($this->resolvedOptions(['flag' => true]))->keyBy('service_option_id');
        self::assertTrue($allowed['110271195']['selectable']);
    }

    public function test_global_option_and_global_offering_visibility_are_preserved(): void
    {
        $this->addOption('134224936');
        RecordFixtureQuery::table('service_options')->update(['hq_id' => null]);
        self::assertCount(1, $this->resolvedOptions());
        self::assertSame('ELIGIBLE', $this->eligibility(['268307057'])['outcome']);
        RecordFixtureQuery::table('service_options')->update(['hq_id' => '227711138']);
        RecordFixtureQuery::table('service_offering_versions')->update(['hq_id' => null]);
        self::assertCount(1, $this->resolvedOptions(), 'A null offering owner preserves the existing unscoped resolution.');
    }

    public function test_query_count_does_not_grow_with_option_families_or_selected_options(): void
    {
        $optionCounts = [];
        $eligibilityCounts = [];
        $references = [];
        for ($index = 1; $index <= 40; $index++) {
            $this->addOption(\Tests\Support\FixtureId::from('option-'.$index));
            $references[] = \Tests\Support\FixtureId::from('option-'.$index.'-1');
            if (! in_array($index, [1, 40], true)) {
                continue;
            }
            DB::connection()->enableQueryLog();
            try {
                DB::connection()->flushQueryLog();
                self::assertCount($index, $this->resolvedOptions());
                $optionCounts[] = count(DB::connection()->getQueryLog());
                DB::connection()->flushQueryLog();
                self::assertSame('ELIGIBLE', $this->eligibility($references)['outcome']);
                $eligibilityCounts[] = count(DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
        }
        self::assertSame([5, 5], $optionCounts);
        self::assertSame([7, 7], $eligibilityCounts); // Includes reading the native offering itself.
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.offering_options_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('offering_options_test');
        foreach (['service_offerings', 'service_offering_versions', 'service_options', 'service_option_versions', 'service_offering_option_rules', 'service_eligibility_rules', 'service_coverage_references'] as $table) {
            (require glob(base_path('Modules/ServiceCatalog/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        RecordFixtureQuery::table('service_offerings')->insert(['service_offering_id' => '90771604', 'hq_id' => '245213294',
            'owner_key' => '245213294', 'code' => 'OFFERING', 'status' => 'ACTIVE', 'created_by' => '197574617']);
        RecordFixtureQuery::table('service_offering_versions')->insert(['service_offering_version_id' => '132002988', 'service_offering_id' => '90771604',
            'hq_id' => '245213294', 'version_number' => 1, 'service_type_version_id' => '19938311', 'shipping_method_version_id' => '95938240',
            'labels' => '{}', 'sla_policy' => '{}', 'created_by' => '197574617', 'status' => 'PUBLISHED']);
    }

    private function addOption(string $id, string $compatibility = 'ALLOWED'): void
    {
        RecordFixtureQuery::table('service_options')->insert(['service_option_id' => $id, 'hq_id' => '245213294',
            'owner_key' => '245213294', 'code' => $id, 'created_by' => '197574617', 'status' => 'ACTIVE']);
        foreach ([1, 2, 3] as $version) {
            RecordFixtureQuery::table('service_option_versions')->insert(['service_option_version_id' => \Tests\Support\FixtureId::from($id.'-'.$version), 'service_option_id' => $id,
                'version_number' => $version, 'labels' => json_encode(['fa' => 'گزینه']), 'definition' => '{"enabled":true}',
                'created_by' => '197574617', 'status' => $version === 3 ? 'DRAFT' : 'PUBLISHED']);
        }
        RecordFixtureQuery::table('service_offering_option_rules')->insert(['offering_option_rule_id' => $id, 'service_offering_version_id' => '132002988',
            'service_option_version_id' => \Tests\Support\FixtureId::from($id.'-1'), 'compatibility' => $compatibility,
            'condition' => json_encode(['fact_key' => 'flag', 'operator' => 'EQ', 'expected_value' => true])]);
    }

    private function resolvedOptions(array $context = []): array
    {
        return array_map(SelectedServiceOptionDocument::serialize(...), $this->app->make(OfferingOptions::class)->resolvedOptions(ServiceOfferingVersionRecord::query()->where('service_offering_version_id', '132002988')->with(['optionRules.optionVersion.option.publishedVersions' => fn ($versions) => $versions->limit(1)])->sole(), OfferingSelectionInput::fromArray($context)));
    }

    private function eligibility(array $selected, array $context = []): array
    {
        return EligibilityDecisionDocument::make($this->app->make(OfferingEligibility::class)->evaluate(
            ServiceOfferingVersionRecord::query()->where('service_offering_version_id', '132002988')->with(['coverageReferences', 'eligibilityRules', 'optionRules.optionVersion'])->sole(),
            OfferingSelectionInput::fromArray(['selected_option_version_ids' => array_map(\Tests\Support\FixtureId::from(...), $selected), ...$context]),
        ));
    }
}
