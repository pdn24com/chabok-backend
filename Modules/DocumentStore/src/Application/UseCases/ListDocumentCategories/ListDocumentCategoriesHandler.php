<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Application\UseCases\ListDocumentCategories;

use Modules\DocumentStore\Application\Contracts\DocumentAccessGuardInterface;
use Modules\DocumentStore\Application\Repositories\DocumentCategoryRepositoryInterface;

final readonly class ListDocumentCategoriesHandler
{
    public function __construct(
        private DocumentAccessGuardInterface $accessGuard,
        private DocumentCategoryRepositoryInterface $documentCategoryRepository,
    ) {}

    public function handle(ListDocumentCategoriesCommand $command): ListDocumentCategoriesResult
    {
        $hqId = $this->accessGuard->assertCanRead($command->actor);

        return new ListDocumentCategoriesResult($this->documentCategoryRepository->listForTenant($hqId, $command->filters->active));
    }
}
