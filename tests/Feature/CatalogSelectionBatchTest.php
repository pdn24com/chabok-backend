<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\Pricing\Application\Dto\PricingZoneColumnDto;
use Modules\Pricing\Application\Dto\TariffDraftDto;
use Modules\Pricing\Application\Services\TariffMatrixCompiler;
use Modules\Pricing\Domain\Exceptions\InvalidTariffMatrix;
use Modules\ServiceCatalog\Application\Contracts\CatalogResolverInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogSelectionInspectorInterface;
use Modules\ServiceCatalog\Application\Dto\CatalogSelectionDto;
use Tests\Support\RecordFixtureQuery;
use Tests\TestCase;

final class CatalogSelectionBatchTest extends TestCase
{
    public function test_publication_visibility_and_binding_have_distinct_meanings(): void
    {
        $selections = array_map(static fn ($reference) => new CatalogSelectionDto($reference, '124644788'), ['94250945', '39581922', '192807878', '268307057', 'missing']);
        $result = $this->app->make(CatalogSelectionInspectorInterface::class)->inspect('245213294', $selections);
        self::assertTrue($result[0]->offeringPublished);
        self::assertTrue($result[0]->offeringAvailable);
        self::assertTrue($result[0]->optionBound, 'An old revision resolves through the latest published identity.');
        self::assertFalse($result[1]->offeringPublished, 'Publication validation requires a revision reference.');
        self::assertTrue($result[1]->offeringAvailable, 'Draft preparation also accepts a stable identity.');
        self::assertTrue($result[1]->optionBound);
        self::assertTrue($result[2]->offeringPublished);
        self::assertFalse($result[2]->offeringAvailable);
        self::assertFalse($result[2]->optionBound);
        self::assertTrue($result[3]->offeringAvailable);
        self::assertFalse($result[3]->optionBound);
        self::assertFalse($result[4]->offeringAvailable);
    }

