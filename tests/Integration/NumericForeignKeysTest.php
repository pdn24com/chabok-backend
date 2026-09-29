<?php

declare(strict_types=1);

namespace Tests\Integration;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Authorization\Infrastructure\Persistence\Models\AssignmentRecord;
use Modules\Authorization\Infrastructure\Persistence\Models\RoleRecord;
use Modules\Geography\Infrastructure\Persistence\Models\CityRecord;
use Modules\Geography\Infrastructure\Persistence\Models\ProvinceRecord;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

/** These assertions use raw database keys and the installed MySQL constraints. */
final class NumericForeignKeysTest extends MySqlRedisTestCase
{
    public function test_every_foreign_key_has_matching_unsigned_integer_columns(): void
    {
        $columns = [];
        foreach (Schema::getTables() as $table) {
            $columns[$table['name']] = array_column(Schema::getColumns($table['name']), null, 'name');
        }
        $count = 0;
        foreach ($columns as $table => $definition) {
            foreach (Schema::getForeignKeys($table) as $foreign) {
                $count++;
                foreach ($foreign['columns'] as $index => $column) {
                    $target = $foreign['foreign_columns'][$index];
                    $label = $table.'.'.$column.' -> '.$foreign['foreign_table'].'.'.$target;
                    self::assertSame('int', $definition[$column]['type_name'], $label);
                    self::assertSame('int', $columns[$foreign['foreign_table']][$target]['type_name'], $label);
                    self::assertStringContainsString('unsigned', $definition[$column]['type'], $label);
                    self::assertStringContainsString('unsigned', $columns[$foreign['foreign_table']][$target]['type'], $label);
                }
            }
        }
        self::assertGreaterThanOrEqual(363, $count);
        foreach (['manifest_id', 'parcel_id', 'created_by', 'hq_id'] as $column) {
            self::assertSame('int', $columns['manifest_parcels'][$column]['type_name']);
        }
    }

    public function test_all_model_relationship_keys_match_the_installed_schema(): void
    {
        $schema = [];
        foreach (Schema::getTables() as $table) {
            $schema[$table['name']] = array_column(Schema::getColumns($table['name']), 'type_name', 'name');
        }
        $count = 0;
        foreach (glob(base_path('Modules/*/src/Infrastructure/Persistence/Models/*.php')) as $file) {
            preg_match('/namespace\s+([^;]+);/', file_get_contents($file), $namespace);
            $class = $namespace[1].'\\'.basename($file, '.php');
            $reflection = new ReflectionClass($class);
            $model = $reflection->newInstance();
            foreach ($reflection->getMethods() as $method) {
                $type = $method->getReturnType();
                if ($method->getDeclaringClass()->getName() !== $class || $method->getNumberOfRequiredParameters() !== 0
                    || ! $type instanceof ReflectionNamedType || ! is_a($type->getName(), Relation::class, true)) {
                    continue;
                }
                $relation = $method->invoke($model);
                $keys = match (true) {
                    $relation instanceof BelongsTo => [[$model->getTable(), $relation->getForeignKeyName()], [$relation->getRelated()->getTable(), $relation->getOwnerKeyName()]],
                    $relation instanceof BelongsToMany => [[$model->getTable(), $relation->getParentKeyName()], [$relation->getRelated()->getTable(), $relation->getRelatedKeyName()], [$relation->getTable(), $relation->getForeignPivotKeyName()], [$relation->getTable(), $relation->getRelatedPivotKeyName()]],
                    $relation instanceof HasManyThrough => [[$model->getTable(), $relation->getLocalKeyName()], [$relation->getParent()->getTable(), $relation->getFirstKeyName()], [$relation->getParent()->getTable(), $relation->getSecondLocalKeyName()], [$relation->getRelated()->getTable(), $relation->getForeignKeyName()]],
                    $relation instanceof HasOneOrMany => [[$model->getTable(), $relation->getLocalKeyName()], [$relation->getRelated()->getTable(), $relation->getForeignKeyName()]],
                    default => [],
                };
                $count++;
                foreach ($keys as [$table, $column]) {
                    self::assertSame('int', $schema[$table][$column], $class.'::'.$method->getName().' '.$table.'.'.$column);
                }
            }
        }
        self::assertGreaterThanOrEqual(140, $count);
    }

