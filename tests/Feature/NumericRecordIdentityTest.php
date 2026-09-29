<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Modules\Authorization\Infrastructure\Database\Seeders\AuthorizationCatalogSeeder;
use Modules\Authorization\Infrastructure\Persistence\Models\RoleRecord;
use Modules\Authorization\Infrastructure\Repositories\EloquentRoleRepository;
use Modules\Foundation\Infrastructure\Persistence\RecordSchema;
use Modules\Foundation\Presentation\Http\Middleware\NodeContext;
use Modules\Geography\Domain\Support\PersianSearchNormalizer;
use Modules\Geography\Infrastructure\Database\Seeders\IranGeographySeeder;
use Modules\Geography\Infrastructure\Persistence\Models\CityRecord;
use Modules\Geography\Infrastructure\Persistence\Models\ProvinceRecord;
use Modules\Organization\Application\Mappers\NetworkInput;
use Modules\Organization\Infrastructure\Persistence\Models\AreaRecord;
use Modules\Organization\Presentation\Http\Requests\CreateNodeRequest;
use Tests\Support\InstallsNumericSchema;
use Tests\TestCase;

final class NumericRecordIdentityTest extends TestCase
{
    use InstallsNumericSchema;

    public function test_every_entity_has_only_one_stored_identity(): void
    {
        foreach (RecordSchema::IDENTITY_NAMES as $table => $oldName) {
            $columns = array_column(Schema::getColumns($table), null, 'name');
            self::assertArrayHasKey('id', $columns, $table);
            self::assertTrue($columns['id']['auto_increment'], $table);
            self::assertArrayNotHasKey($oldName, $columns, $table);
            self::assertSame(['id'], array_values(array_filter(Schema::getIndexes($table), fn ($index) => $index['primary']))[0]['columns'], $table);
        }
        self::assertGreaterThanOrEqual(100, count(RecordSchema::IDENTITY_NAMES));
    }

    public function test_database_allocates_ids_and_domain_names_resolve_to_that_same_number(): void
    {
        $first = AreaRecord::query()->forceCreate(['hq_id' => 1, 'area_code' => 'A', 'area_title' => 'First', 'status' => 'ACTIVE']);
        $second = AreaRecord::query()->forceCreate(['hq_id' => 1, 'area_code' => 'B', 'area_title' => 'Second', 'status' => 'ACTIVE']);
        self::assertSame(1, $first->id);
        self::assertSame(2, $second->id);
        self::assertSame('1', $first->area_id);
        self::assertSame(1, $first->getRouteKey());
        self::assertSame(1, $first->toArray()['id']);
        self::assertArrayNotHasKey('area_id', $first->getAttributes());
        self::assertSame($first->id, AreaRecord::query()->where('area_id', '1')->sole()->id);
        self::assertSame(['1', '2'], AreaRecord::query()->orderBy('area_id')->pluck('area_id')->all());
        self::assertSame($first->id, AreaRecord::query()->where('area_id', '1')->firstOrFail(['area_id', 'area_title'])->getKey());
        self::assertSame('area_id', AreaRecord::query()->selectRaw("id, 'area_id' AS identifier_label")->whereKey($first->id)->sole()->identifier_label);
    }

    public function test_customer_addresses_use_generated_ids_and_keep_tenant_foreign_keys(): void
    {
        Schema::enableForeignKeyConstraints();
        $hq = DB::table('hq_tenants')->insertGetId(['hq_code' => 'ADDRESS', 'hq_title' => 'Address']);
        $otherHq = DB::table('hq_tenants')->insertGetId(['hq_code' => 'OTHER', 'hq_title' => 'Other']);
        $user = DB::table('users')->insertGetId(['hq_id' => $hq, 'first_name' => 'Test', 'last_name' => 'User', 'display_name' => 'Test User', 'status' => 'ACTIVE']);
        $customer = DB::table('crm_customers')->insertGetId(['hq_id' => $hq, 'kind' => 'PERSON', 'phase' => 'LEAD', 'assignee_id' => $user, 'created_by' => $user]);
        $address = ['hq_id' => $hq, 'customer_id' => $customer, 'country_code' => 'IR', 'purpose' => 'DELIVERY', 'created_by' => $user];
        $first = DB::table('crm_customer_address')->insertGetId($address);
        $second = DB::table('crm_customer_address')->insertGetId($address);

        self::assertSame(1, $first);
        self::assertSame(2, $second);
        self::assertSame($customer, DB::table('crm_customer_address')->where('id', $first)->value('customer_id'));

        $this->expectException(QueryException::class);
        DB::table('crm_customer_address')->insert([...$address, 'hq_id' => $otherHq]);
    }

