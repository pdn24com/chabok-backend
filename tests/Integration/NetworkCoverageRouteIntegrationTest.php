<?php

declare(strict_types=1);

namespace Tests\Integration;

use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Domain\Enums\CoverageTarget;
use Modules\Operations\Domain\Enums\RoutePurpose;
use PHPUnit\Framework\Assert;
use Tests\Support\AccessContexts;
use Tests\Support\CoverageFixtures as CoveragePolicyService;
use Tests\Support\RecordFixtureQuery;
use Tests\Support\RouteDefinitionFixtures;

final class NetworkCoverageRouteIntegrationTest extends MySqlRedisTestCase
{
    private array $permissions = [];

    private int $auditWrites = 0;

    private int $outboxWrites = 0;

    /** @return list<string> */
    public function permissions(): array
    {
        return $this->permissions;
    }

    public function auditWritten(): void
    {
        $this->auditWrites++;
    }

    public function outboxWritten(): void
    {
        $this->outboxWrites++;
    }

    public function test_coverage_lifecycle_priority_specificity_boundary_tie_and_security_fail_closed(): void
    {
        [$tenant, $actor, $nodes, $province, $city] = $this->network('COVERAGE');
        $service = $this->app->make(CoveragePolicyService::class);
        $policy = $service->create($actor, ['policy_code' => 'MAH-COVERAGE', 'policy_title' => 'پوشش ماهشهر'], $this->cid('policy'));
        $version = $service->createVersion($actor, $policy['coverage_policy_id'], ['rules' => [
            ['target' => 'DESTINATION_GATEWAY', 'target_node_id' => $nodes[0], 'priority' => 10, 'criterion' => ['criterion_type' => 'PROVINCE', 'province_id' => $province]],
            ['target' => 'DESTINATION_GATEWAY', 'target_node_id' => $nodes[1], 'priority' => 10, 'criterion' => ['criterion_type' => 'CITY', 'city_id' => $city]],
            ['target' => 'DESTINATION_GATEWAY', 'target_node_id' => $nodes[2], 'priority' => 20, 'criterion' => ['criterion_type' => 'POLYGON', 'geometry' => ['type' => 'Polygon', 'coordinates' => [[[48.0, 30.0], [49.0, 30.0], [49.0, 31.0], [48.0, 31.0], [48.0, 30.0]]]]]],
        ]], $this->cid('version'));
        $version = $service->transition($actor, $policy['coverage_policy_id'], $version['coverage_policy_version_id'], 'validate', 1, null, $this->cid('validate'));
        $version = $service->transition($actor, $policy['coverage_policy_id'], $version['coverage_policy_version_id'], 'approve', 2, null, $this->cid('approve'));
        $version = $service->transition($actor, $policy['coverage_policy_id'], $version['coverage_policy_version_id'], 'publish', 3, null, $this->cid('publish'));
        self::assertSame('PUBLISHED', $version['status']);
        $resolved = $service->resolve($tenant['hq_id'], CoverageTarget::DestinationGateway, ['province_id' => $province, 'city_id' => $city, 'latitude' => 30.0, 'longitude' => 48.5]);
        self::assertSame($nodes[2], $resolved->rule->target_node_id, 'Explicit priority wins and polygon boundary is included.');

        $tie = $service->create($actor, ['policy_code' => 'MAH-TIE', 'policy_title' => 'همپوشانی'], $this->cid('tie-policy'));
        $tieVersion = $service->createVersion($actor, $tie['coverage_policy_id'], ['rules' => [['target' => 'DESTINATION_GATEWAY', 'target_node_id' => $nodes[1], 'priority' => 20, 'criterion' => ['criterion_type' => 'POINT_RADIUS', 'center' => ['latitude' => 30.0, 'longitude' => 48.5], 'radius_meters' => 1000]]]], $this->cid('tie-version'));
        foreach (['validate', 'approve', 'publish'] as $index => $action) {
            $tieVersion = $service->transition($actor, $tie['coverage_policy_id'], $tieVersion['coverage_policy_version_id'], $action, $index + 1, null, $this->cid('tie-'.$action));
        }
        $this->expectApi(ApiErrorCode::CoverageAmbiguous, fn () => $service->resolve($tenant['hq_id'], CoverageTarget::DestinationGateway, ['latitude' => 30.0, 'longitude' => 48.5]));
        $this->expectApi(ApiErrorCode::VersionConflict, fn () => $service->transition($actor, $policy['coverage_policy_id'], $version['coverage_policy_version_id'], 'supersede', 3, null, $this->cid('stale')));

        [$foreign] = $this->network('FOREIGN');
        $draft = $service->create($actor, ['policy_code' => 'CROSS-HQ', 'policy_title' => 'غیرمجاز'], $this->cid('cross-policy'));
        $this->expectApi(ApiErrorCode::ValidationError, fn () => $service->createVersion($actor, $draft['coverage_policy_id'], ['rules' => [['target' => 'LAST_MILE_NODE', 'target_node_id' => RecordFixtureQuery::table('nodes')->where('hq_id', $foreign['hq_id'])->value('node_id'), 'priority' => 1, 'criterion' => ['criterion_type' => 'CITY', 'city_id' => $city]]]], $this->cid('cross-version')));
        $this->permissions = [];
        $this->expectApi(ApiErrorCode::PermissionDenied, fn () => $service->list($actor, []));
        self::assertGreaterThanOrEqual(9, $this->auditWrites);
        self::assertSame($this->auditWrites, $this->outboxWrites);
    }

