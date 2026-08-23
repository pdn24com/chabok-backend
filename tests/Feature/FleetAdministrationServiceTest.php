<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Foundation\Application\Contracts\AuthorizationContextResolver;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Operations\Application\FleetAdministrationService;
use Tests\TestCase;

final class FleetAdministrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private FleetTestAuthorization $authorization;
    private FleetTestOutbox $outbox;
    private FleetAdministrationService $fleet;
    private array $first;
    private array $second;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authorization = new FleetTestAuthorization();
        $this->outbox = new FleetTestOutbox();
        $this->app->instance(AuthorizationContextResolver::class, $this->authorization);
        $this->app->instance(OutboxWriter::class, $this->outbox);
        $this->fleet = $this->app->make(FleetAdministrationService::class);
        $this->first = $this->organization('HQ-A');
        $this->second = $this->organization('HQ-B');
    }

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
        ], (string) Str::uuid());
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
        ], (string) Str::uuid());
        self::assertSame('VAN-100', $vehicle['vehicle_code']);
        self::assertSame('ایران ۱۱-۱۲۳ ب ۴۵', $vehicle['plate_number']);

        self::assertSame(1, $this->fleet->drivers($actor, ['search' => 'آزمون'])->total());
        self::assertSame(1, $this->fleet->vehicles($actor, ['vehicle_type' => 'VAN'])->total());

        $updated = $this->fleet->updateDriver($actor, $driver['driver_id'], [
            'expected_version' => 1,
            'availability_status' => 'TEMPORARILY_INACTIVE',
            'capabilities' => ['LINEHAUL'],
        ], (string) Str::uuid());
        self::assertSame(2, $updated['version']);
        self::assertSame(['LINEHAUL'], $updated['capabilities']);

        $inactive = $this->fleet->updateVehicle($actor, $vehicle['vehicle_id'], [
            'expected_version' => 1,
            'status' => 'INACTIVE',
        ], (string) Str::uuid());
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
        ], (string) Str::uuid()));
    }

    public function test_cross_tenant_nodes_users_and_resources_are_not_usable(): void
    {
        $actor = $this->actor($this->first['hq_id'], $this->first['user_id']);
        $this->expectApi(ApiErrorCode::ValidationError, fn () => $this->fleet->createDriver($actor, [
            'driver_code' => 'CROSS-NODE', 'display_name' => 'Cross', 'home_node_id' => $this->second['node_id'], 'capabilities' => ['PICKUP'],
        ], (string) Str::uuid()));
        $this->expectApi(ApiErrorCode::ValidationError, fn () => $this->fleet->createDriver($actor, [
            'driver_code' => 'CROSS-USER', 'display_name' => 'Cross', 'user_id' => $this->second['linked_user_id'],
            'home_node_id' => $this->first['node_id'], 'capabilities' => ['PICKUP'],
        ], (string) Str::uuid()));

        $otherDriver = $this->fleet->createDriver($this->actor($this->second['hq_id'], $this->second['user_id']), [
            'driver_code' => 'OTHER', 'display_name' => 'Other', 'home_node_id' => $this->second['node_id'], 'capabilities' => ['DELIVERY'],
        ], (string) Str::uuid());
        $this->expectApi(ApiErrorCode::ResourceNotFound, fn () => $this->fleet->driverDetail($actor, $otherDriver['driver_id']));
    }

    public function test_duplicate_identity_and_stale_versions_are_rejected(): void
    {
        $actor = $this->actor($this->first['hq_id'], $this->first['user_id']);
        $driver = $this->fleet->createDriver($actor, [
            'driver_code' => 'VERSIONED', 'display_name' => 'Versioned', 'user_id' => $this->first['linked_user_id'],
            'home_node_id' => $this->first['node_id'], 'capabilities' => ['PICKUP'],
        ], (string) Str::uuid());
        $this->expectApi(ApiErrorCode::Conflict, fn () => $this->fleet->createDriver($actor, [
            'driver_code' => 'DUPLICATE-IDENTITY', 'display_name' => 'Duplicate', 'user_id' => $this->first['linked_user_id'],
            'home_node_id' => $this->first['node_id'], 'capabilities' => ['DELIVERY'],
        ], (string) Str::uuid()));
        $this->fleet->updateDriver($actor, $driver['driver_id'], ['expected_version' => 1, 'display_name' => 'Updated'], (string) Str::uuid());
        $this->expectApi(ApiErrorCode::VersionConflict, fn () => $this->fleet->updateDriver(
            $actor, $driver['driver_id'], ['expected_version' => 1, 'display_name' => 'Stale'], (string) Str::uuid(),
        ));
    }

    /** @return array{hq_id:string,node_id:string,user_id:string,linked_user_id:string} */
    private function organization(string $code): array
    {
        $hqId = (string) Str::uuid();
        $areaId = (string) Str::uuid();
        $nodeId = (string) Str::uuid();
        $userId = (string) Str::uuid();
        $linkedUserId = (string) Str::uuid();
        DB::table('hq_tenants')->insert(['hq_id' => $hqId, 'hq_code' => $code, 'hq_title' => $code, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('areas')->insert(['area_id' => $areaId, 'hq_id' => $hqId, 'area_title' => $code, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('nodes')->insert(['node_id' => $nodeId, 'hq_id' => $hqId, 'area_id' => $areaId, 'node_code' => "{$code}-NODE", 'node_title' => $code, 'node_type' => 'BRANCH', 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([[$userId, 'admin'], [$linkedUserId, 'driver']] as [$id, $suffix]) {
            DB::table('users')->insert(['user_id' => $id, 'hq_id' => $hqId, 'username' => "{$code}-{$suffix}", 'normalized_username' => mb_strtolower("{$code}-{$suffix}"), 'first_name' => 'Test', 'last_name' => 'User', 'display_name' => "{$code} {$suffix}", 'status' => 'ACTIVE', 'must_change_password' => false, 'activated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }

        return compact('hqId', 'nodeId', 'userId', 'linkedUserId') + [
            'hq_id' => $hqId, 'node_id' => $nodeId, 'user_id' => $userId, 'linked_user_id' => $linkedUserId,
        ];
    }

    private function actor(string $hqId, string $userId): AuthenticatedPrincipal
    {
        return new AuthenticatedPrincipal($userId, (string) Str::uuid(), $hqId, false);
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

final class FleetTestAuthorization implements AuthorizationContextResolver
{
    public bool $entitled = true;
    /** @var list<string> */
    public array $permissions = ['fleet.driver.view', 'fleet.driver.manage', 'fleet.vehicle.view', 'fleet.vehicle.manage'];

    public function resolve(AuthenticatedPrincipal $principal): array
    {
        return [
            'module_entitlements' => [['module_code' => 'Driver', 'status' => $this->entitled ? 'ENABLED' : 'DISABLED']],
            'permissions' => $this->permissions,
            'accessible_node_ids' => [],
        ];
    }
}

final class FleetTestOutbox implements OutboxWriter
{
    /** @var list<array<string,mixed>> */
    public array $events = [];

    public function write(?string $hqId, string $aggregateType, string $aggregateId, string $eventType, string $correlationId, array $payload, int $eventVersion = 1, ?string $causationId = null): void
    {
        $this->events[] = compact('hqId', 'aggregateType', 'aggregateId', 'eventType', 'correlationId', 'payload', 'eventVersion', 'causationId');
    }
}