    public function test_bulk_writes_and_eager_relations_need_no_identity_lookup_queries(): void
    {
        $province = ProvinceRecord::query()->forceCreate(['legacy_province_code' => '01', 'name_fa' => 'Province', 'normalized_name' => '125339763', 'latitude' => 31, 'longitude' => 51, 'is_active' => true]);
        $rows = [];
        foreach (range(1, 40) as $number) {
            $rows[] = ['province_id' => $province->id, 'legacy_city_code' => (string) $number, 'name_fa' => 'City '.$number, 'normalized_name' => 'city '.$number, 'is_active' => true];
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            CityRecord::query()->insert($rows);
            self::assertCount(1, DB::getQueryLog());
            DB::flushQueryLog();
            $cities = CityRecord::query()->with('province')->where('province_id', (string) $province->id)->get();
            self::assertCount(40, $cities);
            foreach ($cities as $city) {
                self::assertSame($province->id, $city->province->id);
                self::assertSame((string) $province->id, $city->toArray()['province_id']);
            }
            self::assertCount(2, DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_creation_repository_returns_generated_id_and_clones_receive_a_new_id(): void
    {
        $repository = new EloquentRoleRepository;
        $id = $repository->insert(['hq_id' => 1, 'owner_key' => '1', 'role_code' => '175716279', 'role_title' => 'First', 'role_kind' => 'CUSTOM', 'is_cloneable' => true, 'status' => 'ACTIVE']);
        $role = $repository->find($id);
        self::assertSame((string) $role->getKey(), $id);
        $copy = $role->replicate();
        $copy->forceFill(['role_code' => 'second'])->save();
        self::assertNotSame($role->id, $copy->id);
        self::assertSame(2, RoleRecord::query()->count());
    }

    public function test_repeated_seed_keeps_database_ids_and_geography_references(): void
    {
        $authorization = new AuthorizationCatalogSeeder;
        $authorization->run();
        $roles = RoleRecord::query()->pluck('id', 'role_code')->all();
        $authorization->run();
        self::assertSame($roles, RoleRecord::query()->pluck('id', 'role_code')->all());
        $geography = new IranGeographySeeder(new PersianSearchNormalizer);
        $geography->run();
        $city = CityRecord::query()->where('legacy_city_code', '10866')->sole();
        $geography->run();
        $sameCity = CityRecord::query()->with('province')->where('legacy_city_code', '10866')->sole();
        self::assertSame($city->id, $sameCity->id);
        self::assertSame((int) $city->province_id, $sameCity->province->id);
        self::assertSame(2858, CityRecord::query()->count());
    }

    public function test_numeric_json_ids_validate_and_reach_typed_application_inputs(): void
    {
        $request = CreateNodeRequest::create('/api/v1/admin/network/nodes', 'POST', [
            'node_code' => 'N1', 'node_title' => 'Node', 'area_id' => 1, 'node_type' => 'BRANCH',
            'capabilities' => ['PICKUP'], 'address' => ['country_code' => 'IR', 'province_id' => 2, 'city_id' => 3],
        ]);
        $validator = Validator::make($request->all(), $request->rules());
        self::assertFalse($validator->fails(), json_encode($validator->errors()->all()));
        $request->setValidator($validator);
        $input = NetworkInput::node($request->validated());
        self::assertSame('1', $input->details->areaId);
        self::assertSame('3', $input->details->address->cityId);
    }

    public function test_resource_routes_and_node_context_accept_numeric_ids(): void
    {
        $request = Request::create('/api/v1/reference/cities/12');
        $route = app('router')->getRoutes()->match($request);
        self::assertSame('12', $route->parameter('cityId'));
        $request->headers->set('X-Node-Id', '12');
        $response = (new NodeContext)->handle($request, fn ($request) => response()->json(['node_id' => $request->attributes->get('node_id')]));
        self::assertSame('12', $response->getData(true)['node_id']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->installNumericSchema();
    }
}
