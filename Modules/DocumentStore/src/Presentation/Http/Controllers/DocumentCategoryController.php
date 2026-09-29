<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\DocumentStore\Application\UseCases\ListDocumentCategories\ListDocumentCategoriesHandler;
use Modules\DocumentStore\Presentation\Http\Requests\ListDocumentCategoriesRequest;
use Modules\DocumentStore\Presentation\Http\Resources\DocumentCategoryResource;
use Modules\DocumentStore\Presentation\Mappers\DocumentCommandMapper;
use Modules\Foundation\Presentation\Http\ApiResponder;

/** The category tree documents are filed under. */
final class DocumentCategoryController
{
    public function index(ListDocumentCategoriesRequest $request, ListDocumentCategoriesHandler $handler): JsonResponse
    {
        $result = $handler->handle(DocumentCommandMapper::categoryListing($request->attributes->get('principal'), $request->validated()));

        // A tenant keeps few categories, so the tree is returned whole rather than by the page.
        return ApiResponder::success($request, DocumentCategoryResource::collection($result->categories)->resolve($request));
    }
}
