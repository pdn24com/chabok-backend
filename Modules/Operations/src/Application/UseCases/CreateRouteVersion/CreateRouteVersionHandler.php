<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreateRouteVersion;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class CreateRouteVersionHandler
{
    public function __construct(
        private \Modules\Operations\Application\NetworkAccessGuard $access,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Operations\Application\Repositories\RouteDefinitionRepository $routes,
        private \Modules\Operations\Application\Services\RouteDefinitionReader $routeDefinitionReader,
        private \Modules\Operations\Application\Services\RouteVersionGuard $routeVersionGuard,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Operations\Application\Services\RouteLegWriter $routeLegWriter,
        private \Modules\Operations\Application\Services\RouteChangeRecorder $routeChangeRecorder,
        private \Modules\Operations\Application\UseCases\GetRouteVersion\GetRouteVersionHandler $getRouteVersion,
    )
    {
    }

    public function handle(CreateRouteVersionCommand $command): CreateRouteVersionResult
    {
        return new CreateRouteVersionResult($this->execute($command->actor, $command->definitionId, $command->input, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $definitionId, array $input, string $correlationId): array
    {
        $hq = $this->access->assert($actor, 'network.route.manage_draft');
        $id = $this->transactions->run(function () use ($actor, $hq, $definitionId, $input, $correlationId): string {
            if (!$this->routes->definitionExists($hq, $definitionId)) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            }
            $legs = (array) ($input['legs'] ?? []);
            if (($input['source_version_id'] ?? null) !== null && $legs === []) {
                $legs = $this->routeDefinitionReader->legInputs($hq, (string) $input['source_version_id']);
            }
            $this->routeVersionGuard->validateContent($hq, $input, $legs);
            $id = $this->identifiers->uuid();
            $number = (int) $this->routes->lastVersionNumber($definitionId) + 1;
            $this->routes->insertVersion([
                'route_definition_version_id' => $id,
                'hq_id' => $hq,
                'route_definition_id' => $definitionId,
                'version_number' => $number,
                'status' => 'DRAFT',
                'purpose' => $input['purpose'],
                'origin_node_id' => $input['origin_node_id'],
                'destination_node_id' => $input['destination_node_id'],
                'priority' => $input['priority'],
                'offering_version_id' => $input['offering_version_id'] ?? null,
                'effective_from' => $input['effective_from'] ?? null,
                'effective_to' => $input['effective_to'] ?? null,
                'version' => 1,
                'created_by' => $actor->userId,
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
            $this->routeLegWriter->replaceLegs($hq, $id, $legs);
            $this->routeChangeRecorder->record($actor, 'ROUTE_VERSION_CREATED', 'ROUTE_DEFINITION_VERSION', $id, 'DRAFT', $correlationId);
            return $id;
        });
        return $this->getRouteVersion->handle(new \Modules\Operations\Application\UseCases\GetRouteVersion\GetRouteVersionCommand($actor, $definitionId, $id))->data;
    }
}
