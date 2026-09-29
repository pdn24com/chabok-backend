<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;
use Modules\Manifest\Application\Dto\ManifestCandidateScopeDto;
use Modules\Manifest\Application\Dto\ManifestFiltersDto;
use Modules\Manifest\Application\Services\ManifestEligibilityEvaluator;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;
use Modules\Manifest\Infrastructure\Repositories\EloquentManifestCandidateRepository;
use Tests\Support\RecordFixtureQuery;
use Tests\TestCase;

final class ManifestParcelQueryTest extends TestCase
{
    public function test_candidate_pagination_batches_contacts_and_excludes_foreign_stale_and_assigned_parcels(): void
    {
        $query = new EloquentManifestCandidateRepository;
        $scope = new ManifestCandidateScopeDto(['IR'], false, null, null, '88468052');
        $counts = [];
        for ($index = 1; $index <= 40; $index++) {
            $this->parcel((string) $index);
            if (! in_array($index, [1, 40], true)) {
                continue;
            }
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            try {
                $page = $query->paginateCandidates('245213294', new ManifestFiltersDto(pageSize: 50), $scope);
                foreach ($page as $parcel) {
                    self::assertSame('Receiver', $parcel->consignment->receiver_contact_name);
                }
                $counts[] = count(DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
            self::assertSame($index, $page->total());
        }
        self::assertSame([3, 3], $counts);
        $this->parcel('106329882', '106329882');
        $this->parcel('168030777');
        RecordFixtureQuery::table('consignments')->where('consignment_id', '168030777')->update(['service_offering_id' => '90771604', 'commercial_pricing_state' => 'STALE']);
        $this->parcel('49002794');
        RecordFixtureQuery::table('manifest_parcels')->insert(['manifest_parcel_id' => '49002794', 'hq_id' => '245213294', 'manifest_id' => '227711138', 'parcel_id' => '49002794', 'manifest_parcel_status' => 'PENDING', 'input_source' => 'SCAN', 'input_value' => '49002794', 'created_by' => '84712523', 'active_slot' => 'slot']);
        self::assertCount(40, $query->candidates('245213294', new ManifestFiltersDto, $scope));
        $scope = new ManifestCandidateScopeDto(['IR'], true, '69006970', null, '88468052');
        self::assertCount(0, $query->candidates('245213294', new ManifestFiltersDto, $scope));
        RecordFixtureQuery::table('manifest_parcels')->insert(['manifest_parcel_id' => '69006970', 'hq_id' => '245213294', 'manifest_id' => '69006970', 'parcel_id' => '1', 'manifest_parcel_status' => 'SUCCEEDED', 'input_source' => 'SCAN', 'input_value' => '1', 'created_by' => '84712523']);
        self::assertSame(['1'], $query->candidates('245213294', new ManifestFiltersDto, $scope)->pluck('parcel_id')->all());
    }

    public function test_visibility_requires_tenant_and_preserves_node_contact_task_and_route_scopes(): void
    {
        foreach (['88468052', '32917576', '189461406', '47262741', '259303271', '145247809', '240536385'] as $id) {
            $this->parcel($id);
        }
        RecordFixtureQuery::table('parcels')->where('parcel_id', '!=', '88468052')->update(['current_node_id' => '129087332']);
        RecordFixtureQuery::table('consignments')->where('consignment_id', '32917576')->update(['pickup_node_id' => '88468052']);
        RecordFixtureQuery::table('consignments')->where('consignment_id', '189461406')->update(['delivery_node_id' => '88468052']);
        foreach (['pickup', 'delivery'] as $kind) {
            RecordFixtureQuery::table($kind.'_tasks')->insert([$kind.'_task_id' => \Tests\Support\FixtureId::from($kind), 'hq_id' => '245213294', 'consignment_id' => \Tests\Support\FixtureId::from($kind.'-task'), 'node_id' => '88468052', 'status' => 'IN_PROGRESS']);
        }
        RecordFixtureQuery::table('route_plan_legs')->insert(['route_plan_leg_id' => '209489866', 'hq_id' => '245213294', 'route_plan_id' => '105413112', 'source_route_definition_leg_id' => '69006970', 'leg_order' => 1, 'origin_node_id' => '129087332', 'destination_node_id' => '88468052', 'status' => 'IN_TRANSIT']);
        RecordFixtureQuery::table('parcels')->where('parcel_id', '145247809')->update(['active_route_plan_leg_id' => '209489866']);
        $this->parcel('106329882', '106329882');
        $query = new EloquentManifestCandidateRepository;
        self::assertEqualsCanonicalizing(['88468052', '32917576', '189461406', '47262741', '259303271', '145247809'], $query->visibleParcelIds('245213294', '88468052'));
        RecordFixtureQuery::table('route_plan_legs')->update(['status' => 'RECEIVED']);
        RecordFixtureQuery::table('pickup_tasks')->update(['status' => 'COMPLETED']);
        RecordFixtureQuery::table('delivery_tasks')->update(['status' => 'COMPLETED']);
        self::assertEqualsCanonicalizing(['88468052', '32917576', '189461406'], $query->visibleParcelIds('245213294', '88468052'));
    }

    public function test_eligibility_batches_pickup_evidence_and_refreshes_between_operations(): void
    {
        $manifest = (new ManifestRecord)->forceFill(['hq_id' => '245213294', 'manifest_status' => 'PU', 'assigned_driver_id' => '189656963']);
        $evaluator = $this->app->make(ManifestEligibilityEvaluator::class);
        $counts = [];
        for ($index = 1; $index <= 40; $index++) {
            $this->parcel((string) $index);
            RecordFixtureQuery::table('parcels')->where('parcel_id', (string) $index)->update(['current_status' => 'PD', 'current_node_id' => null, 'current_custody_type' => 'PICKUP_DRIVER', 'current_custodian_id' => '189656963']);
            RecordFixtureQuery::table('pickup_tasks')->insert(['pickup_task_id' => \Tests\Support\FixtureId::from('task-'.$index), 'hq_id' => '245213294', 'consignment_id' => (string) $index, 'node_id' => '88468052', 'assigned_driver_id' => '189656963', 'status' => 'ASSIGNED']);
            if (! in_array($index, [1, 40], true)) {
                continue;
            }
            $parcels = ParcelRecord::query()->get();
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            try {
                $results = $evaluator->evaluateMany($parcels, $manifest, '88468052');
                $counts[] = count(DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
            self::assertCount($index, $results);
            foreach ($results as $result) {
                self::assertTrue($result->eligible());
            }
        }
        self::assertSame([2, 2], $counts);
        RecordFixtureQuery::table('pickup_tasks')->where('consignment_id', '1')->update(['status' => 'FAILED']);
        RecordFixtureQuery::table('pickup_tasks')->where('consignment_id', '2')->update(['assigned_driver_id' => '227711138']);
        RecordFixtureQuery::table('pickup_tasks')->where('consignment_id', '3')->update(['hq_id' => '106329882']);
        RecordFixtureQuery::table('consignments')->where('consignment_id', '4')->update(['service_offering_id' => '90771604', 'commercial_pricing_state' => 'STALE']);
        $results = $evaluator->evaluateMany(ParcelRecord::query()->get(), $manifest, '88468052');
        foreach (['1', '2', '3'] as $id) {
            self::assertSame('PICKUP_ASSIGNMENT_MISMATCH', $results[$id]->value);
        }
        self::assertSame('PRICING_STALE', $results['4']->value);
        self::assertTrue($results['5']->eligible());
        $manifest->hq_id = '106329882';
        $results = $evaluator->evaluateMany(ParcelRecord::query()->get(), $manifest, '88468052');
        self::assertSame(['PARCEL_NOT_FOUND'], array_values(array_unique(array_column($results, 'value'))));
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.manifest_parcel_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('manifest_parcel_test');
        Schema::create('consignments', function (Blueprint $table): void {
            $table->increments('id');
            foreach (['consignment_id', 'hq_id', 'consignment_number', 'receiver_contact_name', 'pickup_node_id', 'delivery_node_id', 'service_offering_id', 'commercial_pricing_state'] as $field) {
                $table->string($field)->nullable();
            }
        });
        foreach (['Consignment' => ['parcels'], 'Manifest' => ['manifest_parcels'], 'Operations' => ['pickup_tasks', 'delivery_tasks', 'route_plan_legs']] as $module => $tables) {
            foreach ($tables as $table) {
                (require glob(base_path("Modules/{$module}/database/migrations/*_create_{$table}.php"))[0])->up();
            }
        }
    }

    private function parcel(string $id, string $tenant = '245213294'): void
    {
        RecordFixtureQuery::table('consignments')->insert(['consignment_id' => $id, 'hq_id' => $tenant, 'consignment_number' => 'C'.$id, 'receiver_contact_name' => 'Receiver']);
        RecordFixtureQuery::table('parcels')->insert(['parcel_id' => $id, 'hq_id' => $tenant, 'consignment_id' => $id, 'parcel_number' => 'P'.$id, 'current_status' => 'IR', 'current_node_id' => '88468052']);
    }
}
