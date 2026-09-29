<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\Manifest\Application\Serialization\ManifestContextDocument;
use Modules\Manifest\Application\Services\ManifestContextOptions;
use Tests\Support\RecordFixtureQuery;
use Tests\TestCase;

final class ManifestContextOptionsNativeTest extends TestCase
{
    public function test_reception_choices_batch_route_evidence_and_preserve_transit_and_final_choices(): void
    {
        config(['database.connections.manifest_context_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('manifest_context_test');
        foreach (['Manifest' => ['manifests', 'manifest_parcels'], 'Consignment' => ['parcels'], 'Operations' => ['route_plans', 'route_plan_legs']] as $module => $tables) {
            foreach ($tables as $table) {
                (require glob(base_path("Modules/{$module}/database/migrations/*_create_{$table}.php"))[0])->up();
            }
        }
        $options = $this->app->make(ManifestContextOptions::class);
        $counts = [];
        for ($index = 1; $index <= 40; $index++) {
            RecordFixtureQuery::table('manifests')->insert(['manifest_id' => \Tests\Support\FixtureId::from("manifest-{$index}"), 'hq_id' => '245213294', 'manifest_number' => "M{$index}", 'node_id' => '25296341', 'origin_node_id' => '25296341', 'destination_node_id' => '190608731', 'manifest_status' => 'OS', 'state' => 'CLOSED', 'created_by' => '84712523', 'operational_context_type' => 'LINEHAUL_DEPARTURE', 'context_key' => "context-{$index}"]);
            foreach (['transit', '38024753'] as $kind) {
                $id = \Tests\Support\FixtureId::from("{$kind}-{$index}");
                RecordFixtureQuery::table('route_plans')->insert(['route_plan_id' => $id, 'hq_id' => '245213294', 'consignment_id' => $id, 'route_definition_id' => '80014619', 'created_by' => '84712523']);
                RecordFixtureQuery::table('route_plan_legs')->insert(['route_plan_leg_id' => $id, 'hq_id' => '245213294', 'route_plan_id' => $id, 'source_route_definition_leg_id' => '69006970', 'leg_order' => 1, 'origin_node_id' => '25296341', 'destination_node_id' => '190608731', 'status' => 'IN_TRANSIT']);
                if ($kind === 'transit') {
                    RecordFixtureQuery::table('route_plan_legs')->insert(['route_plan_leg_id' => \Tests\Support\FixtureId::from("next-{$id}"), 'hq_id' => '245213294', 'route_plan_id' => $id, 'source_route_definition_leg_id' => '239306864', 'leg_order' => 2, 'origin_node_id' => '190608731', 'destination_node_id' => '38024753', 'status' => 'PENDING']);
                }
                // Reception existence uses the current parcel state; choices retain all successful evidence.
                RecordFixtureQuery::table('parcels')->insert(['parcel_id' => $id, 'hq_id' => '245213294', 'consignment_id' => $id, 'parcel_number' => $id, 'current_status' => $kind === 'transit' ? 'OS' : 'IR']);
                RecordFixtureQuery::table('manifest_parcels')->insert(['manifest_parcel_id' => $id, 'hq_id' => '245213294', 'manifest_id' => \Tests\Support\FixtureId::from("manifest-{$index}"), 'parcel_id' => $id, 'route_plan_leg_id' => $id, 'manifest_parcel_status' => 'SUCCEEDED', 'input_source' => 'SCAN', 'input_value' => $id, 'created_by' => '84712523']);
            }
            if (! in_array($index, [1, 40], true)) {
                continue;
            }
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            try {
                $data = array_map($this->app->make(ManifestContextDocument::class)->optionResource(...), $options->movementReceptionOptions('245213294', '190608731'));
                $counts[] = count(DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
            self::assertCount($index * 2, $data);
            self::assertSame(['CI', 'IR'], array_column(array_slice($data, 0, 2), 'manifest_status'));
        }
        self::assertSame([5, 5], $counts);
        self::assertSame([], $options->movementReceptionOptions('106329882', '190608731'));
        self::assertSame([], $options->movementReceptionOptions('245213294', '129087332'));
        RecordFixtureQuery::table('parcels')->update(['current_status' => 'IR']);
        self::assertSame([], $options->movementReceptionOptions('245213294', '190608731'));
    }
}
