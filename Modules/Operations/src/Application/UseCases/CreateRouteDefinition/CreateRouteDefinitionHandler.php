<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreateRouteDefinition;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\NetworkAccessGuardInterface;
use Modules\Operations\Application\Contracts\RouteChangeRecorderInterface;
use Modules\Operations\Application\Repositories\RouteDefinitionRepositoryInterface;
use Modules\Operations\Application\UseCases\GetRouteDefinition\GetRouteDefinitionCommand;
use Modules\Operations\Application\UseCases\GetRouteDefinition\GetRouteDefinitionHandler;
use Modules\Operations\Domain\Enums\ConfigVersionStatus;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionRecord;

final readonly class CreateRouteDefinitionHandler
{
    public function __construct(
        private NetworkAccessGuardInterface $networkAccessGuard,
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private RouteChangeRecorderInterface $routeChangeRecorder,
        private GetRouteDefinitionHandler $getRouteDefinitionHandler,
        private RouteDefinitionRepositoryInterface $routeDefinitionRepository,
    ) {}

    public function handle(CreateRouteDefinitionCommand $command): RouteDefinitionRecord
    {
        $actor = $command->actor;
        $input = $command->input;
        $correlationId = $command->correlationId;
        $hq = $this->networkAccessGuard->assert($actor, 'network.route.manage_draft');
        $id = $this->connection->transaction(function () use ($actor, $hq, $input, $correlationId): string {
            if ($this->routeDefinitionRepository->codeTaken($hq, $input->code)) {
                throw new ApiException(ApiErrorCode::Conflict, 409, 'operations.route_definition_code_already_exists');
            }
            $definition = new RouteDefinitionRecord;
            $definition->forceFill([

                'hq_id' => $hq,
                'route_code' => $input->code,
                'route_title' => $input->title,
                'status' => 'INACTIVE',
                'version' => 1,
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ])->save();
            $id = (string) $definition->getKey();
            $this->routeChangeRecorder->record($actor, 'ROUTE_DEFINITION_CREATED', 'ROUTE_DEFINITION', $id, ConfigVersionStatus::Draft->value, $correlationId);

            return $id;
        }, attempts: 3);

        return $this->getRouteDefinitionHandler->handle(new GetRouteDefinitionCommand($actor, $id));
    }
}
