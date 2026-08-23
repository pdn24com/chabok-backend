<?php

declare(strict_types=1);

namespace Modules\Operations\Application;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\AuthorizationContextResolver;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class FleetAdministrationService
{
    private const DRIVER_CAPABILITIES = ['PICKUP', 'LINEHAUL', 'DELIVERY'];
    private const AVAILABILITY = ['AVAILABLE', 'ON_MISSION', 'TEMPORARILY_INACTIVE', 'MAINTENANCE', 'INACTIVE'];

    public function __construct(
        private AuthorizationContextResolver $authorization,
        private TransactionManager $transactions,
        private AuditWriter $audit,
        private OutboxWriter $outbox,
    ) {}

    /** @param array<string, mixed> $filters */
    public function drivers(AuthenticatedPrincipal $actor, array $filters): LengthAwarePaginator
    {
        $this->access($actor, 'fleet.driver.view');
        $query = DB::table('drivers as d')->where('d.hq_id', $actor->hqId);
        $this->applyCommonFilters($query, $filters, 'd');
        if (($filters['search'] ?? '') !== '') {
            $search = '%'.addcslashes(trim((string) $filters['search']), '%_\\').'%';
            $query->where(fn ($q) => $q->where('d.driver_code', 'like', $search)
                ->orWhere('d.display_name', 'like', $search)->orWhere('d.mobile', 'like', $search));
        }
        if (($filters['capability'] ?? '') !== '') {
            $query->whereExists(fn ($q) => $q->selectRaw('1')->from('driver_capabilities as dc')
                ->whereColumn('dc.driver_id', 'd.driver_id')->where('dc.capability', $filters['capability']));
        }
        $page = $query->orderBy('d.driver_code')->paginate(
            perPage: min(100, max(1, (int) ($filters['per_page'] ?? 20))),
            page: max(1, (int) ($filters['page'] ?? 1)),
        );
        $ids = $page->getCollection()->pluck('driver_id')->map(fn ($id): string => (string) $id)->all();
        $capabilities = DB::table('driver_capabilities')->whereIn('driver_id', $ids)
            ->orderByRaw("CASE capability WHEN 'PICKUP' THEN 1 WHEN 'LINEHAUL' THEN 2 WHEN 'DELIVERY' THEN 3 END")->get()
            ->groupBy('driver_id')->map(fn ($rows) => $rows->pluck('capability')->map(fn ($value): string => (string) $value)->all());
        $page->setCollection($page->getCollection()->map(fn ($row): array => $this->driver((array) $row, $capabilities[(string) $row->driver_id] ?? [])));

        return $page;
    }

