<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\UseCases\UpdateCatalogItem;

use Illuminate\Database\ConnectionInterface;
use Modules\CrmCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\CrmCatalog\Application\Contracts\CatalogItemValidatorInterface;
use Modules\CrmCatalog\Application\Dto\CatalogItemDraftDto;
use Modules\CrmCatalog\Application\Repositories\CatalogItemIndustryRepositoryInterface;
use Modules\CrmCatalog\Application\Repositories\CatalogItemRepositoryInterface;
use Modules\CrmCatalog\Infrastructure\Persistence\Models\CatalogItemRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class UpdateCatalogItemHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private CatalogAccessGuardInterface $accessGuard,
        private CatalogItemRepositoryInterface $catalogItemRepository,
        private CatalogItemIndustryRepositoryInterface $catalogItemIndustryRepository,
        private CatalogItemValidatorInterface $catalogItemValidator,
    ) {}

    public function handle(UpdateCatalogItemCommand $command): UpdateCatalogItemResult
    {
        $hqId = $this->accessGuard->assertCanManage($command->actor);
        $changes = $command->changes;
        if ($changes->touchesNothing()) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid',
                ['*' => ['catalog.change_is_empty']]);
        }

        $item = $this->connection->transaction(function () use ($hqId, $command, $changes): CatalogItemRecord {
            // The row is locked for the whole transaction: the record carries no version column, so
            // serialising concurrent writers is the guarantee available here.
            $record = $this->catalogItemRepository->lockForTenant($hqId, $command->catalogItemId);
            if ($record === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }

            $linked = $this->catalogItemIndustryRepository->industryIdsForItem($hqId, $command->catalogItemId);
            $current = CatalogItemDraftDto::fromRecord($record, $linked);
            $this->catalogItemValidator->validateChanges($hqId, $changes, $current);
            $merged = $changes->applyTo($current);

            if ($merged->code !== $current->code && $this->catalogItemRepository->codeTaken($hqId, $merged->code, $command->catalogItemId)) {
                throw new ApiException(ApiErrorCode::Conflict, 409, 'catalog.code_already_exists',
                    ['code' => ['catalog.code_already_exists']]);
            }

            $this->catalogItemRepository->update($hqId, $command->catalogItemId, $merged->toAttributes());

            if ($changes->industryIds !== null) {
                // The set is replaced by its difference, so a kept industry keeps its link, its author and its date.
                $this->catalogItemIndustryRepository->detach($hqId, $command->catalogItemId, array_values(array_diff($linked, $merged->industryIds)));
                $this->catalogItemIndustryRepository->attach($hqId, $command->catalogItemId,
                    array_values(array_diff($merged->industryIds, $linked)), $command->actor->userId, $this->clock->now());
            }

            return $this->catalogItemRepository->findForTenant($hqId, $command->catalogItemId) ?? $record;
        }, attempts: 3);

        return new UpdateCatalogItemResult($item);
    }
}
