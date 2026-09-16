<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreateFleetDriver;

use Modules\Operations\Domain\FleetWriteConflict;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CreateFleetDriverHandler
{
    public function __construct(
        private \Modules\Operations\Application\Services\FleetAccessGuard $fleetAccessGuard,
        private \Modules\Operations\Domain\FleetPolicy $fleetPolicy,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Operations\Application\Repositories\FleetRepository $fleet,
        private \Modules\Operations\Application\Services\DriverCapabilityWriter $driverCapabilityWriter,
        private \Modules\Operations\Application\Services\FleetProjection $fleetProjection,
        private \Modules\Operations\Application\Services\FleetChangeRecorder $fleetChangeRecorder,
        private \Modules\Operations\Application\Services\FleetFailure $fleetFailure,
    )
    {
    }

    public function handle(CreateFleetDriverCommand $command): CreateFleetDriverResult
    {
        return new CreateFleetDriverResult($this->execute($command->actor, $command->input, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        $this->fleetAccessGuard->access($actor, 'fleet.driver.manage');
        $capabilities = $this->fleetPolicy->capabilities((array) $input['capabilities']);
        $this->fleetAccessGuard->activeNode($actor, (string) $input['home_node_id']);
        $this->fleetAccessGuard->assertScopeNode($actor, (string) $input['home_node_id'], 'fleet.driver.manage');
        $this->fleetAccessGuard->availableUser($actor, $input['user_id'] ?? null);
        try {
            return $this->transactions->run(function () use ($actor, $input, $capabilities, $correlationId): array {
                $id = $this->identifiers->uuid();
                $now = $this->clock->now();
                $this->fleet->insertDriver([
                    'driver_id' => $id,
                    'hq_id' => $actor->hqId,
                    'user_id' => $input['user_id'] ?? null,
                    'driver_code' => mb_strtoupper(trim((string) $input['driver_code'])),
                    'display_name' => trim((string) $input['display_name']),
                    'mobile' => $this->fleetPolicy->nullableString($input['mobile'] ?? null),
                    'home_node_id' => $input['home_node_id'],
                    'operational_type' => $this->fleetPolicy->operationalType($capabilities),
                    'status' => 'ACTIVE',
                    'availability_status' => 'AVAILABLE',
                    'version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->driverCapabilityWriter->replaceCapabilities($actor->hqId, $id, $capabilities);
                $after = $this->fleetProjection->driverDetailUnchecked($actor, $id);
                $this->fleetChangeRecorder->record($actor, 'FLEET_DRIVER_CREATED', 'DRIVER', $id, 'ACTIVE', $correlationId, null, $after);
                return $after;
            });
        } catch (FleetWriteConflict $exception) {
            $this->fleetFailure->rethrowConflict($exception, 'Driver code or IAM user is already assigned.');
        }
    }
}