    public function test_matrix_preparation_has_a_bounded_query_count(): void
    {
        $counts = [];
        foreach ([1, 100] as $count) {
            $matrices = [];
            $zones = [];
            for ($index = 0; $index < $count; $index++) {
                $zone = \Tests\Support\FixtureId::from('zone-'.$index);
                $zones[] = new PricingZoneColumnDto($zone, $index + 1);
                $matrices[] = ['id' => \Tests\Support\FixtureId::from('matrix-'.$index), 'service_offering_version_id' => '94250945', 'service_option_version_id' => '124644788',
                    'origin_zone_id' => $zone, 'zone_ids' => [$zone], 'bands' => []];
            }
            DB::connection()->flushQueryLog();
            DB::connection()->enableQueryLog();
            try {
                $result = $this->app->make(TariffMatrixCompiler::class)->prepare(TariffDraftDto::fromInput(['freight_matrices' => $matrices]), $zones, '245213294');
                $counts[] = count(DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
            self::assertCount($count, $result->freightMatrices);
        }
        self::assertSame($counts[0], $counts[1]);
        self::assertLessThanOrEqual(10, $counts[1]);
    }

    public function test_option_revision_groups_preserve_reference_order_and_tenant_visibility(): void
    {
        RecordFixtureQuery::table('service_option_versions')->insert(['service_option_version_id' => '209425022', 'service_option_id' => '168929119', 'version_number' => 2,
            'labels' => '{}', 'definition' => '{}', 'created_by' => '197574617', 'status' => 'PUBLISHED']);
        $resolver = $this->app->make(CatalogResolverInterface::class);
        $groups = $resolver->optionRevisions(['124644788', '168929119', 'missing'], '245213294');
        self::assertSame(['124644788', '168929119', 'missing'], array_map(static fn ($group) => $group->reference, $groups));
        self::assertSame('209425022', $groups[0]->currentVersionId);
        self::assertSame(['124644788', '209425022'], $groups[1]->versionIds);
        self::assertSame([], $groups[2]->versionIds);
        self::assertNull($groups[2]->currentVersionId);
        $foreign = $resolver->optionRevisions(['168929119'], '227711138')[0];
        self::assertNull($foreign->currentVersionId);
        self::assertSame(['124644788', '209425022'], $foreign->versionIds, 'Unscoped revision matching preserves historical links.');
    }

    public function test_distinct_option_families_load_in_two_queries(): void
    {
        $references = [];
        for ($index = 0; $index < 100; $index++) {
            $id = \Tests\Support\FixtureId::from('batch-'.$index);
            RecordFixtureQuery::table('service_options')->insert(['service_option_id' => $id, 'hq_id' => '245213294', 'owner_key' => '245213294', 'code' => $id, 'created_by' => '197574617', 'status' => 'ACTIVE']);
            RecordFixtureQuery::table('service_option_versions')->insert(['service_option_version_id' => \Tests\Support\FixtureId::from($id.'-1'), 'service_option_id' => $id, 'version_number' => 1,
                'labels' => '{}', 'definition' => '{}', 'created_by' => '197574617', 'status' => 'PUBLISHED']);
            $references[] = \Tests\Support\FixtureId::from($id.'-1');
        }
        $counts = [];
        foreach ([[$references[0]], $references] as $selection) {
            DB::connection()->flushQueryLog();
            DB::connection()->enableQueryLog();
            try {
                $groups = $this->app->make(CatalogResolverInterface::class)->optionRevisions($selection, '245213294');
                $counts[] = count(DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
            self::assertCount(count($selection), $groups);
            self::assertSame($selection[count($selection) - 1], $groups[count($groups) - 1]->currentVersionId);
        }
        self::assertSame([2, 2], $counts);
    }

    public function test_foreign_offering_returns_a_typed_validation_failure(): void
    {
        try {
            $this->app->make(TariffMatrixCompiler::class)->prepare(TariffDraftDto::fromInput(['freight_matrices' => [[
                'id' => '115346646', 'service_offering_version_id' => '192807878', 'origin_zone_id' => '88335165', 'zone_ids' => ['88335165'], 'bands' => [],
            ]]]), [new PricingZoneColumnDto('88335165', 1)], '245213294');
            self::fail('Foreign offering must be rejected.');
        } catch (InvalidTariffMatrix $error) {
            self::assertFalse($error->validation->valid);
            self::assertSame('PRICING_SERVICE_VERSION_NOT_PUBLISHED', $error->validation->errors[0]->code->value);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.catalog_batch_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('catalog_batch_test');
        foreach (['service_offerings', 'service_offering_versions', 'service_options', 'service_option_versions', 'service_offering_option_rules'] as $table) {
            (require glob(base_path('Modules/ServiceCatalog/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        (require glob(base_path('Modules/Pricing/database/migrations/*_create_pricing_charge_types.php'))[0])->up();
        RecordFixtureQuery::table('pricing_charge_types')->insert(['charge_type_id' => '212756002', 'code' => 'BASE_FREIGHT', 'category' => 'BASE', 'accounting_mapping_key' => '212756002', 'active' => true]);
        foreach (['39581922' => '245213294', '106329882' => '227711138', '134224936' => null] as $id => $hq) {
            RecordFixtureQuery::table('service_offerings')->insert(['service_offering_id' => $id, 'hq_id' => $hq,
                'owner_key' => $hq ?? 'GLOBAL', 'code' => $id, 'status' => 'ACTIVE', 'created_by' => '197574617']);
            foreach ([1, 2] as $number) {
                RecordFixtureQuery::table('service_offering_versions')->insert(['service_offering_version_id' => \Tests\Support\FixtureId::from($id.'-'.$number), 'service_offering_id' => $id,
                    'version_number' => $number, 'service_type_version_id' => '19938311', 'shipping_method_version_id' => '95938240',
                    'labels' => '{}', 'sla_policy' => '{}', 'created_by' => '197574617', 'status' => 'PUBLISHED']);
            }
        }
        RecordFixtureQuery::table('service_options')->insert(['service_option_id' => '168929119', 'hq_id' => '245213294', 'owner_key' => '245213294', 'code' => 'OPTION', 'created_by' => '197574617', 'status' => 'ACTIVE']);
        RecordFixtureQuery::table('service_option_versions')->insert(['service_option_version_id' => '124644788', 'service_option_id' => '168929119', 'version_number' => 1,
            'labels' => '{}', 'definition' => '{}', 'created_by' => '197574617', 'status' => 'PUBLISHED']);
        RecordFixtureQuery::table('service_offering_option_rules')->insert(['offering_option_rule_id' => '135229616', 'service_offering_version_id' => '58258676', 'service_option_version_id' => '124644788', 'compatibility' => 'ALLOWED']);
    }
}