    public function test_route_lifecycle_rejects_disconnected_cycles_and_ambiguous_published_templates(): void
    {
        [$tenant, $actor, $nodes] = $this->network('ROUTE');
        $service = $this->app->make(RouteDefinitionFixtures::class);
        $definition = $service->create($actor, ['route_code' => 'TBZ-MAH', 'route_title' => 'تبریز به ماهشهر'], $this->cid('route'));
        $payload = ['purpose' => 'TRUNK', 'origin_node_id' => $nodes[0], 'destination_node_id' => $nodes[2], 'priority' => 100, 'legs' => [['leg_order' => 1, 'origin_node_id' => $nodes[0], 'destination_node_id' => $nodes[1]], ['leg_order' => 2, 'origin_node_id' => $nodes[1], 'destination_node_id' => $nodes[2]]]];
        $version = $service->createVersion($actor, $definition['route_definition_id'], $payload, $this->cid('route-version'));
        foreach (['validate', 'approve', 'publish'] as $index => $action) {
            $version = $service->transition($actor, $definition['route_definition_id'], $version['route_definition_version_id'], $action, $index + 1, null, $this->cid('route-'.$action));
        }
        self::assertSame($version['route_definition_version_id'], $service->resolve($tenant['hq_id'], RoutePurpose::Trunk, $nodes[0], $nodes[2])['route_definition_version_id']);

        $broken = $service->create($actor, ['route_code' => 'BROKEN', 'route_title' => 'شکسته'], $this->cid('broken'));
        $this->expectApi(ApiErrorCode::ValidationError, fn () => $service->createVersion($actor, $broken['route_definition_id'], [...$payload, 'legs' => [['leg_order' => 1, 'origin_node_id' => $nodes[0], 'destination_node_id' => $nodes[1]], ['leg_order' => 2, 'origin_node_id' => $nodes[0], 'destination_node_id' => $nodes[2]]]], $this->cid('broken-version')));
        $this->expectApi(ApiErrorCode::ValidationError, fn () => $service->createVersion($actor, $broken['route_definition_id'], [...$payload, 'destination_node_id' => $nodes[0], 'legs' => [['leg_order' => 1, 'origin_node_id' => $nodes[0], 'destination_node_id' => $nodes[1]], ['leg_order' => 2, 'origin_node_id' => $nodes[1], 'destination_node_id' => $nodes[0]]]], $this->cid('cycle-version')));

        $second = $service->create($actor, ['route_code' => 'TBZ-MAH-ALT', 'route_title' => 'مسیر هم‌اولویت'], $this->cid('route-alt'));
        $secondVersion = $service->createVersion($actor, $second['route_definition_id'], $payload, $this->cid('route-alt-version'));
        foreach (['validate', 'approve', 'publish'] as $index => $action) {
            $secondVersion = $service->transition($actor, $second['route_definition_id'], $secondVersion['route_definition_version_id'], $action, $index + 1, null, $this->cid('route-alt-'.$action));
        }
        $this->expectApi(ApiErrorCode::RouteAmbiguous, fn () => $service->resolve($tenant['hq_id'], RoutePurpose::Trunk, $nodes[0], $nodes[2]));
        self::assertCount(1, $service->history($actor, $definition['route_definition_id'], 1, 20)->items());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->permissions = ['network.coverage.view', 'network.coverage.manage_draft', 'network.coverage.validate', 'network.coverage.approve', 'network.coverage.publish', 'network.route.view', 'network.route.manage_draft', 'network.route.validate', 'network.route.approve', 'network.route.publish'];
        $this->app->instance(AccessContextResolverInterface::class, new class($this) implements AccessContextResolverInterface
        {
            public function __construct(private NetworkCoverageRouteIntegrationTest $test) {}

            public function resolve(AuthenticatedPrincipal $principal): AccessContextDto
            {
                return AccessContexts::make(['module_entitlements' => [['module_code' => 'LiveOperations', 'status' => 'ENABLED']], 'permissions' => $this->test->permissions(), 'accessible_node_ids' => []]);
            }
        });
        $this->app->instance(AuditWriterInterface::class, new class($this) implements AuditWriterInterface
        {
            public function __construct(private NetworkCoverageRouteIntegrationTest $test) {}

            public function write(?string $hqId, ?string $initiatorId, string $action, string $targetType, ?string $targetId, string $correlationId, ?array $before = null, ?array $after = null, ?string $safeNote = null, ?string $ipAddress = null, ?string $userAgent = null, ?string $sourceClient = null): void
            {
                $this->test->auditWritten();
            }
        });
        $this->app->instance(OutboxWriterInterface::class, new class($this) implements OutboxWriterInterface
        {
            public function __construct(private NetworkCoverageRouteIntegrationTest $test) {}

            public function write(?string $hqId, string $aggregateType, string $aggregateId, string $eventType, string $correlationId, array $payload, int $eventVersion = 1, ?string $causationId = null): void
            {
                $keys = array_keys($payload);
                sort($keys);
                Assert::assertSame('network.configuration.changed', $eventType);
                Assert::assertSame(['action', 'status', 'target_id', 'target_type'], $keys);
                $this->test->outboxWritten();
            }
        });
    }

