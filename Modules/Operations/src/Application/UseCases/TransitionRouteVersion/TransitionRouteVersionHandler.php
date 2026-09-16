<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\TransitionRouteVersion;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class TransitionRouteVersionHandler
{
    public function __construct(
        private \Modules\Operations\Application\NetworkAccessGuard $access,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Operations\Application\Services\RouteDefinitionReader $routeDefinitionReader,
        private \Modules\Operations\Application\Services\RouteVersionGuard $routeVersionGuard,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Operations\Application\Repositories\RouteDefinitionRepository $routes,
        private \Modules\Operations\Application\Services\RouteLegWriter $routeLegWriter,
        private \Modules\Operations\Application\Services\RouteChangeRecorder $routeChangeRecorder,
        private \Modules\Operations\Application\UseCases\GetRouteVersion\GetRouteVersionHandler $getRouteVersion,
    )
    {
    }

    public function handle(TransitionRouteVersionCommand $command): TransitionRouteVersionResult
    {
        return new TransitionRouteVersionResult($this->execute($command->actor, $command->definitionId, $command->versionId, $command->action, $command->expected, $command->note, $command->correlationId));
    }

    private function execute(
        AuthenticatedPrincipal $actor,
        string $definitionId,
        string $versionId,
        string $action,
        int $expected,
        ?string $note,
        string $correlationId,
    ): array
    {
        $permission = match ($action) {
            'validate' => 'network.route.validate',
            'approve' => 'network.route.approve',
            'publish', 'supersede' => 'network.route.publish',
            default => 'network.route.manage_draft',
        };
        $hq = $this->access->assert($actor, $permission);
        $this->transactions->run(function () use ($actor, $hq, $definitionId, $versionId, $action, $expected, $note, $correlationId): void {
            $row = $this->routeDefinitionReader->lockedVersion($hq, $definitionId, $versionId);
            $this->routeVersionGuard->expected($row, $expected);
            $next = match ($action) {
                'validate' => $this->routeVersionGuard->validatedChanges($hq, $row, $actor->userId),
                'approve' => $this->routeVersionGuard->simpleChanges($row, 'VALIDATED', 'APPROVED', ['approved_by' => $actor->userId, 'approved_at' => $this->clock->now()]),
                'publish' => $this->routeVersionGuard->publishedChanges($hq, $row, $actor->userId),
                'supersede' => $this->routeVersionGuard->simpleChanges($row, 'PUBLISHED', 'SUPERSEDED'),
                'archive' => $this->routeVersionGuard->archiveChanges($row),
                default => throw new ApiException(ApiErrorCode::ValidationError, 422, 'Unknown lifecycle action.'),
            };
            $next['version'] = (int) $row->version + 1;
            $next['updated_at'] = $this->clock->now();
            $this->routes->updateVersion($versionId, $next);
            if ($action === 'publish') {
                $this->routes->publishDefinition($definitionId, $versionId, $this->clock->now());
                $this->routeLegWriter->syncLegacyLegs($row);
            }
            if ($action === 'supersede') {
                $this->routes->supersedeDefinition($definitionId, $versionId, $this->clock->now());
            }
            $this->routeChangeRecorder->record($actor, 'ROUTE_VERSION_' . strtoupper($action), 'ROUTE_DEFINITION_VERSION', $versionId, (string) $next['status'], $correlationId, $note);
        });
        return $this->getRouteVersion->handle(new \Modules\Operations\Application\UseCases\GetRouteVersion\GetRouteVersionCommand($actor, $definitionId, $versionId))->data;
    }
}
