<?php

declare(strict_types=1);

namespace Modules\Organization\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Tests\Support\AccessContexts;
use Tests\Support\NetworkAdministrationFixtures as NetworkAdministrationService;
use Tests\Support\RecordFixtureQuery;
use Tests\TestCase;

final class NetworkAdministrationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_hq_admin_creates_and_updates_area_and_node_with_audit_outbox_and_versioning(): void
    {
        $hqId = (string) random_int(1, 2000000000);
        $userId = (string) random_int(1, 2000000000);
        RecordFixtureQuery::table('hq_tenants')->insert(['hq_id' => $hqId, 'hq_code' => 'HQ-NETWORK', 'hq_title' => 'Network HQ', 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);
        RecordFixtureQuery::table('users')->insert(['user_id' => $userId, 'hq_id' => $hqId, 'username' => 'network.admin', 'normalized_username' => 'network.admin', 'first_name' => 'Network', 'last_name' => 'Admin', 'display_name' => 'Network Admin', 'status' => 'ACTIVE', 'must_change_password' => false, 'created_at' => now(), 'updated_at' => now()]);
        $provinceId = (string) random_int(1, 2000000000);
        $cityId = (string) random_int(1, 2000000000);
        RecordFixtureQuery::table('provinces')->insert(['province_id' => $provinceId, 'legacy_province_code' => '1', 'name_fa' => 'آذربایجان شرقی', 'normalized_name' => 'azerbaijan-sharghi', 'latitude' => 38.0962, 'longitude' => 46.2738, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        RecordFixtureQuery::table('cities')->insert(['city_id' => $cityId, 'province_id' => $provinceId, 'legacy_city_code' => '10712', 'name_fa' => 'تبریز', 'normalized_name' => 'tabriz', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $resolver = new class implements AccessContextResolverInterface
        {
            /** @var list<string> */
            public array $permissions = ['network.area.view', 'network.area.manage', 'network.node.view', 'network.node.manage'];

            public function resolve(AuthenticatedPrincipal $principal): AccessContextDto
            {
                return AccessContexts::make(['hq_id' => $principal->hqId, 'permission_scopes' => array_fill_keys($this->permissions, [['scope_type' => 'TENANT', 'scope_id' => null, 'includes_descendants' => false]]), 'permissions' => $this->permissions, 'module_entitlements' => [['module_code' => 'LiveOperations', 'status' => 'ENABLED']]]);
            }
        };
        app()->instance(AccessContextResolverInterface::class, $resolver);
        app()->instance(OutboxWriterInterface::class, new class implements OutboxWriterInterface
        {
            public function write(?string $hqId, string $aggregateType, string $aggregateId, string $eventType, string $correlationId, array $payload, int $eventVersion = 1, ?string $causationId = null): void
            {
                RecordFixtureQuery::table('outbox_events')->insert(['event_id' => (string) random_int(1, 2000000000), 'hq_id' => $hqId, 'aggregate_type' => $aggregateType, 'aggregate_id' => $aggregateId, 'event_type' => $eventType, 'event_version' => $eventVersion, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'correlation_id' => $correlationId, 'causation_id' => $causationId, 'occurred_at' => now(), 'publication_state' => 'PENDING', 'attempts' => 0, 'created_at' => now()]);
            }
        });
        $service = app(NetworkAdministrationService::class);
        $actor = new AuthenticatedPrincipal($userId, (string) random_int(1, 2000000000), $hqId, false);

        $area = $service->createArea($actor, ['area_code' => 'NW', 'area_title' => 'شمال‌غرب', 'parent_area_id' => null], (string) random_int(1, 2000000000));
        $node = $service->createNode($actor, ['area_id' => $area['area_id'], 'node_code' => 'TBZ-HUB', 'node_title' => 'هاب تبریز', 'node_type' => 'HUB', 'capabilities' => ['CONSOLIDATION', 'LINEHAUL'], 'address' => ['country_code' => 'IR', 'province_id' => $provinceId, 'city_id' => $cityId, 'postal_code' => null, 'line' => 'تبریز', 'location' => ['latitude' => 38.0962, 'longitude' => 46.2738]]], (string) random_int(1, 2000000000));
        $updated = $service->updateNode($actor, $node['node_id'], ['node_title' => 'هاب اصلی تبریز', 'expected_version' => 1], (string) random_int(1, 2000000000));

        $this->assertSame(2, $updated['version']);
        $this->assertSame('هاب اصلی تبریز', $updated['node_title']);
        $this->assertDatabaseCount('audit_events', 3);
        $this->assertDatabaseHasPublic('outbox_events', ['event_type' => 'network.configuration.changed', 'aggregate_id' => $node['node_id']]);

        try {
            $service->updateNode($actor, $node['node_id'], ['node_title' => '168030777', 'expected_version' => 1], (string) random_int(1, 2000000000));
            $this->fail('A stale update must be rejected.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::VersionConflict, $exception->errorCode);
        }

        $child = $service->createArea($actor, ['area_code' => 'NW-CHILD', 'area_title' => 'زیرناحیه', 'parent_area_id' => $area['area_id']], (string) random_int(1, 2000000000));
        try {
            $service->updateArea($actor, $area['area_id'], ['parent_area_id' => $child['area_id'], 'expected_version' => 1], (string) random_int(1, 2000000000));
            $this->fail('An Area hierarchy cycle must be rejected.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::ValidationError, $exception->errorCode);
        }

        $service->updateArea($actor, $child['area_id'], ['parent_area_id' => null, 'expected_version' => 1], (string) random_int(1, 2000000000));
        $areaAudit = RecordFixtureQuery::table('audit_events')->where('action_key', 'network.area.updated')->where('target_id', $child['area_id'])->first();
        self::assertSame($area['area_id'], json_decode($areaAudit->before_snapshot, true)['parent_area_id']);
        self::assertNull(json_decode($areaAudit->after_snapshot, true)['parent_area_id']);
        $nodeAudit = RecordFixtureQuery::table('audit_events')->where('action_key', 'network.node.updated')->where('target_id', $node['node_id'])->first();
        self::assertSame('هاب تبریز', json_decode($nodeAudit->before_snapshot, true)['node_title']);
        self::assertSame('هاب اصلی تبریز', json_decode($nodeAudit->after_snapshot, true)['node_title']);
        self::assertArrayNotHasKey('id', json_decode($nodeAudit->after_snapshot, true));

        $foreignHqId = (string) random_int(1, 2000000000);
        $foreignUserId = (string) random_int(1, 2000000000);
        RecordFixtureQuery::table('hq_tenants')->insert(['hq_id' => $foreignHqId, 'hq_code' => 'HQ-FOREIGN', 'hq_title' => 'Foreign HQ', 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);
        RecordFixtureQuery::table('users')->insert(['user_id' => $foreignUserId, 'hq_id' => $foreignHqId, 'username' => 'foreign.admin', 'normalized_username' => 'foreign.admin', 'first_name' => 'Foreign', 'last_name' => 'Admin', 'display_name' => 'Foreign Admin', 'status' => 'ACTIVE', 'must_change_password' => false, 'created_at' => now(), 'updated_at' => now()]);
        $foreignActor = new AuthenticatedPrincipal($foreignUserId, (string) random_int(1, 2000000000), $foreignHqId, false);
        foreach ([[fn () => $service->area($foreignActor, $area['area_id']), ApiErrorCode::ResourceNotFound], [fn () => $service->node($foreignActor, $node['node_id']), ApiErrorCode::ScopeAccessDenied]] as [$foreignRead, $expectedCode]) {
            try {
                $foreignRead();
                $this->fail('A foreign HQ must not read Area or Node resources.');
            } catch (ApiException $exception) {
                $this->assertSame($expectedCode, $exception->errorCode);
            }
        }

        $resolver->permissions = [];
        try {
            $service->nodes($actor, []);
            $this->fail('Missing permission must fail closed.');
        } catch (ApiException $exception) {
            $this->assertSame(ApiErrorCode::PermissionDenied, $exception->errorCode);
        }
    }
}
