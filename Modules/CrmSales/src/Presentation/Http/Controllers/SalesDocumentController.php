<?php

declare(strict_types=1);

namespace Modules\CrmSales\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\CrmSales\Application\UseCases\CreateSalesDocument\CreateSalesDocumentHandler;
use Modules\CrmSales\Application\UseCases\CreateSalesDocumentVersion\CreateSalesDocumentVersionHandler;
use Modules\CrmSales\Application\UseCases\GetSalesDocument\GetSalesDocumentHandler;
use Modules\CrmSales\Application\UseCases\ListSalesDocuments\ListSalesDocumentsHandler;
use Modules\CrmSales\Presentation\Http\Requests\CreateSalesDocumentRequest;
use Modules\CrmSales\Presentation\Http\Requests\CreateSalesDocumentVersionRequest;
use Modules\CrmSales\Presentation\Http\Requests\ListSalesDocumentsRequest;
use Modules\CrmSales\Presentation\Http\Resources\SalesDocumentListResource;
use Modules\CrmSales\Presentation\Http\Resources\SalesDocumentResource;
use Modules\CrmSales\Presentation\Mappers\SalesCommandMapper;
use Modules\Foundation\Presentation\Http\ApiResponder;

/** Estimates, proposals and proformas: the documents a price is quoted on, apart from any invoice. */
final class SalesDocumentController
{
    public function index(ListSalesDocumentsRequest $request, ListSalesDocumentsHandler $handler): JsonResponse
    {
        $documents = $handler->handle(SalesCommandMapper::listing($request->attributes->get('principal'), $request->validated()));

        return ApiResponder::success($request, $documents
            ->map(fn ($document): array => (new SalesDocumentListResource($document))->resolve($request))
            ->all());
    }

    public function show(Request $request, GetSalesDocumentHandler $handler, string $documentId): JsonResponse
    {
        $document = $handler->handle(SalesCommandMapper::document($request->attributes->get('principal'), $documentId));

        return ApiResponder::success($request, new SalesDocumentResource($document));
    }

    public function store(CreateSalesDocumentRequest $request, CreateSalesDocumentHandler $handler): JsonResponse
    {
        $result = $handler->handle(SalesCommandMapper::draft($request->attributes->get('principal'), $request->validated()));

        return ApiResponder::success($request, new SalesDocumentResource($result->document), status: 201);
    }

    public function storeVersion(CreateSalesDocumentVersionRequest $request, CreateSalesDocumentVersionHandler $handler, string $documentId): JsonResponse
    {
        $result = $handler->handle(SalesCommandMapper::revision($request->attributes->get('principal'), $documentId, $request->validated()));

        return ApiResponder::success($request, new SalesDocumentResource($result->document), status: 201);
    }
}
