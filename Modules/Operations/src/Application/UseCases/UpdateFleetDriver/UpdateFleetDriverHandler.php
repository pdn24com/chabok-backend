<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\UpdateFleetDriver;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\DriverCapabilityWriterInterface;
use Modules\Operations\Application\Contracts\FleetAccessGuardInterface;
use Modules\Operations\Application\Contracts\FleetChangeRecorderInterface;
use Modules\Operations\Application\Repositories\DriverRepositoryInterface;
use Modules\Operations\Domain\Policies\FleetPolicy;
use Modules\Operations\Infrastructure\Persistence\Models\DriverRecord;

final readonly class UpdateFleetDriverHandler
{
    public function __construct(
        private FleetAccessGuardInterface $fleetAccessGuard,
        private FleetPolicy $fleetPolicy,
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private DriverCapabilityWriterInterface $driverCapabilityWriter,
        private FleetChangeRecorderInterface $fleetChangeRecorder,
        private DriverRepositoryInterface $driverRepository,
    ) {}

    public function handle(UpdateFleetDriverCommand $command): DriverRecord
    {
        $actor = $command->actor;
        $driverId = $command->driverId;
        $input = $command->input;
        $correlationId = $command->correlationId;
        $this->fleetAccessGuard->access($actor, 'fleet.driver.manage');
        if ($input->homeNodeId !== null) {
            $this->fleetAccessGuard->activeNode($actor, $input->homeNodeId);
        }
        if ($input->userIdProvided) {
            $this->fleetAccessGuard->availableUser($actor, $input->userId, $driverId);
        }
        $capabilities = $input->capabilities !== null ? $this->fleetPolicy->capabilities($input->capabilities) : null;
        try {
            return $this->connection->transaction(function () use ($actor, $driverId, $input, $capabilities, $correlationId): DriverRecord {
                $row = $this->driverRepository->lockWithCapabilities($actor->hqId, $driverId);
                if ($row === null) {
                    throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'operations.driver_not_found');
                }
                $this->fleetAccessGuard->assertScopeNode($actor, $row->home_node_id, 'fleet.driver.manage');
                if ($input->homeNodeId !== null) {
                    $this->fleetAccessGuard->assertScopeNode($actor, $input->homeNodeId, 'fleet.driver.manage');
                }
                $expected = $input->expectedVersion;
                if ($row->version !== $expected) {
                    throw new ApiException(ApiErrorCode::VersionConflict, 409, 'operations.driver_changed_since_loaded', details: ['current_version' => $row->version]);
                }
                $before = clone $row;
                $status = $input->status ?? $row->status;
                $availability = $input->availabilityStatus ?? $row->availability_status;
                $lifecycle = $this->fleetPolicy->lifecycle($status, $availability, $input->status !== null);
                $changes = [
                    'display_name' => $input->displayName ?? $row->display_name,
                    'user_id' => $input->userIdProvided ? $input->userId : $row->user_id,
                    'home_node_id' => $input->homeNodeId ?? $row->home_node_id,
                    'mobile' => $input->mobileProvided ? $this->fleetPolicy->nullableString($input->mobile) : $row->mobile,
                    'operational_type' => $capabilities === null ? $row->operational_type : $this->fleetPolicy->operationalType($capabilities),
                    'status' => $lifecycle->status,
                    'availability_status' => $lifecycle->availability,
                    'version' => $expected + 1,
                    'updated_at' => $this->clock->now(),
                ];
                $row->forceFill($changes)->save();
                if ($capabilities !== null) {
                    $this->driverCapabilityWriter->replaceCapabilities($actor->hqId, $driverId, $capabilities);
                }
                $after = $row->load('capabilities');
                $this->fleetChangeRecorder->record($actor, 'FLEET_DRIVER_UPDATED', 'DRIVER', $driverId, $lifecycle->status->value, $correlationId, $before, $after);

                return $after;
            }, attempts: 3);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }
            throw new ApiException(ApiErrorCode::Conflict, 409, 'operations.iam_user_is_already_assigned_another_driver');
        }
    }
}
