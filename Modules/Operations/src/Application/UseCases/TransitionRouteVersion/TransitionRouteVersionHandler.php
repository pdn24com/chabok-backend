<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\TransitionRouteVersion;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\NetworkAccessGuardInterface;
use Modules\Operations\Application\Contracts\RouteChangeRecorderInterface;
use Modules\Operations\Application\Contracts\RouteDefinitionReaderInterface;
use Modules\Operations\Application\Contracts\RouteLegWriterInterface;
use Modules\Operations\Application\Contracts\RouteVersionGuardInterface;
use Modules\Operations\Application\Repositories\RouteDefinitionRepositoryInterface;
use Modules\Operations\Application\UseCases\GetRouteVersion\GetRouteVersionCommand;
use Modules\Operations\Application\UseCases\GetRouteVersion\GetRouteVersionHandler;
use Modules\Operations\Domain\Enums\ConfigVersionStatus;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionVersionRecord;

final readonly class TransitionRouteVersionHandler
{
    public function __construct(
        private NetworkAccessGuardInterface $networkAccessGuard,
        private ConnectionInterface $connection,
        private RouteDefinitionReaderInterface $routeDefinitionReader,
        private RouteVersionGuardInterface $routeVersionGuard,
        private ClockInterface $clock,
        private RouteLegWriterInterface $routeLegWriter,
        private RouteChangeRecorderInterface $routeChangeRecorder,
        private GetRouteVersionHandler $getRouteVersionHandler,
        private RouteDefinitionRepositoryInterface $routeDefinitionRepository,
    ) {}

    public function handle(TransitionRouteVersionCommand $command): RouteDefinitionVersionRecord
    {
        $actor = $command->actor;
        $definitionId = $command->definitionId;
        $versionId = $command->versionId;
        $action = $command->action;
        $expected = $command->expected;
        $note = $command->note;
        $correlationId = $command->correlationId;
        $permission = match ($action) {
            'validate' => 'network.route.validate',
            'approve' => 'network.route.approve',
            'publish', 'supersede' => 'network.route.publish',
            default => 'network.route.manage_draft',
        };
        $hq = $this->networkAccessGuard->assert($actor, $permission);
        $this->connection->transaction(function () use ($actor, $hq, $definitionId, $versionId, $action, $expected, $note, $correlationId): void {
            $row = $this->routeDefinitionReader->lockedVersion($hq, $definitionId, $versionId);
            $this->routeVersionGuard->expected($row, $expected);
            $next = match ($action) {
                'validate' => $this->routeVersionGuard->validatedChanges($hq, $row, $actor->userId),
                'approve' => $this->routeVersionGuard->simpleChanges($row, ConfigVersionStatus::Validated->value, ConfigVersionStatus::Approved->value, ['approved_by' => $actor->userId, 'approved_at' => $this->clock->now()]),
                'publish' => $this->routeVersionGuard->publishedChanges($hq, $row, $actor->userId),
                'supersede' => $this->routeVersionGuard->simpleChanges($row, ConfigVersionStatus::Published->value, ConfigVersionStatus::Superseded->value),
                'archive' => $this->routeVersionGuard->archiveChanges($row),
                default => throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.unknown_lifecycle_action'),
            };
            $next['version'] = (int) $row->version + 1;
            $next['updated_at'] = $this->clock->now();
            $row->forceFill($next)->save();
            if ($action === 'publish') {
                $this->routeDefinitionRepository->publishVersion($definitionId, ['published_version_id' => $versionId, 'status' => 'ACTIVE', 'updated_at' => $this->clock->now()]);
                $this->routeLegWriter->syncLegacyLegs($row);
            }
            if ($action === 'supersede') {
                $this->routeDefinitionRepository->retirePublishedVersion($definitionId, $versionId, ['published_version_id' => null, 'status' => 'INACTIVE', 'updated_at' => $this->clock->now()]);
            }
            $this->routeChangeRecorder->record($actor, 'ROUTE_VERSION_'.strtoupper($action), 'ROUTE_DEFINITION_VERSION', $versionId, (string) $next['status'], $correlationId, $note);
        }, attempts: 3);

        return $this->getRouteVersionHandler->handle(new GetRouteVersionCommand($actor, $definitionId, $versionId));
    }
}
