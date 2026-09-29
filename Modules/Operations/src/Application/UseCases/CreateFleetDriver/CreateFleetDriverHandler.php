<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreateFleetDriver;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\DriverCapabilityWriterInterface;
use Modules\Operations\Application\Contracts\FleetAccessGuardInterface;
use Modules\Operations\Application\Contracts\FleetChangeRecorderInterface;
use Modules\Operations\Domain\Policies\FleetPolicy;
use Modules\Operations\Infrastructure\Persistence\Models\DriverRecord;

final readonly class CreateFleetDriverHandler
{
    public function __construct(
        private FleetAccessGuardInterface $fleetAccessGuard,
        private FleetPolicy $fleetPolicy,
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private DriverCapabilityWriterInterface $driverCapabilityWriter,
        private FleetChangeRecorderInterface $fleetChangeRecorder,
    ) {}

    public function handle(CreateFleetDriverCommand $command): DriverRecord
    {
        $actor = $command->actor;
        $input = $command->input;
        $correlationId = $command->correlationId;
        $this->fleetAccessGuard->access($actor, 'fleet.driver.manage');
        $capabilities = $this->fleetPolicy->capabilities($input->capabilities);
        $this->fleetAccessGuard->activeNode($actor, $input->homeNodeId);
        $this->fleetAccessGuard->assertScopeNode($actor, $input->homeNodeId, 'fleet.driver.manage');
        $this->fleetAccessGuard->availableUser($actor, $input->userId);
        try {
            return $this->connection->transaction(function () use ($actor, $input, $capabilities, $correlationId): DriverRecord {
                $now = $this->clock->now();
                $driver = new DriverRecord;
                $driver->forceFill([

                    'hq_id' => $actor->hqId,
                    'user_id' => $input->userId,
                    'driver_code' => mb_strtoupper($input->driverCode),
                    'display_name' => $input->displayName,
                    'mobile' => $this->fleetPolicy->nullableString($input->mobile),
                    'home_node_id' => $input->homeNodeId,
                    'operational_type' => $this->fleetPolicy->operationalType($capabilities),
                    'status' => 'ACTIVE',
                    'availability_status' => 'AVAILABLE',
                    'version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $driver->save();
                $id = (string) $driver->getKey();
                $this->driverCapabilityWriter->replaceCapabilities($actor->hqId, $id, $capabilities);
                $after = $driver->load('capabilities');
                $this->fleetChangeRecorder->record($actor, 'FLEET_DRIVER_CREATED', 'DRIVER', $id, 'ACTIVE', $correlationId, null, $after);

                return $after;
            }, attempts: 3);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }
            throw new ApiException(ApiErrorCode::Conflict, 409, 'operations.driver_code_iam_user_is_already_assigned');
        }
    }
}