    public function test_numeric_ids_round_trip_without_translation(): void
    {
        $province = ProvinceRecord::query()->forceCreate(['legacy_province_code' => 'N1', 'name_fa' => 'Province',
            'normalized_name' => 'province', 'latitude' => 35, 'longitude' => 51, 'is_active' => true]);
        $city = CityRecord::query()->forceCreate(['province_id' => $province->id, 'legacy_city_code' => 'N101',
            'name_fa' => 'City', 'normalized_name' => 'city', 'is_active' => true]);
        self::assertSame($province->id, DB::table('cities')->where('id', $city->id)->value('province_id'));
        self::assertSame($city->id, $city->getRouteKey());
        self::assertSame($city->id, $city->toArray()['id']);
        self::assertSame($province->id, $city->load('province')->province->id);
        self::assertSame((string) $city->id, CityRecord::query()->where('province_id', (string) $province->id)->sole()->city_id);
    }

    public function test_polymorphic_scopes_distinguish_equal_ids_in_different_tables(): void
    {
        $hq = DB::table('hq_tenants')->insertGetId(['hq_code' => 'SCOPES', 'hq_title' => 'Scopes', 'status' => 'ACTIVE']);
        $area = DB::table('areas')->insertGetId(['hq_id' => $hq, 'area_code' => 'SCOPES', 'area_title' => 'Area', 'status' => 'ACTIVE']);
        DB::table('nodes')->insert(['id' => $area, 'hq_id' => $hq, 'area_id' => $area,
            'node_code' => 'SCOPES', 'node_title' => 'Node', 'node_type' => 'BRANCH', 'status' => 'ACTIVE']);
        $user = DB::table('users')->insertGetId(['hq_id' => $hq, 'first_name' => 'Scope', 'last_name' => 'Test',
            'display_name' => 'Scope Test', 'status' => 'ACTIVE', 'username' => 'scope-test', 'normalized_username' => 'scope-test']);
        $role = RoleRecord::query()->forceCreate(['owner_key' => 'GLOBAL', 'role_code' => 'scope-test',
            'role_title' => 'Scope test', 'role_kind' => 'SYSTEM', 'status' => 'ACTIVE']);
        foreach (['AREA', 'NODE'] as $type) {
            AssignmentRecord::query()->forceCreate(['hq_id' => $hq, 'user_id' => $user, 'role_id' => $role->id,
                'scope_id' => $area, 'scope_type' => $type]);
        }
        $assignments = AssignmentRecord::query()->where('hq_id', $hq)->with(['scopeArea', 'scopeNode'])->get()->keyBy('scope_type');
        self::assertSame($area, $assignments['AREA']->scopeArea->id);
        self::assertSame($area, $assignments['NODE']->scopeNode->id);
        self::assertNull($assignments['AREA']->scopeNode);
        self::assertNull($assignments['NODE']->scopeArea);
        self::assertSame(['AREA'], AssignmentRecord::query()->where('hq_id', $hq)->whereHas('scopeArea')->pluck('scope_type')->all());
        self::assertSame(['NODE'], AssignmentRecord::query()->where('hq_id', $hq)->whereHas('scopeNode')->pluck('scope_type')->all());
    }

    public function test_database_rejects_an_unknown_numeric_parent(): void
    {
        $this->expectException(QueryException::class);
        CityRecord::query()->forceCreate(['province_id' => 4294967295, 'legacy_city_code' => 'MISSING',
            'name_fa' => 'City', 'normalized_name' => 'city', 'is_active' => true]);
    }

    public function test_rollback_removes_inserted_records(): void
    {
        $id = null;
        try {
            DB::transaction(function () use (&$id): void {
                $id = DB::table('hq_tenants')->insertGetId(['hq_code' => 'ROLLBACK', 'hq_title' => 'Rollback', 'status' => 'ACTIVE']);
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException $exception) {
            self::assertSame('rollback', $exception->getMessage());
        }
        self::assertIsInt($id);
        self::assertFalse(DB::table('hq_tenants')->where('id', $id)->exists());
    }
}
