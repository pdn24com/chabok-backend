<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\UpdateRouteVersion;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class UpdateRouteVersionHandler
{
    public function __construct(
        private \Modules\Operations\Application\NetworkAccessGuard $access,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Operations\Application\Services\RouteDefinitionReader $routeDefinitionReader,
        private \Modules\Operations\Application\Services\RouteVersionGuard $routeVersionGuard,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Operations\Application\Services\RouteLegWriter $routeLegWriter,
        private \Modules\Operations\Application\Repositories\RouteDefinitionRepository $routes,
        private \Modules\Operations\Application\Services\RouteChangeRecorder $routeChangeRecorder,
        private \Modules\Operations\Application\UseCases\GetRouteVersion\GetRouteVersionHandler $getRouteVersion,
    )
    {
    }

    public function handle(UpdateRouteVersionCommand $command): UpdateRouteVersionResult
    {
        return new UpdateRouteVersionResult($this->execute($command->actor, $command->definitionId, $command->versionId, $command->input, $command->correlationId));
    }

    private function execute(
        AuthenticatedPrincipal $actor,
        string $definitionId,
        string $versionId,
        array $input,
        string $correlationId,
    ): array
    {
        $hq = $this->access->assert($actor, 'network.route.manage_draft');
        $this->transactions->run(function () use ($actor, $hq, $definitionId, $versionId, $input, $correlationId): void {
            $row = $this->routeDefinitionReader->lockedVersion($hq, $definitionId, $versionId);
            if ($row->status !== 'DRAFT') {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only a draft version is editable.');
            }
            $this->routeVersionGuard->expected($row, (int) $input['expected_version']);
            $candidate = array_merge((array) $row, $input);
            $legs = array_key_exists('legs', $input) ? $input['legs'] : $this->routeDefinitionReader->legInputs($hq, $versionId);
            $this->routeVersionGuard->validateContent($hq, $candidate, $legs);
            $changes = ['version' => (int) $row->version + 1, 'updated_at' => $this->clock->now()];
            foreach (['priority', 'offering_version_id', 'effective_from', 'effective_to'] as $field) {
                if (array_key_exists($field, $input)) {
                    $changes[$field] = $input[$field];
                }
            }
            if (array_key_exists('legs', $input)) {
                $this->routeLegWriter->replaceLegs($hq, $versionId, $legs);
            }
            $this->routes->updateVersion($versionId, $changes);
            $this->routeChangeRecorder->record($actor, 'ROUTE_VERSION_UPDATED', 'ROUTE_DEFINITION_VERSION', $versionId, 'DRAFT', $correlationId);
        });
        return $this->getRouteVersion->handle(new \Modules\Operations\Application\UseCases\GetRouteVersion\GetRouteVersionCommand($actor, $definitionId, $versionId))->data;
    }
}
