<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use RuntimeException;
use Tests\Support\AccessContexts;
use Tests\Support\FleetAdministrationFixtures as FleetAdministrationService;
use Tests\Support\RecordFixtureQuery;
use Tests\TestCase;

final class FleetAdministrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private FleetTestAuthorization $authorization;

    private FleetTestOutbox $outbox;

    private FleetAdministrationService $fleet;

    private array $first;

    private array $second;

    public function test_current_hq_can_create_list_and_update_drivers_and_vehicles(): void
    {
        $actor = $this->actor($this->first['hq_id'], $this->first['user_id']);
        $driver = $this->fleet->createDriver($actor, [
            'driver_code' => 'drv-100',
            'display_name' => 'راننده آزمون',
            'user_id' => $this->first['linked_user_id'],
            'home_node_id' => $this->first['node_id'],
            'mobile' => '09120000000',
            'capabilities' => ['DELIVERY', 'PICKUP'],
        ], (string) random_int(1, 2000000000));
        self::assertSame('DRV-100', $driver['driver_code']);
        self::assertSame(['PICKUP', 'DELIVERY'], $driver['capabilities']);
        self::assertSame('AVAILABLE', $driver['availability_status']);

        $vehicle = $this->fleet->createVehicle($actor, [
            'vehicle_code' => 'van-100',
            'plate_number' => 'ایران ۱۱-۱۲۳ ب ۴۵',
            'vehicle_type' => 'VAN',
            'home_node_id' => $this->first['node_id'],
            'capacity_weight_grams' => 2_500_000,
            'capacity_volume_cm3' => 9_000_000,
        ], (string) random_int(1, 2000000000));
        self::assertSame('VAN-100', $vehicle['vehicle_code']);
        self::assertSame('ایران ۱۱-۱۲۳ ب ۴۵', $vehicle['plate_number']);

        self::assertSame(1, $this->fleet->drivers($actor, ['search' => 'آزمون'])->total());
        self::assertSame(1, $this->fleet->vehicles($actor, ['vehicle_type' => 'VAN'])->total());

        $updated = $this->fleet->updateDriver($actor, $driver['driver_id'], [
            'expected_version' => 1,
            'availability_status' => 'TEMPORARILY_INACTIVE',
            'capabilities' => ['LINEHAUL'],
        ], (string) random_int(1, 2000000000));
        self::assertSame(2, $updated['version']);
        self::assertSame(['LINEHAUL'], $updated['capabilities']);

        $inactive = $this->fleet->updateVehicle($actor, $vehicle['vehicle_id'], [
            'expected_version' => 1,
            'status' => 'INACTIVE',
        ], (string) random_int(1, 2000000000));
        self::assertSame('INACTIVE', $inactive['status']);
        self::assertSame('INACTIVE', $inactive['availability_status']);
        self::assertDatabaseCount('audit_events', 4);
        self::assertCount(4, $this->outbox->events);
    }

    public function test_access_is_fail_closed_for_entitlement_and_permission(): void
    {
        $actor = $this->actor($this->first['hq_id'], $this->first['user_id']);
        $this->authorization->entitled = false;
        $this->expectApi(ApiErrorCode::EntitlementDisabled, fn () => $this->fleet->drivers($actor, []));

        $this->authorization->entitled = true;
        $this->authorization->permissions = ['fleet.driver.view'];
        $this->expectApi(ApiErrorCode::PermissionDenied, fn () => $this->fleet->createDriver($actor, [
            'driver_code' => 'DENIED', 'display_name' => 'Denied', 'home_node_id' => $this->first['node_id'], 'capabilities' => ['PICKUP'],
        ], (string) random_int(1, 2000000000)));
    }

    public function test_cross_tenant_nodes_users_and_resources_are_not_usable(): void
    {
        $actor = $this->actor($this->first['hq_id'], $this->first['user_id']);
        $this->expectApi(ApiErrorCode::ValidationError, fn () => $this->fleet->createDriver($actor, [
            'driver_code' => 'CROSS-NODE', 'display_name' => 'Cross', 'home_node_id' => $this->second['node_id'], 'capabilities' => ['PICKUP'],
        ], (string) random_int(1, 2000000000)));
        $this->expectApi(ApiErrorCode::ValidationError, fn () => $this->fleet->createDriver($actor, [
            'driver_code' => 'CROSS-USER', 'display_name' => 'Cross', 'user_id' => $this->second['linked_user_id'],
            'home_node_id' => $this->first['node_id'], 'capabilities' => ['PICKUP'],
        ], (string) random_int(1, 2000000000)));

        $otherDriver = $this->fleet->createDriver($this->actor($this->second['hq_id'], $this->second['user_id']), [
            'driver_code' => 'OTHER', 'display_name' => 'Other', 'home_node_id' => $this->second['node_id'], 'capabilities' => ['DELIVERY'],
        ], (string) random_int(1, 2000000000));
        $this->expectApi(ApiErrorCode::ResourceNotFound, fn () => $this->fleet->driverDetail($actor, $otherDriver['driver_id']));
    }

    public function test_duplicate_identity_and_stale_versions_are_rejected(): void
    {
        $actor = $this->actor($this->first['hq_id'], $this->first['user_id']);
        $driver = $this->fleet->createDriver($actor, [
            'driver_code' => 'VERSIONED', 'display_name' => 'Versioned', 'user_id' => $this->first['linked_user_id'],
            'home_node_id' => $this->first['node_id'], 'capabilities' => ['PICKUP'],
        ], (string) random_int(1, 2000000000));
        $this->expectApi(ApiErrorCode::Conflict, fn () => $this->fleet->createDriver($actor, [
            'driver_code' => 'DUPLICATE-IDENTITY', 'display_name' => 'Duplicate', 'user_id' => $this->first['linked_user_id'],
            'home_node_id' => $this->first['node_id'], 'capabilities' => ['DELIVERY'],
        ], (string) random_int(1, 2000000000)));
        $this->fleet->updateDriver($actor, $driver['driver_id'], ['expected_version' => 1, 'display_name' => 'Updated'], (string) random_int(1, 2000000000));
        $this->expectApi(ApiErrorCode::VersionConflict, fn () => $this->fleet->updateDriver(
            $actor, $driver['driver_id'], ['expected_version' => 1, 'display_name' => 'Stale'], (string) random_int(1, 2000000000),
        ));
    }

    public function test_nullable_patches_preserve_omitted_fields_and_audit_both_snapshots(): void
    {
        $actor = $this->actor($this->first['hq_id'], $this->first['user_id']);
        $driver = $this->fleet->createDriver($actor, [
            'driver_code' => 'NULLABLE', 'display_name' => 'Before', 'user_id' => $this->first['linked_user_id'],
            'home_node_id' => $this->first['node_id'], 'mobile' => '09120000000', 'capabilities' => ['PICKUP', 'DELIVERY'],
        ], (string) random_int(1, 2000000000));
        $preserved = $this->fleet->updateDriver($actor, $driver['driver_id'], [
            'expected_version' => 1, 'display_name' => 'After',
        ], (string) random_int(1, 2000000000));
        self::assertSame($driver['user_id'], $preserved['user_id']);
        self::assertSame($driver['mobile'], $preserved['mobile']);
        $cleared = $this->fleet->updateDriver($actor, $driver['driver_id'], [
            'expected_version' => 2, 'user_id' => null, 'mobile' => null, 'capabilities' => ['LINEHAUL'],
        ], (string) random_int(1, 2000000000));
        self::assertNull($cleared['user_id']);
        self::assertNull($cleared['mobile']);
        $audit = RecordFixtureQuery::table('audit_events')->where('action_key', 'FLEET_DRIVER_UPDATED')->orderByDesc('id')->first();
        $beforeSnapshot = json_decode($audit->before_snapshot, true);
        $afterSnapshot = json_decode($audit->after_snapshot, true);
        self::assertSame($preserved['mobile'], $beforeSnapshot['mobile']);
        self::assertSame($preserved['capabilities'], $beforeSnapshot['capabilities']);
        self::assertSame(2, $beforeSnapshot['version']);
        self::assertNull($afterSnapshot['mobile']);
        self::assertSame(['LINEHAUL'], $afterSnapshot['capabilities']);
        self::assertSame(3, $afterSnapshot['version']);
        self::assertSame('[REDACTED]', $afterSnapshot['driver_code']);

        $vehicle = $this->fleet->createVehicle($actor, [
            'vehicle_code' => 'NULLABLE-VAN', 'plate_number' => 'NULLABLE-PLATE', 'vehicle_type' => 'VAN',
            'home_node_id' => $this->first['node_id'], 'capacity_weight_grams' => 1000, 'capacity_volume_cm3' => 2000,
        ], (string) random_int(1, 2000000000));
        $changed = $this->fleet->updateVehicle($actor, $vehicle['vehicle_id'], [
            'expected_version' => 1, 'capacity_weight_grams' => null,
        ], (string) random_int(1, 2000000000));
        self::assertNull($changed['capacity_weight_grams']);
        self::assertSame(2000, $changed['capacity_volume_cm3']);
    }

    public function test_outbox_failure_rolls_back_driver_capabilities_and_audit(): void
    {
        $actor = $this->actor($this->first['hq_id'], $this->first['user_id']);
        $driver = $this->fleet->createDriver($actor, [
            'driver_code' => 'ATOMIC', 'display_name' => 'Before',
            'home_node_id' => $this->first['node_id'], 'capabilities' => ['PICKUP'],
        ], (string) random_int(1, 2000000000));
        $this->outbox->fail = true;
        try {
            $this->fleet->updateDriver($actor, $driver['driver_id'], [
                'expected_version' => 1, 'display_name' => 'Failed', 'capabilities' => ['DELIVERY'],
            ], (string) random_int(1, 2000000000));
            self::fail('The failed outbox write must roll back the mutation.');
        } catch (RuntimeException $error) {
            self::assertSame('Simulated outbox failure.', $error->getMessage());
        }
        self::assertSame($driver, $this->fleet->driverDetail($actor, $driver['driver_id']));
        self::assertDatabaseCount('audit_events', 1);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->authorization = new FleetTestAuthorization;
        $this->outbox = new FleetTestOutbox;
        $this->app->instance(AccessContextResolverInterface::class, $this->authorization);
        $this->app->instance(OutboxWriterInterface::class, $this->outbox);
        $this->fleet = $this->app->make(FleetAdministrationService::class);
        $this->first = $this->organization('HQ-A');
        $this->second = $this->organization('HQ-B');
    }

    /** @return array{hq_id:string,node_id:string,user_id:string,linked_user_id:string} */
    private function organization(string $code): array
    {
        $hqId = (string) random_int(1, 2000000000);
        $areaId = (string) random_int(1, 2000000000);
        $nodeId = (string) random_int(1, 2000000000);
        $userId = (string) random_int(1, 2000000000);
        $linkedUserId = (string) random_int(1, 2000000000);
        RecordFixtureQuery::table('hq_tenants')->insert(['hq_id' => $hqId, 'hq_code' => $code, 'hq_title' => $code, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);
        RecordFixtureQuery::table('areas')->insert(['area_id' => $areaId, 'hq_id' => $hqId, 'area_title' => $code, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);
        RecordFixtureQuery::table('nodes')->insert(['node_id' => $nodeId, 'hq_id' => $hqId, 'area_id' => $areaId, 'node_code' => "{$code}-NODE", 'node_title' => $code, 'node_type' => 'BRANCH', 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([[$userId, '147232623'], [$linkedUserId, '189656963']] as [$id, $suffix]) {
            RecordFixtureQuery::table('users')->insert(['user_id' => $id, 'hq_id' => $hqId, 'username' => "{$code}-{$suffix}", 'normalized_username' => mb_strtolower("{$code}-{$suffix}"), 'first_name' => 'Test', 'last_name' => 'User', 'display_name' => "{$code} {$suffix}", 'status' => 'ACTIVE', 'must_change_password' => false, 'activated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }

        return compact('hqId', 'nodeId', 'userId', 'linkedUserId') + [
            'hq_id' => $hqId, 'node_id' => $nodeId, 'user_id' => $userId, 'linked_user_id' => $linkedUserId,
        ];
    }

    private function actor(string $hqId, string $userId): AuthenticatedPrincipal
    {
        return new AuthenticatedPrincipal($userId, (string) random_int(1, 2000000000), $hqId, false);
    }

    private function expectApi(ApiErrorCode $error, callable $callback): void
    {
        try {
            $callback();
            self::fail("Expected {$error->value}.");
        } catch (ApiException $exception) {
            self::assertSame($error, $exception->errorCode);
        }
    }
}

final class FleetTestAuthorization implements AccessContextResolverInterface
{
    public bool $entitled = true;

    /** @var list<string> */
    public array $permissions = ['fleet.driver.view', 'fleet.driver.manage', 'fleet.vehicle.view', 'fleet.vehicle.manage'];

    public function resolve(AuthenticatedPrincipal $principal): AccessContextDto
    {
        return AccessContexts::make([
            'module_entitlements' => [['module_code' => 'Driver', 'status' => $this->entitled ? 'ENABLED' : 'DISABLED']],
            'permissions' => $this->permissions,
            'hq_id' => $principal->hqId,
            'permission_scopes' => array_fill_keys($this->permissions, [['scope_type' => 'TENANT', 'scope_id' => null, 'includes_descendants' => false]]),
            'accessible_node_ids' => [],
        ]);
    }
}

final class FleetTestOutbox implements OutboxWriterInterface
{
    public bool $fail = false;

    /** @var list<array<string,mixed>> */
    public array $events = [];

    public function write(?string $hqId, string $aggregateType, string $aggregateId, string $eventType, string $correlationId, array $payload, int $eventVersion = 1, ?string $causationId = null): void
    {
        if ($this->fail) {
            throw new RuntimeException('Simulated outbox failure.');
        }
        $this->events[] = compact('hqId', 'aggregateType', 'aggregateId', 'eventType', 'correlationId', 'payload', 'eventVersion', 'causationId');
    }
}
