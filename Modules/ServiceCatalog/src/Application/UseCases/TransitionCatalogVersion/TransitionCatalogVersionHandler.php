<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\TransitionCatalogVersion;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class TransitionCatalogVersionHandler
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\Services\CatalogAccessGuard $catalogAccessGuard,
        private \Modules\ServiceCatalog\Application\Services\CatalogResourceDefinition $catalogResourceDefinition,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\ServiceCatalog\Application\Repositories\CatalogRepository $catalog,
        private \Modules\ServiceCatalog\Application\Services\CatalogVersionGuard $catalogVersionGuard,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\ServiceCatalog\Application\Services\CatalogChangeRecorder $catalogChangeRecorder,
        private \Modules\ServiceCatalog\Application\Services\CatalogReader $catalogReader,
    )
    {
    }

    public function handle(TransitionCatalogVersionCommand $command): TransitionCatalogVersionResult
    {
        return new TransitionCatalogVersionResult($this->execute($command->actor, $command->resource, $command->versionIdValue, $command->action, $command->correlationId));
    }

    private function execute(
        AuthenticatedPrincipal $actor,
        string $resource,
        string $versionIdValue,
        string $action,
        string $correlationId,
    ): array
    {
        $permission = match ($action) {
            'approve' => 'service_catalog.approve',
            'publish' => 'service_catalog.publish',
            default => 'service_catalog.manage_draft',
        };
        $this->catalogAccessGuard->assertAccess($actor, $permission);
        [, $versionId] = $this->catalogResourceDefinition->map($resource);
        return $this->transactions->run(function () use ($actor, $resource, $versionIdValue, $action, $correlationId, $versionId): array {
            $row = $this->catalog->lockVersion($actor->hqId, $resource, $versionIdValue);
            if ($row === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            }
            $changes = match ($action) {
                'approve' => $this->catalogVersionGuard->approvalChanges($actor, $row, $resource, $versionIdValue),
                'publish' => $this->catalogVersionGuard->publicationChanges($actor, $row, $resource, $versionIdValue),
                'supersede' => $this->catalogVersionGuard->simpleTransition($row, 'PUBLISHED', 'SUPERSEDED'),
                'archive' => $this->catalogVersionGuard->simpleTransition($row, 'SUPERSEDED', 'ARCHIVED'),
                default => throw new ApiException(ApiErrorCode::ValidationError, 422, 'Unsupported lifecycle action.'),
            };
            $this->catalog->updateVersion($resource, $versionIdValue, $changes + ['updated_at' => $this->clock->now()]);
            $event = 'SERVICE_CATALOG_VERSION_' . mb_strtoupper($action) . 'D';
            $this->catalogChangeRecorder->record($actor, $event, 'SERVICE_CATALOG_VERSION', $versionIdValue, $correlationId, ['status' => $changes['status']]);
            return $this->catalogReader->versionDetail($actor, $resource, $versionIdValue);
        });
    }
}
