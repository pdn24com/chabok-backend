<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Pricing\Application\Enums\ServiceDependencyFailure;
use Modules\Pricing\Application\Services\ServiceTariffDependencies;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingChargeTypeRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffFamilyRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffVersionRecord;
use Tests\TestCase;

final class ServiceTariffDependenciesTest extends TestCase
{
    public function test_latest_effective_revision_is_selected_per_family_with_a_fixed_query_count(): void
    {
        $ids = [];
        for ($index = 0; $index < 10; $index++) {
            $ids[] = \Tests\Support\FixtureId::from('family-'.$index);
            $this->family(\Tests\Support\FixtureId::from('family-'.$index));
        }
        $queryCounts = [];
        foreach ([[$ids[0]], $ids] as $selection) {
            DB::connection()->flushQueryLog();
            DB::connection()->enableQueryLog();
            try {
                $result = $this->dependencies()->inspect($selection, '245213294', null, CarbonImmutable::parse('2026-09-23'), []);
                $queryCounts[] = count(DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
                DB::connection()->flushQueryLog();
            }
            self::assertNull($result->failure);
            self::assertCount(count($selection), $result->tariffs);
            foreach ($result->tariffs as $index => $tariff) {
                self::assertSame(\Tests\Support\FixtureId::from($selection[$index].'-v2'), $tariff->version->tariff_version_id);
            }
        }
        self::assertSame($queryCounts[0], $queryCounts[1]);
    }

    public function test_foreign_tenant_and_unpublished_families_are_not_available(): void
    {
        $this->family('106329882', '136028801');
        $this->family('136079804');
        TariffVersionRecord::query()->where('tariff_family_id', '136079804')->update(['status' => 'DRAFT']);
        $service = $this->dependencies();
        $at = CarbonImmutable::parse('2026-09-23');
        self::assertSame(ServiceDependencyFailure::FamilyUnavailable, $service->inspect(['106329882'], '245213294', null, $at, [])->failure);
        self::assertSame(ServiceDependencyFailure::VersionUnpublished, $service->inspect(['136079804'], '245213294', null, $at, [])->failure);
        self::assertSame(ServiceDependencyFailure::DuplicateFamily, $service->inspect(['106329882', '106329882'], '245213294', null, $at, [])->failure);
    }

    public function test_charge_aliases_conflict_with_local_rules_and_other_service_tariffs(): void
    {
        $this->family('105810232');
        $this->family('192990635');
        PricingChargeTypeRecord::query()->where('charge_type_id', \Tests\Support\FixtureId::from('insurance-charge'))->update(['code' => 'INSURANCE']);
        PricingChargeTypeRecord::query()->where('charge_type_id', \Tests\Support\FixtureId::from('fee-charge'))->update(['code' => 'INSURANCE_FEE']);
        $service = $this->dependencies();
        $at = CarbonImmutable::parse('2026-09-23');
        self::assertSame(ServiceDependencyFailure::DuplicateCharge, $service->inspect(['105810232', '192990635'], '245213294', null, $at, [])->failure);
        self::assertSame(ServiceDependencyFailure::DuplicateCharge, $service->inspect(['105810232'], '245213294', null, $at, [\Tests\Support\FixtureId::from('fee-charge')])->failure);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.service_tariff_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('service_tariff_test');
        foreach (['pricing_charge_types', 'tariff_families', 'tariff_versions', 'pricing_zone_set_versions'] as $table) {
            $migration = require glob(base_path('Modules/Pricing/database/migrations/*_create_'.$table.'.php'))[0];
            $migration->up();
        }
    }

    private function family(string $id, string $hqId = '245213294'): void
    {
        PricingChargeTypeRecord::query()->insert(['charge_type_id' => \Tests\Support\FixtureId::from($id.'-charge'), 'code' => $id, 'category' => 'SURCHARGE', 'accounting_mapping_key' => $id]);
        TariffFamilyRecord::query()->insert(['tariff_family_id' => $id, 'hq_id' => $hqId, 'owner_key' => $hqId, 'code' => $id, 'purpose' => 'SALES', 'created_by' => '5212567', 'tariff_kind' => 'SERVICE', 'service_charge_type_id' => \Tests\Support\FixtureId::from($id.'-charge')]);
        foreach ([1, 2, 3] as $revision) {
            TariffVersionRecord::query()->insert(['tariff_version_id' => \Tests\Support\FixtureId::from($id.'-v'.$revision), 'tariff_family_id' => $id, 'version_number' => $revision, 'status' => 'PUBLISHED', 'valid_from' => $revision === 3 ? '2030-01-01 00:00:00' : '2020-01-01 00:00:00', 'created_by' => '5212567']);
        }
    }

    private function dependencies(): ServiceTariffDependencies
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(CarbonImmutable::parse('2026-09-23'));

        $this->app->instance(ClockInterface::class, $clock);

        return $this->app->make(ServiceTariffDependencies::class);
    }
}
