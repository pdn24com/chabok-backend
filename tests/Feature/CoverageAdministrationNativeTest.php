<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Mockery;
use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Application\Dto\ModuleEntitlementDto;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\EntitlementStatus;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Dto\CoverageRuleDto;
use Modules\Operations\Application\Services\CoverageRuleGuard;
use Modules\Operations\Application\Services\CoverageRuleWriter;
use Modules\Operations\Application\UseCases\ListCoverageVersions\ListCoverageVersionsCommand;
use Modules\Operations\Application\UseCases\ListCoverageVersions\ListCoverageVersionsHandler;
use Modules\Operations\Infrastructure\Persistence\Models\CoverageRuleRecord;
use Modules\Operations\Presentation\Http\Resources\CoverageVersionResource;
use Tests\Support\RecordFixtureQuery;
use Tests\TestCase;

final class CoverageAdministrationNativeTest extends TestCase
{
    public function test_history_loads_one_or_forty_versions_with_rules_in_four_queries(): void
    {
        $counts = [];
        for ($index = 1; $index <= 40; $index++) {
            RecordFixtureQuery::table('coverage_policy_versions')->insert(['coverage_policy_version_id' => \Tests\Support\FixtureId::from('v'.$index), 'coverage_policy_id' => '136528174', 'hq_id' => '245213294', 'version_number' => $index, 'status' => 'DRAFT', 'created_by' => '5212567']);
            RecordFixtureQuery::table('coverage_rules')->insert(['coverage_rule_id' => \Tests\Support\FixtureId::from('r'.$index), 'coverage_policy_version_id' => \Tests\Support\FixtureId::from('v'.$index), 'hq_id' => '245213294', 'target' => 'DESTINATION_GATEWAY', 'target_node_id' => '88468052', 'priority' => 10, 'criterion_type' => 'CITY', 'city_id' => '18506435']);
            if (! in_array($index, [1, 40], true)) {
                continue;
            }
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            try {
                $page = $this->app->make(ListCoverageVersionsHandler::class)->handle(new ListCoverageVersionsCommand(new AuthenticatedPrincipal('5212567', 'session', '245213294', false), '136528174', 1, 50));
                $data = CoverageVersionResource::collection($page->getCollection())->resolve();
                $counts[] = count(DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
            self::assertCount($index, $data);
            self::assertSame(\Tests\Support\FixtureId::from('r'.$index), $data[0]['rules'][0]['coverage_rule_id']);
            self::assertSame(['criterion_type' => 'CITY', 'city_id' => '18506435'], $data[0]['rules'][0]['criterion']);
        }
        self::assertSame([4, 4], $counts);
    }

    public function test_reference_validation_is_batched_and_keeps_duplicate_and_tenant_rejections(): void
    {
        $guard = $this->app->make(CoverageRuleGuard::class);
        $rules = [];
        for ($index = 1; $index <= 40; $index++) {
            $rules[] = $this->rule($index, ['criterion_type' => 'CITY', 'city_id' => '18506435']);
            if (! in_array($index, [1, 40], true)) {
                continue;
            }
            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();
            try {
                $guard->validateRuleInputs('245213294', $rules);
                self::assertCount(2, DB::connection()->getQueryLog());
            } finally {
                DB::connection()->disableQueryLog();
            }
        }
        try {
            $guard->validateRuleInputs('245213294', [$rules[0], $rules[0]]);
            self::fail('Indistinguishable rules must be rejected.');
        } catch (ApiException $exception) {
            self::assertSame(ApiErrorCode::ValidationError, $exception->errorCode);
            self::assertSame('operations.duplicate_indistinguishable_coverage_rules_are_not_allowed', $exception->getMessage());
        }
        RecordFixtureQuery::table('nodes')->where('node_id', '88468052')->update(['hq_id' => '106329882']);
        try {
            $guard->validateRuleInputs('245213294', $rules);
            self::fail('Foreign target must be rejected.');
        } catch (ApiException $exception) {
            self::assertSame(ApiErrorCode::ValidationError, $exception->errorCode);
            self::assertSame('operations.every_target_node_must_be_active_belong', $exception->getMessage());
        }
    }

    public function test_all_criterion_types_round_trip_through_model_casts_and_bulk_writer(): void
    {
        $geometry = ['type' => 'Polygon', 'coordinates' => [[[50, 30], [52, 30], [52, 32], [50, 32], [50, 30]]]];
        $criteria = [
            ['criterion_type' => 'PROVINCE', 'province_id' => '125339763'],
            ['criterion_type' => 'CITY', 'city_id' => '18506435'],
            ['criterion_type' => 'POSTAL_RANGE', 'postal_code_from' => '0010000000', 'postal_code_to' => '0099999999'],
            ['criterion_type' => 'POLYGON', 'geometry' => $geometry],
            ['criterion_type' => 'POINT_RADIUS', 'center' => ['latitude' => 35.0, 'longitude' => 51.0], 'radius_meters' => 500],
        ];
        $rules = [];
        foreach ($criteria as $index => $criterion) {
            $rules[] = $this->rule($index, $criterion);
        }
        $this->app->make(CoverageRuleGuard::class)->validateRuleInputs('245213294', $rules);
        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();
        try {
            $this->app->make(CoverageRuleWriter::class)->replaceRules('245213294', '97144633', $rules);
            self::assertCount(2, DB::connection()->getQueryLog());
        } finally {
            DB::connection()->disableQueryLog();
        }
        $stored = CoverageRuleRecord::query()->orderBy('priority')->get();
        self::assertCount(5, $stored);
        foreach ($stored as $index => $record) {
            $restored = CoverageRuleDto::fromRecord($record);
            self::assertEquals($rules[$index], $restored);
        }
        self::assertSame('0010000000', $stored[2]->postal_code_from);
        self::assertIsArray($stored[3]->geometry_geojson);
        self::assertNull($stored[3]->province_id);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.coverage_admin_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::setDefaultConnection('coverage_admin_test');
        foreach (['coverage_policies', 'coverage_policy_versions', 'coverage_rules'] as $table) {
            (require glob(base_path('Modules/Operations/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        (require glob(base_path('Modules/Organization/database/migrations/*_create_nodes.php'))[0])->up();
        foreach (['provinces', 'cities'] as $table) {
            (require glob(base_path('Modules/Geography/database/migrations/*_create_'.$table.'.php'))[0])->up();
        }
        RecordFixtureQuery::table('nodes')->insert(['node_id' => '88468052', 'hq_id' => '245213294', 'area_id' => '78192358', 'node_code' => '88468052', 'node_title' => 'Node', 'node_type' => 'HUB']);
        RecordFixtureQuery::table('provinces')->insert(['province_id' => '125339763', 'legacy_province_code' => '01', 'name_fa' => 'Province', 'normalized_name' => '125339763', 'latitude' => 35, 'longitude' => 51]);
        RecordFixtureQuery::table('cities')->insert(['city_id' => '18506435', 'province_id' => '125339763', 'legacy_city_code' => '01', 'name_fa' => 'City', 'normalized_name' => '18506435']);
        RecordFixtureQuery::table('coverage_policies')->insert(['coverage_policy_id' => '136528174', 'hq_id' => '245213294', 'policy_code' => '136528174', 'policy_title' => 'Policy']);
        $resolver = Mockery::mock(AccessContextResolverInterface::class);
        $resolver->shouldReceive('resolve')->andReturn(new AccessContextDto(
            hqId: '245213294', permissions: ['network.coverage.view'], moduleEntitlements: [new ModuleEntitlementDto('LiveOperations', EntitlementStatus::ENABLED)],
        ));
        $this->app->instance(AccessContextResolverInterface::class, $resolver);
    }

    private function rule(int $priority, array $criterion): CoverageRuleDto
    {
        return CoverageRuleDto::fromValidated(['target' => 'DESTINATION_GATEWAY', 'target_node_id' => '88468052', 'priority' => $priority, 'criterion' => $criterion]);
    }
}
