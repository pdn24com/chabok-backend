<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\DocumentStore\Application\UseCases\ArchiveDocument\ArchiveDocumentHandler;
use Modules\DocumentStore\Application\UseCases\LinkDocumentToResource\LinkDocumentToResourceHandler;
use Modules\DocumentStore\Application\UseCases\ListDocuments\ListDocumentsHandler;
use Modules\DocumentStore\Application\UseCases\RegisterDocument\RegisterDocumentHandler;
use Modules\DocumentStore\Application\UseCases\UpdateDocument\UpdateDocumentHandler;
use Modules\DocumentStore\Presentation\Http\Requests\LinkDocumentRequest;
use Modules\DocumentStore\Presentation\Http\Requests\ListDocumentsRequest;
use Modules\DocumentStore\Presentation\Http\Requests\RegisterDocumentRequest;
use Modules\DocumentStore\Presentation\Http\Requests\UpdateDocumentRequest;
use Modules\DocumentStore\Presentation\Http\Resources\DocumentLinkResource;
use Modules\DocumentStore\Presentation\Http\Resources\DocumentResource;
use Modules\DocumentStore\Presentation\Mappers\DocumentCommandMapper;
use Modules\Foundation\Presentation\Http\ApiResponder;

/**
 * The document archive and what hangs off it. Narrowed by the resource filters, the same list is the
 * documents tab of a customer file or of an opportunity.
 */
final class DocumentController
{
    public function index(ListDocumentsRequest $request, ListDocumentsHandler $handler): JsonResponse
    {
        $result = $handler->handle(DocumentCommandMapper::listing($request->attributes->get('principal'), $request->validated()));

        return ApiResponder::paginated($request, $result->documents, fn ($document): array => (new DocumentResource($document))->resolve($request));
    }

    public function store(RegisterDocumentRequest $request, RegisterDocumentHandler $handler): JsonResponse
    {
        $result = $handler->handle(DocumentCommandMapper::draft($request->attributes->get('principal'), $request->validated()));

        return ApiResponder::success($request, new DocumentResource($result->document), status: 201);
    }

    public function update(UpdateDocumentRequest $request, UpdateDocumentHandler $handler, string $documentId): JsonResponse
    {
        $result = $handler->handle(DocumentCommandMapper::changes($request->attributes->get('principal'), $documentId, $request->validated()));

        return ApiResponder::success($request, new DocumentResource($result->document));
    }

    public function archive(Request $request, ArchiveDocumentHandler $handler, string $documentId): JsonResponse
    {
        $result = $handler->handle(DocumentCommandMapper::archival($request->attributes->get('principal'), $documentId));

        return ApiResponder::success($request, new DocumentResource($result->document));
    }

    public function link(LinkDocumentRequest $request, LinkDocumentToResourceHandler $handler, string $documentId): JsonResponse
    {
        $result = $handler->handle(DocumentCommandMapper::attachment($request->attributes->get('principal'), $documentId, $request->validated()));

        return ApiResponder::success($request, new DocumentLinkResource($result->link), status: 201);
    }
}
