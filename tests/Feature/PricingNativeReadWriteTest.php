<?php

declare(strict_types=1);

namespace Tests\Feature;

use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Mockery;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Pricing\Application\Dto\PricingFiltersDto;
use Modules\Pricing\Application\Dto\PricingRateRuleDraftDto;
use Modules\Pricing\Application\Mappers\PricingCalculationInput;
use Modules\Pricing\Application\Services\PricingConfigurationWriter;
use Modules\Pricing\Application\UseCases\ListTariffs\ListTariffsCommand;
use Modules\Pricing\Application\UseCases\ListTariffs\ListTariffsHandler;
use Modules\Pricing\Domain\ValueObjects\CalculationLine;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingQuoteLineRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffRateRuleRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffVersionRecord;
use Modules\Pricing\Infrastructure\Repositories\EloquentTariffRepository;
use Tests\Support\AccessContexts;
use Tests\Support\RecordFixtureQuery;
use Tests\TestCase;

final class PricingNativeReadWriteTest extends TestCase
{
    public function test_list_is_tenant_scoped_and_eager_loads_latest_revision_in_constant_queries(): void
    {
        $counts = [];
        for ($index = 1; $index <= 40; $index++) {
            $id = \Tests\Support\FixtureId::from(sprintf('family-%02d', $index));
            $this->family($id);
            $this->version($id, \Tests\Support\FixtureId::from($id.'-published'));
            $this->version($id, \Tests\Support\FixtureId::from($id.'-draft'), ['version_number' => 2, 'status' => 'DRAFT']);
            if (in_array($index, [1, 40], true)) {
                $page = $this->measure($counts, fn () => $this->app->make(ListTariffsHandler::class)->handle(new ListTariffsCommand(new AuthenticatedPrincipal('84712523', 'session', '245213294', false), PricingFiltersDto::fromInput(['page_size' => 100]))));
                self::assertSame($index, $page->total());
                self::assertSame('DRAFT', $page->items()[0]['latest_status']);
                self::assertSame(2, $page->items()[0]['latest_version_number']);
                self::assertArrayHasKey('id', $page->items()[0]);
                self::assertArrayNotHasKey('latest_version', $page->items()[0]);
            }
        }
        self::assertSame([3, 3], $counts);
        $this->family('106329882', ['hq_id' => '227711138']);
        $this->family('134224936', ['hq_id' => null]);
        $page = $this->app->make(ListTariffsHandler::class)->handle(new ListTariffsCommand(new AuthenticatedPrincipal('84712523', 'session', '245213294', false), PricingFiltersDto::fromInput(['page_size' => 100])));
        self::assertSame(41, $page->total());
        self::assertNotContains('106329882', array_column($page->items(), 'tariff_family_id'));
        self::assertContains('134224936', array_column($page->items(), 'tariff_family_id'));
    }

    public function test_eligible_tariff_preserves_effective_successors_scope_priority_and_native_json(): void
    {
        $this->family('245213294');
        $this->version('245213294', '213518006', ['freight_matrices' => '[{"id":"matrix","enabled":true}]']);
        $this->rule('213518006');
        $this->family('220811213', ['hq_id' => null, 'scope_type' => 'PLATFORM']);
        $this->version('220811213', '12692996');
        $this->rule('12692996');
        $this->family('106329882', ['hq_id' => '227711138', 'priority' => 1]);
        $this->version('106329882', '262456316', ['is_default' => 1]);
        $this->rule('262456316');
        $query = new EloquentTariffRepository;
        $at = new DateTimeImmutable('2026-09-24T00:00:00Z');
        $counts = [];
        $chosen = $this->measure($counts, fn () => $query->findEligibleFreightVersion('245213294', ['90771604'], $at));
        self::assertSame([2], $counts);
        self::assertInstanceOf(TariffVersionRecord::class, $chosen);
        self::assertSame('213518006', $chosen->tariff_version_id);
        self::assertSame('245213294', $chosen->family->code);
        self::assertSame([['id' => 'matrix', 'enabled' => true]], $chosen->freight_matrices);
        self::assertSame('PUBLISHED', $chosen->status);
        $this->version('245213294', '125058276', ['version_number' => 2, 'status' => 'DRAFT']);
        $this->version('245213294', '247152105', ['version_number' => 3, 'valid_from' => '2026-09-25 00:00:00']);
        $this->version('245213294', '262557346', ['version_number' => 4, 'valid_to' => '2026-09-24 00:00:00']);
        self::assertSame('213518006', $query->findEligibleFreightVersion('245213294', ['90771604'], $at)->tariff_version_id);
        // A current successor suppresses the old version even when it removed this offering.
        $this->version('245213294', '88663408', ['version_number' => 5]);
        self::assertSame('12692996', $query->findEligibleFreightVersion('245213294', ['90771604'], $at)->tariff_version_id);
        $this->rule('88663408');
        self::assertSame('88663408', $query->findEligibleFreightVersion('245213294', ['90771604'], $at)->tariff_version_id);
        RecordFixtureQuery::table('tariff_versions')->where('tariff_version_id', '12692996')->update(['is_default' => 1]);
        self::assertSame('12692996', $query->findEligibleFreightVersion('245213294', ['90771604'], $at)->tariff_version_id);
        self::assertNull($query->findEligibleFreightVersion('245213294', ['unknown'], $at));
        $model = TariffVersionRecord::query()->where('tariff_version_id', '125058276')->sole();
        $model->forceFill(['valid_from' => '2026-09-24 00:00:00.123456'])->save();
        self::assertSame('2026-09-24 00:00:00.123456', $model->fresh()->getRawOriginal('valid_from'));
    }

