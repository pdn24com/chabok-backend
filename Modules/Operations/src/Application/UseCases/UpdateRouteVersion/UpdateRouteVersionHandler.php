<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\UpdateRouteVersion;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\NetworkAccessGuardInterface;
use Modules\Operations\Application\Contracts\RouteChangeRecorderInterface;
use Modules\Operations\Application\Contracts\RouteDefinitionReaderInterface;
use Modules\Operations\Application\Contracts\RouteLegWriterInterface;
use Modules\Operations\Application\Contracts\RouteVersionGuardInterface;
use Modules\Operations\Application\Dto\RouteVersionDto;
use Modules\Operations\Application\UseCases\GetRouteVersion\GetRouteVersionCommand;
use Modules\Operations\Application\UseCases\GetRouteVersion\GetRouteVersionHandler;
use Modules\Operations\Domain\Enums\ConfigVersionStatus;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionVersionRecord;

final readonly class UpdateRouteVersionHandler
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
    ) {}

    public function handle(UpdateRouteVersionCommand $command): RouteDefinitionVersionRecord
    {
        $actor = $command->actor;
        $definitionId = $command->definitionId;
        $versionId = $command->versionId;
        $input = $command->input;
        $correlationId = $command->correlationId;
        $hq = $this->networkAccessGuard->assert($actor, 'network.route.manage_draft');
        $this->connection->transaction(function () use ($actor, $hq, $definitionId, $versionId, $input, $correlationId): void {
            $row = $this->routeDefinitionReader->lockedVersion($hq, $definitionId, $versionId);
            if ($row->status !== ConfigVersionStatus::Draft->value) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.only_draft_version_is_editable');
            }
            $this->routeVersionGuard->expected($row, $input->expectedVersion);
            $candidate = $input->applyTo(RouteVersionDto::fromRecord($row));
            $this->routeVersionGuard->validateContent($hq, $candidate);
            if ($input->legs !== null) {
                $this->routeLegWriter->replaceLegs($hq, $versionId, $candidate->legs);
            }
            $row->forceFill([
                'priority' => $candidate->priority,
                'offering_version_id' => $candidate->offeringVersionId,
                'effective_from' => $candidate->effectiveFrom,
                'effective_to' => $candidate->effectiveTo,
                'version' => $row->version + 1,
                'updated_at' => $this->clock->now(),
            ])->save();
            $this->routeChangeRecorder->record($actor, 'ROUTE_VERSION_UPDATED', 'ROUTE_DEFINITION_VERSION', $versionId, ConfigVersionStatus::Draft->value, $correlationId);
        }, attempts: 3);

        return $this->getRouteVersionHandler->handle(new GetRouteVersionCommand($actor, $definitionId, $versionId));
    }
}
