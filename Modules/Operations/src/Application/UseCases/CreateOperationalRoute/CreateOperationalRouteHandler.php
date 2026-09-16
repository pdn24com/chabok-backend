<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreateOperationalRoute;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class CreateOperationalRouteHandler
{
    public function __construct(
        private \Modules\Operations\Application\Repositories\OperationalDirectoryRepository $directory,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
    )
    {
    }

    public function handle(CreateOperationalRouteCommand $command): CreateOperationalRouteResult
    {
        return new CreateOperationalRouteResult($this->execute($command->hqId, $command->code, $command->title, $command->legs));
    }

    private function execute(string $hqId, string $code, string $title, array $legs): string
    {
        if ($legs === []) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'A route requires at least one leg.');
        }
        $seen = [];
        $previousDestination = null;
        foreach ($legs as $index => $leg) {
            if ($previousDestination !== null && $leg['origin_node_id'] !== $previousDestination) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'The route chain is broken.');
            }
            foreach (['origin_node_id', 'destination_node_id'] as $field) {
                $node = $this->directory->activeNode($hqId, $leg[$field]);
                if ($node === null) {
                    throw new ApiException(ApiErrorCode::ValidationError, 422, 'Every route node must be active and belong to the tenant.');
                }
            }
            if ($leg['origin_node_id'] === $leg['destination_node_id'] || isset($seen[$leg['destination_node_id']])) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Pilot routes cannot contain cycles.');
            }
            $seen[$leg['origin_node_id']] = true;
            $previousDestination = $leg['destination_node_id'];
        }
        return $this->transactions->run(function () use ($hqId, $code, $title, $legs): string {
            $id = $this->identifiers->uuid();
            $this->directory->insertRoute([
                'route_definition_id' => $id,
                'hq_id' => $hqId,
                'route_code' => $code,
                'route_title' => $title,
                'status' => 'ACTIVE',
                'version' => 1,
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
            foreach ($legs as $index => $leg) {
                $this->directory->insertLeg([
                    'route_definition_leg_id' => $this->identifiers->uuid(),
                    'hq_id' => $hqId,
                    'route_definition_id' => $id,
                    'leg_order' => $index + 1,
                    'origin_node_id' => $leg['origin_node_id'],
                    'destination_node_id' => $leg['destination_node_id'],
                    'status' => 'ACTIVE',
                    'created_at' => $this->clock->now(),
                    'updated_at' => $this->clock->now(),
                ]);
            }
            return $id;
        });
    }
}