    public function test_rules_and_quote_lines_are_batched_with_exact_json_and_atomic_replacement(): void
    {
        $writer = $this->app->make(PricingConfigurationWriter::class);
        $ruleCounts = $lineCounts = [];
        foreach ([1, 40, 101] as $count) {
            $rules = $lines = [];
            for ($number = 1; $number <= $count; $number++) {
                $rules[] = ['matrix_cell_id' => \Tests\Support\FixtureId::from('cell-'.$number), 'service_offering_version_id' => '90771604', 'charge_type_id' => '158632188',
                    'calculation_method' => 'PER_UNIT', 'unit_rate' => '123.125000', 'basis_charge_codes' => ['BASE_FREIGHT'],
                    'conditions' => ['remote_area' => false, 'parcel_count' => 0], 'taxable' => false, 'priority' => $number];
                $lines[] = new CalculationLine(PricingCalculationInput::rule([
                    'rate_rule_id' => \Tests\Support\FixtureId::from('rule-'.$number), 'charge_type_id' => '158632188', 'charge_type_code' => 'FREIGHT', 'title' => 'حمل',
                    'category' => 'BASE', 'calculation_method' => 'PER_UNIT', 'basis' => 'BILLABLE_WEIGHT', 'priority' => $number,
                    'unit_rate' => '123.125000', 'accounting_mapping_key' => 'REVENUE', 'taxable' => false,
                ]), 1.25, 154, 154);
            }
            $this->measure($ruleCounts, fn () => DB::transaction(fn () => $writer->replaceRules('97144633', array_map(PricingRateRuleDraftDto::fromInput(...), $rules))));
            $this->measure($lineCounts, fn () => DB::transaction(fn () => $writer->insertLines(\Tests\Support\FixtureId::from('quote-'.$count), $lines)));
            $storedRules = TariffRateRuleRecord::query()->where('tariff_version_id', '97144633')->orderBy('priority')->get();
            $storedLines = PricingQuoteLineRecord::query()->where('quote_id', \Tests\Support\FixtureId::from('quote-'.$count))->orderBy('line_number')->get();
            self::assertCount($count, $storedRules);
            self::assertCount($count, $storedLines);
            self::assertSame(['remote_area' => false, 'parcel_count' => 0], $storedRules[0]->conditions);
            self::assertSame(['BASE_FREIGHT'], $storedRules[0]->basis_charge_codes);
            self::assertFalse((bool) $storedRules[0]->taxable);
            self::assertSame(154, $storedLines[0]->explanation['raw_amount']);
            self::assertNull($storedLines[0]->explanation['percentage_bps']);
            self::assertSame($count, $storedLines->last()->line_number);
            self::assertCount($count, $storedRules->pluck('rate_rule_id')->unique());
        }
        self::assertSame([2, 2, 3], $ruleCounts);
        self::assertSame([1, 1, 2], $lineCounts);
        $before = TariffRateRuleRecord::query()->get()->toArray();
        try {
            DB::transaction(fn () => $writer->replaceRules('97144633', array_map(PricingRateRuleDraftDto::fromInput(...), [$rules[0], $rules[0]])));
            self::fail('Duplicate cells must roll back replacement.');
        } catch (QueryException) {
            self::assertSame($before, TariffRateRuleRecord::query()->get()->toArray());
        }
        self::assertSame(1, PricingQuoteLineRecord::query()->where('quote_id', '220281940')->count());
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.pricing_native' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('pricing_native');
        foreach (['tariff_families', 'tariff_versions', 'tariff_rate_rules', 'pricing_quote_lines'] as $table) {
            (require glob(base_path('Modules/Pricing/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        $authorization = Mockery::mock(AccessContextResolverInterface::class);
        $authorization->shouldReceive('resolve')->andReturn(AccessContexts::make([
            'hq_id' => '245213294', 'permissions' => ['pricing.tariff.view'],
            'module_entitlements' => [['module_code' => 'Pricing', 'status' => 'ENABLED']],
        ]));
        $this->app->instance(AccessContextResolverInterface::class, $authorization);
    }

    private function family(string $id, array $overrides = []): void
    {
        RecordFixtureQuery::table('tariff_families')->insert(array_replace(['tariff_family_id' => $id, 'hq_id' => '245213294', 'owner_key' => $id,
            'code' => $id, 'purpose' => 'SALES', 'scope_type' => 'TENANT', 'created_by' => '84712523'], $overrides));
    }

    private function version(string $family, string $id, array $overrides = []): void
    {
        RecordFixtureQuery::table('tariff_versions')->insert(array_replace(['tariff_version_id' => $id, 'tariff_family_id' => $family, 'hq_id' => '245213294',
            'version_number' => 1, 'status' => 'PUBLISHED', 'valid_from' => '2026-09-23 00:00:00', 'created_by' => '84712523'], $overrides));
    }

    private function rule(string $version): void
    {
        RecordFixtureQuery::table('tariff_rate_rules')->insert(['rate_rule_id' => \Tests\Support\FixtureId::from('rule-'.$version), 'tariff_version_id' => $version,
            'service_offering_version_id' => '90771604', 'charge_type_id' => '158632188', 'calculation_method' => 'FIXED']);
    }

    private function measure(array &$counts, callable $operation): mixed
    {
        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();
        try {
            $result = $operation();
            $counts[] = count(DB::connection()->getQueryLog());

            return $result;
        } finally {
            DB::connection()->disableQueryLog();
        }
    }
}
