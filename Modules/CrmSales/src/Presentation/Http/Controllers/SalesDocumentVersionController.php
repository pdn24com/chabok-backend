<?php

declare(strict_types=1);

namespace Modules\CrmSales\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CrmSales\Application\UseCases\TransitionSalesDocumentVersion\TransitionSalesDocumentVersionHandler;
use Modules\CrmSales\Domain\Enums\SalesDocumentTransition;
use Modules\CrmSales\Presentation\Http\Resources\SalesDocumentResource;
use Modules\CrmSales\Presentation\Mappers\SalesCommandMapper;
use Modules\Foundation\Presentation\Http\ApiResponder;

/** What an operator can do to the revision a sales document currently shows. */
final class SalesDocumentVersionController
{
    public function issue(Request $request, TransitionSalesDocumentVersionHandler $handler, string $versionId): JsonResponse
    {
        return $this->move($request, $handler, $versionId, SalesDocumentTransition::ISSUE);
    }

    public function accept(Request $request, TransitionSalesDocumentVersionHandler $handler, string $versionId): JsonResponse
    {
        return $this->move($request, $handler, $versionId, SalesDocumentTransition::ACCEPT);
    }

    public function cancel(Request $request, TransitionSalesDocumentVersionHandler $handler, string $versionId): JsonResponse
    {
        return $this->move($request, $handler, $versionId, SalesDocumentTransition::CANCEL);
    }

    private function move(Request $request, TransitionSalesDocumentVersionHandler $handler, string $versionId, SalesDocumentTransition $transition): JsonResponse
    {
        $result = $handler->handle(SalesCommandMapper::transition($request->attributes->get('principal'), $versionId, $transition));

        return ApiResponder::success($request, new SalesDocumentResource($result->document));
    }
}
