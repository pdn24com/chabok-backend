<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\UpdateFleetDriver;

use Modules\Operations\Domain\FleetWriteConflict;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class UpdateFleetDriverHandler
{
    public function __construct(
        private \Modules\Operations\Application\Services\FleetAccessGuard $fleetAccessGuard,
        private \Modules\Operations\Domain\FleetPolicy $fleetPolicy,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Operations\Application\Repositories\FleetRepository $fleet,
        private \Modules\Operations\Application\Services\FleetProjection $fleetProjection,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Operations\Application\Services\DriverCapabilityWriter $driverCapabilityWriter,
        private \Modules\Operations\Application\Services\FleetChangeRecorder $fleetChangeRecorder,
        private \Modules\Operations\Application\Services\FleetFailure $fleetFailure,
    )
    {
    }

    public function handle(UpdateFleetDriverCommand $command): UpdateFleetDriverResult
    {
        return new UpdateFleetDriverResult($this->execute($command->actor, $command->driverId, $command->input, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $driverId, array $input, string $correlationId): array
    {
        $this->fleetAccessGuard->access($actor, 'fleet.driver.manage');
        if (array_key_exists('home_node_id', $input)) {
            $this->fleetAccessGuard->activeNode($actor, (string) $input['home_node_id']);
        }
        if (array_key_exists('user_id', $input)) {
            $this->fleetAccessGuard->availableUser($actor, $input['user_id'], $driverId);
        }
        $capabilities = array_key_exists('capabilities', $input) ? $this->fleetPolicy->capabilities((array) $input['capabilities']) : null;
        try {
            return $this->transactions->run(function () use ($actor, $driverId, $input, $capabilities, $correlationId): array {
                $row = $this->fleet->findDriverForUpdate($actor->hqId, $driverId);
                if ($row === null) {
                    throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Driver not found.');
                }
                $this->fleetAccessGuard->assertScopeNode($actor, (string) $row->home_node_id, 'fleet.driver.manage');
                if (isset($input['home_node_id'])) {
                    $this->fleetAccessGuard->assertScopeNode($actor, $input['home_node_id'], 'fleet.driver.manage');
                }
                $expected = (int) $input['expected_version'];
                if ((int) $row->version !== $expected) {
                    throw new ApiException(ApiErrorCode::VersionConflict, 409, 'The Driver changed since it was loaded.', details: ['current_version' => (int) $row->version]);
                }
                $before = $this->fleetProjection->driverDetailUnchecked($actor, $driverId);
                $status = (string) ($input['status'] ?? $row->status);
                $availability = (string) ($input['availability_status'] ?? $row->availability_status);
                [$status, $availability] = $this->fleetPolicy->lifecycle($status, $availability, array_key_exists('status', $input));
                $changes = [
                    'display_name' => array_key_exists('display_name', $input) ? trim((string) $input['display_name']) : $row->display_name,
                    'user_id' => array_key_exists('user_id', $input) ? $input['user_id'] : $row->user_id,
                    'home_node_id' => $input['home_node_id'] ?? $row->home_node_id,
                    'mobile' => array_key_exists('mobile', $input) ? $this->fleetPolicy->nullableString($input['mobile']) : $row->mobile,
                    'operational_type' => $capabilities === null ? $row->operational_type : $this->fleetPolicy->operationalType($capabilities),
                    'status' => $status,
                    'availability_status' => $availability,
                    'version' => $expected + 1,
                    'updated_at' => $this->clock->now(),
                ];
                $this->fleet->updateDriver($actor->hqId, $driverId, $expected, $changes);
                if ($capabilities !== null) {
                    $this->driverCapabilityWriter->replaceCapabilities($actor->hqId, $driverId, $capabilities);
                }
                $after = $this->fleetProjection->driverDetailUnchecked($actor, $driverId);
                $this->fleetChangeRecorder->record($actor, 'FLEET_DRIVER_UPDATED', 'DRIVER', $driverId, $status, $correlationId, $before, $after);
                return $after;
            });
        } catch (FleetWriteConflict $exception) {
            $this->fleetFailure->rethrowConflict($exception, 'The IAM user is already assigned to another Driver.');
        }
    }
}
