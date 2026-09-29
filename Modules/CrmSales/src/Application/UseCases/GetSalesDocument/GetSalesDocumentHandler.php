<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\UseCases\GetSalesDocument;

use Modules\CrmSales\Application\Contracts\SalesDocumentAccessGuardInterface;
use Modules\CrmSales\Application\Repositories\SalesDocumentRepositoryInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class GetSalesDocumentHandler
{
    public function __construct(
        private SalesDocumentAccessGuardInterface $accessGuard,
        private SalesDocumentRepositoryInterface $salesDocumentRepository,
    ) {}

    public function handle(GetSalesDocumentCommand $command): GetSalesDocumentResult
    {
        $hqId = $this->accessGuard->assertCanRead($command->actor);

        // A document of another tenant is indistinguishable from one that does not exist.
        $document = $this->salesDocumentRepository->findForTenant($hqId, $command->documentId)
            ?? throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');

        return new GetSalesDocumentResult($document);
    }
}
