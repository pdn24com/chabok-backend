<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Operations\Application\Services\RoutePlanReader;
use Modules\Operations\Infrastructure\Repositories\EloquentManifestRouteAccess;
use Modules\Operations\Presentation\Http\Resources\RoutePlanResource;
use Tests\Support\RecordFixtureQuery;
use Tests\TestCase;

final class MovementReadTest extends TestCase
{
    public function test_plan_graph_including_evidence_serializes_in_eleven_queries_for_one_or_forty_plans(): void
    {
        $reader = $this->app->make(RoutePlanReader::class);
        $counts = [];
        for ($index = 1; $index <= 40; $index++) {
            $this->plan($index);
            if (! in_array($index, [1, 40], true)) {
                continue;
            }
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            try {
                $plans = $reader->visibleAtNode('245213294', '25296341');
                $data = RoutePlanResource::collection($plans)->resolve();
                $counts[] = count(DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
            self::assertCount($index, $data);
            self::assertSame('AVAILABLE', $data[0]['configuration_state']);
            self::assertSame(['city_id' => '18506435'], $data[0]['resolution_evidence']['resolution_input']);
            self::assertSame('190608731', $data[0]['legs'][0]['destination_node']['node_id']);
        }
        self::assertSame([11, 11], $counts);
        self::assertNull($reader->find('106329882', '237427150', '25296341'));
        self::assertNull($reader->find('245213294', '237427150', 'unrelated'));
        self::assertNotNull($reader->find('245213294', '237427150', '190608731'));
        RecordFixtureQuery::table('coverage_policy_versions')->update(['status' => 'SUPERSEDED']);
        self::assertSame('STALE', (new RoutePlanResource($reader->find('245213294', '237427150', '25296341')))->resolve()['configuration_state']);
    }

    public function test_manifest_summary_uses_plan_status_and_revision_without_join_column_collision(): void
    {
        $this->plan(1);
        $access = new EloquentManifestRouteAccess;
        $plan = $access->planSummary('245213294', '237427150');
        self::assertSame('PLANNED', $plan->status);
        self::assertSame(2, $plan->version);
        self::assertSame('ACTIVE', $plan->definition->status);
        self::assertSame(8, $plan->definition->version);
        self::assertSame('CN1', $plan->consignment->consignment_number);
        self::assertNull($access->planSummary('106329882', '237427150'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.movement_read_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('movement_read_test');
        // The Consignment projection needs only these fields; full schema constraints are covered by MySQL integration.
        Schema::create('consignments', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('hq_id');
            $table->string('consignment_number');
            $table->string('pickup_node_id');
        });
        foreach (['route_plans', 'route_plan_legs', 'route_definitions', 'route_definition_versions', 'route_plan_resolution_evidence', 'coverage_policies', 'coverage_policy_versions'] as $table) {
            (require glob(base_path('Modules/Operations/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        (require glob(base_path('Modules/Organization/database/migrations/*_create_nodes.php'))[0])->up();
        foreach (['25296341', '190608731'] as $node) {
            RecordFixtureQuery::table('nodes')->insert(['node_id' => $node, 'hq_id' => '245213294', 'area_id' => '78192358', 'node_code' => $node, 'node_title' => $node, 'node_type' => 'HUB']);
        }
        RecordFixtureQuery::table('route_definitions')->insert(['route_definition_id' => '80014619', 'hq_id' => '245213294', 'route_code' => '145247809', 'route_title' => 'Route', 'status' => 'ACTIVE', 'version' => 8]);
        RecordFixtureQuery::table('route_definition_versions')->insert(['route_definition_version_id' => '264442585', 'route_definition_id' => '80014619', 'hq_id' => '245213294', 'version_number' => 1, 'status' => 'PUBLISHED', 'purpose' => 'TRUNK', 'origin_node_id' => '25296341', 'destination_node_id' => '190608731']);
        RecordFixtureQuery::table('coverage_policies')->insert(['coverage_policy_id' => '136528174', 'hq_id' => '245213294', 'policy_code' => 'coverage', 'policy_title' => 'Coverage']);
        RecordFixtureQuery::table('coverage_policy_versions')->insert(['coverage_policy_version_id' => '173522071', 'coverage_policy_id' => '136528174', 'hq_id' => '245213294', 'version_number' => 1, 'status' => 'PUBLISHED', 'created_by' => '5212567']);
    }

    private function plan(int $index): void
    {
        RecordFixtureQuery::table('consignments')->insert(['consignment_id' => \Tests\Support\FixtureId::from('c'.$index), 'hq_id' => '245213294', 'consignment_number' => 'CN'.$index, 'pickup_node_id' => '25296341']);
        RecordFixtureQuery::table('route_plans')->insert(['route_plan_id' => \Tests\Support\FixtureId::from('plan-'.$index), 'hq_id' => '245213294', 'consignment_id' => \Tests\Support\FixtureId::from('c'.$index), 'route_definition_id' => '80014619', 'route_definition_version_id' => '264442585', 'created_by' => '5212567', 'version' => 2]);
        RecordFixtureQuery::table('route_plan_legs')->insert(['route_plan_leg_id' => \Tests\Support\FixtureId::from('leg-'.$index), 'hq_id' => '245213294', 'route_plan_id' => \Tests\Support\FixtureId::from('plan-'.$index), 'source_route_definition_leg_id' => '206175912', 'leg_order' => 1, 'origin_node_id' => '25296341', 'destination_node_id' => '190608731', 'status' => 'PENDING']);
        RecordFixtureQuery::table('route_plan_resolution_evidence')->insert(['resolution_evidence_id' => \Tests\Support\FixtureId::from('evidence-'.$index), 'hq_id' => '245213294', 'consignment_id' => \Tests\Support\FixtureId::from('c'.$index), 'route_plan_id' => \Tests\Support\FixtureId::from('plan-'.$index),
            'coverage_policy_id' => '136528174', 'coverage_policy_version_id' => '173522071', 'coverage_rule_id' => '121018698', 'coverage_criterion_type' => 'CITY', 'coverage_priority' => 10,
            'resolution_input' => '{"city_id":"18506435"}', 'destination_gateway_node_id' => '190608731', 'route_definition_id' => '80014619', 'route_definition_version_id' => '264442585', 'route_purpose' => 'TRUNK', 'ordered_route_legs' => '[]', 'resolved_at' => '2026-09-23 10:00:00']);
    }
}
