<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreateRouteVersion;

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

final readonly class CreateRouteVersionHandler
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

    public function handle(CreateRouteVersionCommand $command): RouteDefinitionVersionRecord
    {
        $actor = $command->actor;
        $definitionId = $command->definitionId;
        $input = $command->input;
        $correlationId = $command->correlationId;
        $hq = $this->networkAccessGuard->assert($actor, 'network.route.manage_draft');
        $id = $this->connection->transaction(function () use ($actor, $hq, $definitionId, $input, $correlationId): string {
            if (! $this->routeDefinitionRepository->definitionExists($hq, $definitionId)) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            $legs = $input->legs;
            if ($input->sourceVersionId !== null && $legs === []) {
                $legs = $this->routeDefinitionReader->legInputs($hq, $input->sourceVersionId);
            }
            $this->routeVersionGuard->validateContent($hq, $input->withLegs($legs));
            $number = $this->routeDefinitionRepository->nextVersionNumber($definitionId);
            $version = new RouteDefinitionVersionRecord;
            $version->forceFill([

                'hq_id' => $hq,
                'route_definition_id' => $definitionId,
                'version_number' => $number,
                'status' => ConfigVersionStatus::Draft->value,
                'purpose' => $input->purpose->value,
                'origin_node_id' => $input->originNodeId,
                'destination_node_id' => $input->destinationNodeId,
                'priority' => $input->priority,
                'offering_version_id' => $input->offeringVersionId,
                'effective_from' => $input->effectiveFrom,
                'effective_to' => $input->effectiveTo,
                'version' => 1,
                'created_by' => $actor->userId,
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ])->save();
            $id = (string) $version->getKey();
            $this->routeLegWriter->replaceLegs($hq, $id, $legs);
            $this->routeChangeRecorder->record($actor, 'ROUTE_VERSION_CREATED', 'ROUTE_DEFINITION_VERSION', $id, ConfigVersionStatus::Draft->value, $correlationId);

            return $id;
        }, attempts: 3);

        return $this->getRouteVersionHandler->handle(new GetRouteVersionCommand($actor, $definitionId, $id));
    }
}
