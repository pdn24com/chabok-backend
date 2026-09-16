<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\SetCatalogRecordActive;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class SetCatalogRecordActiveHandler
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\Services\CatalogRecordAccessGuard $catalogRecordAccessGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\ServiceCatalog\Application\Repositories\CatalogRecordRepository $records,
        private \Modules\ServiceCatalog\Application\Services\CatalogRecordReader $catalogRecordReader,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\ServiceCatalog\Application\CurrentCatalog $currentCatalog,
        private \Modules\ServiceCatalog\Application\Services\CatalogRecordChangeRecorder $catalogRecordChangeRecorder,
    )
    {
    }

    public function handle(SetCatalogRecordActiveCommand $command): SetCatalogRecordActiveResult
    {
        return new SetCatalogRecordActiveResult($this->execute($command->actor, $command->resource, $command->id, $command->active, $command->expected, $command->correlation));
    }

    private function execute(
        AuthenticatedPrincipal $actor,
        string $resource,
        string $id,
        bool $active,
        int $expected,
        string $correlation,
    ): array
    {
        $this->catalogRecordAccessGuard->authorize($actor, true);
        return $this->transactions->run(function () use ($actor, $resource, $id, $active, $expected, $correlation): array {
            [$identityId] = \Modules\ServiceCatalog\Domain\CatalogResource::keys($resource);
            $identity = $this->records->lockIdentity($actor->hqId, $resource, $id);
            if ($identity === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            }
            $before = $this->catalogRecordReader->detail($actor, $resource, $id);
            $status = $active ? 'ACTIVE' : 'INACTIVE';
            if ($identity->status === $status) {
                return $before;
            }
            if ((int) $identity->edit_lock !== $expected) {
                throw new ApiException(ApiErrorCode::VersionConflict, 409, 'The record changed since it was loaded.');
            }
            $this->records->updateIdentity($resource, $id, ['status' => $status, 'updated_at' => $this->clock->now()]);
            if ($active) {
                $this->currentCatalog->resolve($resource, $id, (string) $actor->hqId);
            }
            $after = $this->catalogRecordReader->detail($actor, $resource, $id);
            $this->catalogRecordChangeRecorder->record($actor, $id, $correlation, 'SERVICE_CATALOG_STATUS_CHANGED', $before, $after);
            return $after;
        });
    }
}
