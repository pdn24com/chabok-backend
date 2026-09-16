<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreateRouteDefinition;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class CreateRouteDefinitionHandler
{
    public function __construct(
        private \Modules\Operations\Application\NetworkAccessGuard $access,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Operations\Application\Repositories\RouteDefinitionRepository $routes,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Operations\Application\Services\RouteChangeRecorder $routeChangeRecorder,
        private \Modules\Operations\Application\UseCases\GetRouteDefinition\GetRouteDefinitionHandler $getRouteDefinition,
    )
    {
    }

    public function handle(CreateRouteDefinitionCommand $command): CreateRouteDefinitionResult
    {
        return new CreateRouteDefinitionResult($this->execute($command->actor, $command->input, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        $hq = $this->access->assert($actor, 'network.route.manage_draft');
        $id = $this->transactions->run(function () use ($actor, $hq, $input, $correlationId): string {
            if ($this->routes->codeExists($hq, $input['route_code'])) {
                throw new ApiException(ApiErrorCode::Conflict, 409, 'The Route Definition code already exists.');
            }
            $id = $this->identifiers->uuid();
            $this->routes->insertDefinition([
                'route_definition_id' => $id,
                'hq_id' => $hq,
                'route_code' => $input['route_code'],
                'route_title' => $input['route_title'],
                'status' => 'INACTIVE',
                'version' => 1,
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
            $this->routeChangeRecorder->record($actor, 'ROUTE_DEFINITION_CREATED', 'ROUTE_DEFINITION', $id, 'DRAFT', $correlationId);
            return $id;
        });
        return $this->getRouteDefinition->handle(new \Modules\Operations\Application\UseCases\GetRouteDefinition\GetRouteDefinitionCommand($actor, $id))->data;
    }
}
