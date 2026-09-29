<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Modules\Organization\Application\Serialization\NetworkDocument;
use Modules\Organization\Application\UseCases\AddAreaEdge\AddAreaEdgeCommand;
use Modules\Organization\Application\UseCases\AddAreaEdge\AddAreaEdgeHandler;
use Modules\Organization\Infrastructure\Persistence\Models\AreaHierarchyRecord;
use Modules\Organization\Infrastructure\Persistence\Models\AreaRecord;

final class InternalDatabaseKeysTest extends MySqlRedisTestCase
{
    public function test_every_table_has_an_auto_increment_integer_primary_key(): void
    {
        foreach (Schema::getTables() as $table) {
            $primary = array_values(array_filter(Schema::getIndexes($table['name']), fn ($index) => $index['primary']));
            self::assertSame(['id'], $primary[0]['columns'], $table['name']);
            $columns = array_column(Schema::getColumns($table['name']), null, 'name');
            self::assertSame('int', $columns['id']['type_name'], $table['name']);
            self::assertTrue($columns['id']['auto_increment'], $table['name']);
        }
    }

    public function test_model_and_route_use_the_same_generated_identity(): void
    {
        $tenant = $this->tenant('NUMERIC-IDENTITY');
        $area = (new AreaRecord)->forceFill([
            'hq_id' => $tenant['hq_id'],
            'area_code' => 'AREA-1',
            'area_title' => 'Original',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $area->save();
        self::assertIsInt($area->getKey());
        self::assertSame($area->id, $area->getRouteKey());
        self::assertSame((string) $area->id, AreaRecord::query()->findOrFail($area->getKey())->area_id);
        self::assertArrayHasKey('id', $area->toArray());

        $copy = $area->replicate()->forceFill(['area_code' => 'AREA-2']);
        $copy->save();
        self::assertNotSame($area->getKey(), $copy->getKey());
        self::assertNotSame($area->getRouteKey(), $copy->getRouteKey());
    }

    public function test_area_edges_use_numeric_keys_and_keep_public_ids_and_tenant_constraints(): void
    {
        $tenant = $this->tenant('NUMERIC-HIERARCHY');
        $foreign = $this->tenant('FOREIGN-HIERARCHY');
        $areas = [];
        foreach ([$tenant['hq_id'], $tenant['hq_id'], $foreign['hq_id']] as $index => $hqId) {
            $areas[] = AreaRecord::query()->forceCreate(['area_id' => (string) random_int(1, 2000000000), 'hq_id' => $hqId,
                'area_code' => 'AREA-'.$index, 'area_title' => 'Area '.$index, 'status' => 'ACTIVE']);
        }
        $handler = $this->app->make(AddAreaEdgeHandler::class);
        $handler->handle(new AddAreaEdgeCommand($tenant['hq_id'], $areas[0]->area_id, $areas[1]->area_id));
        $edge = AreaHierarchyRecord::query()->where('hq_id', $tenant['hq_id'])->firstOrFail();
        self::assertSame($areas[0]->id, $edge->parent_area_id);
        self::assertSame($areas[1]->id, $edge->child_area_id);
        self::assertSame($areas[0]->area_id, NetworkDocument::area($areas[1]->load('parentEdge.parent'))['parent_area_id']);
        $columns = array_column(Schema::getColumns('area_hierarchies'), null, 'name');
        self::assertSame('int', $columns['parent_area_id']['type_name']);
        self::assertSame('int', $columns['child_area_id']['type_name']);
        $keys = array_column(Schema::getForeignKeys('area_hierarchies'), null, 'name');
        foreach (['area_hierarchy_parent_fk', 'area_hierarchy_child_fk'] as $key) {
            self::assertSame(['hq_id', 'id'], $keys[$key]['foreign_columns']);
        }
        try {
            AreaHierarchyRecord::query()->forceCreate(['area_hierarchy_id' => (string) random_int(1, 2000000000), 'hq_id' => $tenant['hq_id'],
                'parent_area_id' => $areas[2]->id, 'child_area_id' => $areas[0]->id]);
            self::fail('A foreign tenant parent must fail at the database boundary.');
        } catch (QueryException $error) {
            self::assertSame('23000', $error->errorInfo[0]);
        }
        self::assertSame(1, AreaHierarchyRecord::query()->count());
    }
}