    /** @return array<string, mixed> */
    public function driverDetail(AuthenticatedPrincipal $actor, string $driverId): array
    {
        $this->access($actor, 'fleet.driver.view');
        $row = DB::table('drivers')->where(['hq_id' => $actor->hqId, 'driver_id' => $driverId])->first();
        if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Driver not found.');

        return $this->driver((array) $row, DB::table('driver_capabilities')->where('driver_id', $driverId)
            ->orderByRaw("CASE capability WHEN 'PICKUP' THEN 1 WHEN 'LINEHAUL' THEN 2 WHEN 'DELIVERY' THEN 3 END")
            ->pluck('capability')->map(fn ($value): string => (string) $value)->all());
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function createDriver(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        $this->access($actor, 'fleet.driver.manage');
        $capabilities = $this->capabilities((array) $input['capabilities']);
        $this->activeNode($actor, (string) $input['home_node_id']);
        $this->availableUser($actor, $input['user_id'] ?? null);

        try {
            return $this->transactions->run(function () use ($actor, $input, $capabilities, $correlationId): array {
                $id = (string) Str::uuid();
                $now = now();
                DB::table('drivers')->insert([
                    'driver_id' => $id,
                    'hq_id' => $actor->hqId,
                    'user_id' => $input['user_id'] ?? null,
                    'driver_code' => Str::upper(trim((string) $input['driver_code'])),
                    'display_name' => trim((string) $input['display_name']),
                    'mobile' => $this->nullableString($input['mobile'] ?? null),
                    'home_node_id' => $input['home_node_id'],
                    'operational_type' => $this->operationalType($capabilities),
                    'status' => 'ACTIVE',
                    'availability_status' => 'AVAILABLE',
                    'version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->replaceCapabilities($actor->hqId, $id, $capabilities);
                $after = $this->driverDetailUnchecked($actor, $id);
                $this->record($actor, 'FLEET_DRIVER_CREATED', 'DRIVER', $id, 'ACTIVE', $correlationId, null, $after);

                return $after;
            });
        } catch (QueryException $exception) {
            $this->rethrowConflict($exception, 'Driver code or IAM user is already assigned.');
        }
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function updateDriver(AuthenticatedPrincipal $actor, string $driverId, array $input, string $correlationId): array
    {
        $this->access($actor, 'fleet.driver.manage');
        if (array_key_exists('home_node_id', $input)) $this->activeNode($actor, (string) $input['home_node_id']);
        if (array_key_exists('user_id', $input)) $this->availableUser($actor, $input['user_id'], $driverId);
        $capabilities = array_key_exists('capabilities', $input) ? $this->capabilities((array) $input['capabilities']) : null;

        try {
            return $this->transactions->run(function () use ($actor, $driverId, $input, $capabilities, $correlationId): array {
                $row = DB::table('drivers')->where(['hq_id' => $actor->hqId, 'driver_id' => $driverId])->lockForUpdate()->first();
                if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Driver not found.');
                $expected = (int) $input['expected_version'];
                if ((int) $row->version !== $expected) {
                    throw new ApiException(ApiErrorCode::VersionConflict, 409, 'The Driver changed since it was loaded.', details: ['current_version' => (int) $row->version]);
                }
                $before = $this->driverDetailUnchecked($actor, $driverId);
                $status = (string) ($input['status'] ?? $row->status);
                $availability = (string) ($input['availability_status'] ?? $row->availability_status);
                [$status, $availability] = $this->lifecycle($status, $availability, array_key_exists('status', $input));
                $changes = [
                    'display_name' => array_key_exists('display_name', $input) ? trim((string) $input['display_name']) : $row->display_name,
                    'user_id' => array_key_exists('user_id', $input) ? $input['user_id'] : $row->user_id,
                    'home_node_id' => $input['home_node_id'] ?? $row->home_node_id,
                    'mobile' => array_key_exists('mobile', $input) ? $this->nullableString($input['mobile']) : $row->mobile,
                    'operational_type' => $capabilities === null ? $row->operational_type : $this->operationalType($capabilities),
                    'status' => $status,
                    'availability_status' => $availability,
                    'version' => $expected + 1,
                    'updated_at' => now(),
                ];
                DB::table('drivers')->where(['hq_id' => $actor->hqId, 'driver_id' => $driverId, 'version' => $expected])->update($changes);
                if ($capabilities !== null) $this->replaceCapabilities($actor->hqId, $driverId, $capabilities);
                $after = $this->driverDetailUnchecked($actor, $driverId);
                $this->record($actor, 'FLEET_DRIVER_UPDATED', 'DRIVER', $driverId, $status, $correlationId, $before, $after);

                return $after;
            });
        } catch (QueryException $exception) {
            $this->rethrowConflict($exception, 'The IAM user is already assigned to another Driver.');
        }
    }

    /** @param array<string, mixed> $filters */
    public function vehicles(AuthenticatedPrincipal $actor, array $filters): LengthAwarePaginator
    {
        $this->access($actor, 'fleet.vehicle.view');
        $query = DB::table('vehicles as v')->where('v.hq_id', $actor->hqId);
        $this->applyCommonFilters($query, $filters, 'v');
        if (($filters['search'] ?? '') !== '') {
            $search = '%'.addcslashes(trim((string) $filters['search']), '%_\\').'%';
            $query->where(fn ($q) => $q->where('v.vehicle_code', 'like', $search)->orWhere('v.plate_number', 'like', $search));
        }
        if (($filters['vehicle_type'] ?? '') !== '') $query->where('v.vehicle_type', $filters['vehicle_type']);
        $page = $query->orderBy('v.vehicle_code')->paginate(
            perPage: min(100, max(1, (int) ($filters['per_page'] ?? 20))),
            page: max(1, (int) ($filters['page'] ?? 1)),
        );
        $page->setCollection($page->getCollection()->map(fn ($row): array => $this->vehicle((array) $row)));

        return $page;
    }

    /** @return array<string, mixed> */
    public function vehicleDetail(AuthenticatedPrincipal $actor, string $vehicleId): array
    {
        $this->access($actor, 'fleet.vehicle.view');
        $row = DB::table('vehicles')->where(['hq_id' => $actor->hqId, 'vehicle_id' => $vehicleId])->first();
        if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Vehicle not found.');

        return $this->vehicle((array) $row);
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function createVehicle(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        $this->access($actor, 'fleet.vehicle.manage');
        $this->activeNode($actor, (string) $input['home_node_id']);

        try {
            return $this->transactions->run(function () use ($actor, $input, $correlationId): array {
                $id = (string) Str::uuid();
                $plate = trim((string) $input['plate_number']);
                $now = now();
                DB::table('vehicles')->insert([
                    'vehicle_id' => $id,
                    'hq_id' => $actor->hqId,
                    'vehicle_code' => Str::upper(trim((string) $input['vehicle_code'])),
                    'registration_number' => $plate,
                    'plate_number' => $plate,
                    'vehicle_type' => $input['vehicle_type'],
                    'home_node_id' => $input['home_node_id'],
                    'capacity_weight_grams' => $input['capacity_weight_grams'] ?? null,
                    'capacity_volume_cm3' => $input['capacity_volume_cm3'] ?? null,
                    'status' => 'ACTIVE',
                    'availability_status' => 'AVAILABLE',
                    'version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $after = $this->vehicleDetailUnchecked($actor, $id);
                $this->record($actor, 'FLEET_VEHICLE_CREATED', 'VEHICLE', $id, 'ACTIVE', $correlationId, null, $after);

                return $after;
            });
        } catch (QueryException $exception) {
            $this->rethrowConflict($exception, 'Vehicle code or plate number already exists.');
        }
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function updateVehicle(AuthenticatedPrincipal $actor, string $vehicleId, array $input, string $correlationId): array
    {
        $this->access($actor, 'fleet.vehicle.manage');
        if (array_key_exists('home_node_id', $input)) $this->activeNode($actor, (string) $input['home_node_id']);

        try {
            return $this->transactions->run(function () use ($actor, $vehicleId, $input, $correlationId): array {
                $row = DB::table('vehicles')->where(['hq_id' => $actor->hqId, 'vehicle_id' => $vehicleId])->lockForUpdate()->first();
                if ($row === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Vehicle not found.');
                $expected = (int) $input['expected_version'];
                if ((int) $row->version !== $expected) {
                    throw new ApiException(ApiErrorCode::VersionConflict, 409, 'The Vehicle changed since it was loaded.', details: ['current_version' => (int) $row->version]);
                }
                $before = $this->vehicle((array) $row);
                $status = (string) ($input['status'] ?? $row->status);
                $availability = (string) ($input['availability_status'] ?? $row->availability_status);
                [$status, $availability] = $this->lifecycle($status, $availability, array_key_exists('status', $input));
                $plate = array_key_exists('plate_number', $input) ? trim((string) $input['plate_number']) : (string) $row->plate_number;
                DB::table('vehicles')->where(['hq_id' => $actor->hqId, 'vehicle_id' => $vehicleId, 'version' => $expected])->update([
                    'plate_number' => $plate,
                    'registration_number' => $plate,
                    'vehicle_type' => $input['vehicle_type'] ?? $row->vehicle_type,
                    'home_node_id' => $input['home_node_id'] ?? $row->home_node_id,
                    'capacity_weight_grams' => array_key_exists('capacity_weight_grams', $input) ? $input['capacity_weight_grams'] : $row->capacity_weight_grams,
                    'capacity_volume_cm3' => array_key_exists('capacity_volume_cm3', $input) ? $input['capacity_volume_cm3'] : $row->capacity_volume_cm3,
                    'status' => $status,
                    'availability_status' => $availability,
                    'version' => $expected + 1,
                    'updated_at' => now(),
                ]);
                $after = $this->vehicleDetailUnchecked($actor, $vehicleId);
                $this->record($actor, 'FLEET_VEHICLE_UPDATED', 'VEHICLE', $vehicleId, $status, $correlationId, $before, $after);

                return $after;
            });
        } catch (QueryException $exception) {
            $this->rethrowConflict($exception, 'The plate number is already used by another Vehicle.');
        }
    }

    private function access(AuthenticatedPrincipal $actor, string $permission): void
    {
        if ($actor->hqId === null) throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
        $context = $this->authorization->resolve($actor);
        if (! collect($context['module_entitlements'])->contains(fn ($entry) => $entry['module_code'] === 'Driver' && $entry['status'] === 'ENABLED')) {
            throw new ApiException(ApiErrorCode::EntitlementDisabled, 403, 'Access denied.');
        }
        if (! in_array($permission, $context['permissions'], true)) throw new ApiException(ApiErrorCode::PermissionDenied, 403, 'Access denied.');
    }

    private function activeNode(AuthenticatedPrincipal $actor, string $nodeId): void
    {
        if (! DB::table('nodes')->where(['hq_id' => $actor->hqId, 'node_id' => $nodeId, 'status' => 'ACTIVE'])->exists()) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Home node must be active and belong to the current HQ.', ['home_node_id' => ['گره مبنا معتبر و فعال نیست.']]);
        }
    }

    private function availableUser(AuthenticatedPrincipal $actor, mixed $userId, ?string $currentDriverId = null): void
    {
        if ($userId === null || $userId === '') return;
        if (! DB::table('users')->where(['hq_id' => $actor->hqId, 'user_id' => $userId])->exists()) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'IAM user must belong to the current HQ.', ['user_id' => ['کاربر انتخاب‌شده متعلق به این سازمان نیست.']]);
        }
        $query = DB::table('drivers')->where('user_id', $userId);
        if ($currentDriverId !== null) $query->where('driver_id', '!=', $currentDriverId);
        if ($query->exists()) throw new ApiException(ApiErrorCode::Conflict, 409, 'The IAM user is already assigned to another Driver.');
    }

    /** @param list<mixed> $values @return list<string> */
    private function capabilities(array $values): array
    {
        $values = array_values(array_unique(array_map(fn ($value): string => (string) $value, $values)));
        sort($values);
        if ($values === [] || array_diff($values, self::DRIVER_CAPABILITIES) !== []) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'At least one valid Driver capability is required.', ['capabilities' => ['حداقل یک قابلیت معتبر انتخاب کنید.']]);
        }

        return $values;
    }

    /** @param list<string> $capabilities */
    private function operationalType(array $capabilities): string
    {
        return count($capabilities) === 1 ? $capabilities[0] : 'MULTI';
    }

    /** @param list<string> $capabilities */
    private function replaceCapabilities(?string $hqId, string $driverId, array $capabilities): void
    {
        DB::table('driver_capabilities')->where('driver_id', $driverId)->delete();
        foreach ($capabilities as $capability) {
            DB::table('driver_capabilities')->insert([
                'driver_capability_id' => (string) Str::uuid(),
                'hq_id' => $hqId,
                'driver_id' => $driverId,
                'capability' => $capability,
                'created_at' => now(),
            ]);
        }
    }

    /** @return array{string,string} */
    private function lifecycle(string $status, string $availability, bool $statusWasProvided): array
    {
        if ($status === 'INACTIVE' && $statusWasProvided) $availability = 'INACTIVE';
        if ($status === 'ACTIVE' && $availability === 'INACTIVE' && $statusWasProvided) $availability = 'AVAILABLE';
        if (! in_array($status, ['ACTIVE', 'INACTIVE'], true) || ! in_array($availability, self::AVAILABILITY, true)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Invalid Fleet lifecycle transition.');
        }
        if (($status === 'INACTIVE') !== ($availability === 'INACTIVE')) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Inactive Fleet resources must have INACTIVE availability.');
        }

        return [$status, $availability];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') return null;

        return trim((string) $value);
    }

    /** @param mixed $query @param array<string,mixed> $filters */
    private function applyCommonFilters($query, array $filters, string $alias): void
    {
        foreach (['status', 'availability_status', 'home_node_id'] as $field) {
            if (($filters[$field] ?? '') !== '') $query->where("{$alias}.{$field}", $filters[$field]);
        }
    }

    /** @param array<string,mixed> $row @param list<string> $capabilities @return array<string,mixed> */
    private function driver(array $row, array $capabilities): array
    {
        return [
            'driver_id' => (string) $row['driver_id'],
            'driver_code' => (string) $row['driver_code'],
            'display_name' => (string) $row['display_name'],
            'user_id' => $row['user_id'] === null ? null : (string) $row['user_id'],
            'home_node_id' => (string) $row['home_node_id'],
            'mobile' => $row['mobile'] === null ? null : (string) $row['mobile'],
            'capabilities' => array_values($capabilities),
            'status' => (string) $row['status'],
            'availability_status' => (string) $row['availability_status'],
            'version' => (int) $row['version'],
        ];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function vehicle(array $row): array
    {
        return [
            'vehicle_id' => (string) $row['vehicle_id'],
            'vehicle_code' => (string) $row['vehicle_code'],
            'plate_number' => (string) ($row['plate_number'] ?? $row['registration_number']),
            'vehicle_type' => (string) $row['vehicle_type'],
            'home_node_id' => (string) $row['home_node_id'],
            'capacity_weight_grams' => $row['capacity_weight_grams'] === null ? null : (int) $row['capacity_weight_grams'],
            'capacity_volume_cm3' => $row['capacity_volume_cm3'] === null ? null : (int) $row['capacity_volume_cm3'],
            'status' => (string) $row['status'],
            'availability_status' => (string) $row['availability_status'],
            'version' => (int) $row['version'],
        ];
    }

    /** @return array<string,mixed> */
    private function driverDetailUnchecked(AuthenticatedPrincipal $actor, string $driverId): array
    {
        $row = (array) DB::table('drivers')->where(['hq_id' => $actor->hqId, 'driver_id' => $driverId])->first();
        $capabilities = DB::table('driver_capabilities')->where('driver_id', $driverId)
            ->orderByRaw("CASE capability WHEN 'PICKUP' THEN 1 WHEN 'LINEHAUL' THEN 2 WHEN 'DELIVERY' THEN 3 END")
            ->pluck('capability')->map(fn ($value): string => (string) $value)->all();

        return $this->driver($row, $capabilities);
    }

    /** @return array<string,mixed> */
    private function vehicleDetailUnchecked(AuthenticatedPrincipal $actor, string $vehicleId): array
    {
        return $this->vehicle((array) DB::table('vehicles')->where(['hq_id' => $actor->hqId, 'vehicle_id' => $vehicleId])->first());
    }

    /** @param array<string,mixed>|null $before @param array<string,mixed> $after */
    private function record(AuthenticatedPrincipal $actor, string $action, string $type, string $id, string $status, string $correlationId, ?array $before, array $after): void
    {
        $this->audit->write($actor->hqId, $actor->userId, $action, $type, $id, $correlationId, before: $before, after: $after, sourceClient: 'BRANCH_PANEL');
        $this->outbox->write($actor->hqId, $type, $id, 'fleet.configuration.changed', $correlationId, [
            'action' => $action,
            'target_type' => $type,
            'target_id' => $id,
            'status' => $status,
        ]);
    }

    private function rethrowConflict(QueryException $exception, string $message): never
    {
        if ((string) $exception->getCode() === '23000') throw new ApiException(ApiErrorCode::Conflict, 409, $message);
        throw $exception;
    }
}
