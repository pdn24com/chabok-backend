<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Application\UseCases\ListDocuments;

use Modules\DocumentStore\Application\Contracts\DocumentAccessGuardInterface;
use Modules\DocumentStore\Application\Ports\DocumentResourceDirectoryInterface;
use Modules\DocumentStore\Application\Repositories\DocumentRepositoryInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

/**
 * Serves both the archive page and the documents tab of a record: the tab is this list narrowed to one
 * resource. Narrowing to a record of another tenant answers as if the record did not exist, so the
 * archive cannot be used to discover what a neighbouring tenant owns.
 */
final readonly class ListDocumentsHandler
{
    public function __construct(
        private DocumentAccessGuardInterface $accessGuard,
        private DocumentResourceDirectoryInterface $resourceDirectory,
        private DocumentRepositoryInterface $documentRepository,
    ) {}

    public function handle(ListDocumentsCommand $command): ListDocumentsResult
    {
        $hqId = $this->accessGuard->assertCanRead($command->actor);
        $filters = $command->filters;
        if ($filters->resourceType !== null && $filters->resourceId !== null
            && ! $this->resourceDirectory->exists($hqId, $filters->resourceType, $filters->resourceId)) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return new ListDocumentsResult($this->documentRepository->paginateForTenant($hqId, $filters));
    }
}