    /** @return array{0:array<string,mixed>,1:AuthenticatedPrincipal,2:list<string>,3:string,4:string} */
    private function network(string $code): array
    {
        $tenant = $this->tenant('HQ-'.$code);
        $user = $this->user($tenant['hq_id'], strtolower($code).'-admin');
        $actor = new AuthenticatedPrincipal($user['user_id'], $this->cid('session-'.$code), $tenant['hq_id'], false);
        $area = (string) random_int(1, 2000000000);
        RecordFixtureQuery::table('areas')->insert(['area_id' => $area, 'hq_id' => $tenant['hq_id'], 'area_title' => $code, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);
        $nodes = [];
        foreach (['ORIGIN', 'HUB', 'DESTINATION'] as $index => $suffix) {
            $nodes[] = $id = (string) random_int(1, 2000000000);
            RecordFixtureQuery::table('nodes')->insert(['node_id' => $id, 'hq_id' => $tenant['hq_id'], 'area_id' => $area, 'node_code' => "{$code}-{$suffix}", 'node_title' => $suffix, 'node_type' => $index === 1 ? 'HUB' : 'GATEWAY', 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);
        }
        $province = (string) random_int(1, 2000000000);
        $city = (string) random_int(1, 2000000000);
        RecordFixtureQuery::table('provinces')->insertOrIgnore(['province_id' => $province, 'legacy_province_code' => substr(hash('sha256', $code), 0, 4), 'name_fa' => 'خوزستان '.$code, 'normalized_name' => strtolower($code), 'latitude' => 30.5, 'longitude' => 48.5, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        RecordFixtureQuery::table('cities')->insertOrIgnore(['city_id' => $city, 'province_id' => $province, 'legacy_city_code' => substr(hash('sha256', 'city-'.$code), 0, 12), 'name_fa' => 'ماهشهر '.$code, 'normalized_name' => strtolower($code), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return [$tenant, $actor, $nodes, $province, $city];
    }

    private function cid(string $key): string
    {
        $hex = substr(hash('sha256', $key), 0, 32);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-4'.substr($hex, 13, 3).'-8'.substr($hex, 17, 3).'-'.substr($hex, 20, 12);
    }

    private function expectApi(ApiErrorCode $code, callable $callback): void
    {
        try {
            $callback();
            self::fail("Expected {$code->value}.");
        } catch (ApiException $exception) {
            self::assertSame($code, $exception->errorCode);
        }
    }
}
