<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\UseCases\CreateCatalogItem;

use Illuminate\Database\ConnectionInterface;
use Modules\CrmCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\CrmCatalog\Application\Contracts\CatalogItemValidatorInterface;
use Modules\CrmCatalog\Application\Repositories\CatalogItemIndustryRepositoryInterface;
use Modules\CrmCatalog\Application\Repositories\CatalogItemRepositoryInterface;
use Modules\CrmCatalog\Infrastructure\Persistence\Models\CatalogItemRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class CreateCatalogItemHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private CatalogAccessGuardInterface $accessGuard,
        private CatalogItemRepositoryInterface $catalogItemRepository,
        private CatalogItemIndustryRepositoryInterface $catalogItemIndustryRepository,
        private CatalogItemValidatorInterface $catalogItemValidator,
    ) {}

    public function handle(CreateCatalogItemCommand $command): CreateCatalogItemResult
    {
        $hqId = $this->accessGuard->assertCanManage($command->actor);
        $input = $command->input;

        $item = $this->connection->transaction(function () use ($command, $hqId, $input): CatalogItemRecord {
            $this->catalogItemValidator->validate($hqId, $input);
            if ($this->catalogItemRepository->codeTaken($hqId, $input->code)) {
                throw new ApiException(ApiErrorCode::Conflict, 409, 'catalog.code_already_exists',
                    ['code' => ['catalog.code_already_exists']]);
            }

            $at = $this->clock->now();
            $item = $this->catalogItemRepository->create([
                ...$input->toAttributes(),
                'hq_id' => $hqId,
                'created_by' => $command->actor->userId,
                'created_at' => $at,
            ]);
            $this->catalogItemIndustryRepository->attach($hqId, $item->catalog_item_id, $input->industryIds, $command->actor->userId, $at);

            // Read back, so the response names the category, persona, sales model and industries.
            return $this->catalogItemRepository->findForTenant($hqId, $item->catalog_item_id) ?? $item;
        }, attempts: 3);

        return new CreateCatalogItemResult($item);
    }
}
