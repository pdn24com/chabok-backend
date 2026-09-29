<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Foundation\Presentation\Http\Middleware\IdempotentCommand;
use Modules\ServiceCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogRecordReaderInterface;
use Modules\ServiceCatalog\Application\Contracts\CommitmentZoneResolverInterface;
use Modules\ServiceCatalog\Application\Dto\CommitmentScheduleDto;
use Modules\ServiceCatalog\Application\Dto\CommitmentScheduleFiltersDto;
use Modules\ServiceCatalog\Application\Mappers\CatalogDraftInput;
use Modules\ServiceCatalog\Application\Mappers\OfferingSelectionInput;
use Modules\ServiceCatalog\Application\UseCases\CloneCatalogDraft\CloneCatalogDraftCommand;
use Modules\ServiceCatalog\Application\UseCases\CloneCatalogDraft\CloneCatalogDraftHandler;
use Modules\ServiceCatalog\Application\UseCases\CloneCommitmentSchedule\CloneCommitmentScheduleCommand;
use Modules\ServiceCatalog\Application\UseCases\CloneCommitmentSchedule\CloneCommitmentScheduleHandler;
use Modules\ServiceCatalog\Application\UseCases\CreateCatalogIdentity\CreateCatalogIdentityCommand;
use Modules\ServiceCatalog\Application\UseCases\CreateCatalogIdentity\CreateCatalogIdentityHandler;
use Modules\ServiceCatalog\Application\UseCases\CreateCommitmentSchedule\CreateCommitmentScheduleCommand;
use Modules\ServiceCatalog\Application\UseCases\CreateCommitmentSchedule\CreateCommitmentScheduleHandler;
use Modules\ServiceCatalog\Application\UseCases\GetCatalogHistory\GetCatalogHistoryCommand;
use Modules\ServiceCatalog\Application\UseCases\GetCatalogHistory\GetCatalogHistoryHandler;
use Modules\ServiceCatalog\Application\UseCases\GetCommitmentScheduleHistory\GetCommitmentScheduleHistoryCommand;
use Modules\ServiceCatalog\Application\UseCases\GetCommitmentScheduleHistory\GetCommitmentScheduleHistoryHandler;
use Modules\ServiceCatalog\Application\UseCases\ListCatalogAuditEvents\ListCatalogAuditEventsCommand;
use Modules\ServiceCatalog\Application\UseCases\ListCatalogAuditEvents\ListCatalogAuditEventsHandler;
use Modules\ServiceCatalog\Application\UseCases\ListCatalogIdentities\ListCatalogIdentitiesCommand;
use Modules\ServiceCatalog\Application\UseCases\ListCatalogIdentities\ListCatalogIdentitiesHandler;
use Modules\ServiceCatalog\Application\UseCases\ListCommitmentSchedules\ListCommitmentSchedulesCommand;
use Modules\ServiceCatalog\Application\UseCases\ListCommitmentSchedules\ListCommitmentSchedulesHandler;
use Modules\ServiceCatalog\Application\UseCases\ListPickupCommitmentWindows\ListPickupCommitmentWindowsCommand;
use Modules\ServiceCatalog\Application\UseCases\ListPickupCommitmentWindows\ListPickupCommitmentWindowsHandler;
use Modules\ServiceCatalog\Application\UseCases\ListPublishedCatalogVersions\ListPublishedCatalogVersionsCommand;
use Modules\ServiceCatalog\Application\UseCases\ListPublishedCatalogVersions\ListPublishedCatalogVersionsHandler;
use Modules\ServiceCatalog\Application\UseCases\ListPublishedCommitmentSchedules\ListPublishedCommitmentSchedulesCommand;
use Modules\ServiceCatalog\Application\UseCases\ListPublishedCommitmentSchedules\ListPublishedCommitmentSchedulesHandler;
use Modules\ServiceCatalog\Application\UseCases\PreviewServiceCommitment\PreviewServiceCommitmentCommand;
use Modules\ServiceCatalog\Application\UseCases\PreviewServiceCommitment\PreviewServiceCommitmentHandler;
use Modules\ServiceCatalog\Application\UseCases\ResolveServiceOfferings\ResolveServiceOfferingsCommand;
use Modules\ServiceCatalog\Application\UseCases\ResolveServiceOfferings\ResolveServiceOfferingsHandler;
use Modules\ServiceCatalog\Application\UseCases\SaveCatalogRecord\SaveCatalogRecordCommand;
use Modules\ServiceCatalog\Application\UseCases\SaveCatalogRecord\SaveCatalogRecordHandler;
use Modules\ServiceCatalog\Application\UseCases\SetCatalogRecordActive\SetCatalogRecordActiveCommand;
use Modules\ServiceCatalog\Application\UseCases\SetCatalogRecordActive\SetCatalogRecordActiveHandler;
use Modules\ServiceCatalog\Application\UseCases\TransitionCatalogVersion\TransitionCatalogVersionCommand;
use Modules\ServiceCatalog\Application\UseCases\TransitionCatalogVersion\TransitionCatalogVersionHandler;
use Modules\ServiceCatalog\Application\UseCases\TransitionCommitmentSchedule\TransitionCommitmentScheduleCommand;
use Modules\ServiceCatalog\Application\UseCases\TransitionCommitmentSchedule\TransitionCommitmentScheduleHandler;
use Modules\ServiceCatalog\Application\UseCases\UpdateCatalogDraft\UpdateCatalogDraftCommand;
use Modules\ServiceCatalog\Application\UseCases\UpdateCatalogDraft\UpdateCatalogDraftHandler;
use Modules\ServiceCatalog\Application\UseCases\UpdateCommitmentSchedule\UpdateCommitmentScheduleCommand;
use Modules\ServiceCatalog\Application\UseCases\UpdateCommitmentSchedule\UpdateCommitmentScheduleHandler;
use Modules\ServiceCatalog\Application\UseCases\ValidateCatalogDraft\ValidateCatalogDraftCommand;
use Modules\ServiceCatalog\Application\UseCases\ValidateCatalogDraft\ValidateCatalogDraftHandler;
use Modules\ServiceCatalog\Application\UseCases\ValidateCommitmentSchedule\ValidateCommitmentScheduleCommand;
use Modules\ServiceCatalog\Application\UseCases\ValidateCommitmentSchedule\ValidateCommitmentScheduleHandler;
use Modules\ServiceCatalog\Application\UseCases\ValidateServiceSelection\ValidateServiceSelectionCommand;
use Modules\ServiceCatalog\Application\UseCases\ValidateServiceSelection\ValidateServiceSelectionHandler;
use Modules\ServiceCatalog\Presentation\Http\Requests\CreateCatalogIdentityRequest;
use Modules\ServiceCatalog\Presentation\Http\Requests\CreateCommitmentScheduleRequest;
use Modules\ServiceCatalog\Presentation\Http\Requests\ListCatalogAuditEventsRequest;
use Modules\ServiceCatalog\Presentation\Http\Requests\ListCatalogIdentitiesRequest;
use Modules\ServiceCatalog\Presentation\Http\Requests\ListCommitmentSchedulesRequest;
use Modules\ServiceCatalog\Presentation\Http\Requests\ListPickupCommitmentWindowsRequest;
use Modules\ServiceCatalog\Presentation\Http\Requests\ListPublishedCatalogVersionsRequest;
use Modules\ServiceCatalog\Presentation\Http\Requests\ListPublishedCommitmentSchedulesRequest;
use Modules\ServiceCatalog\Presentation\Http\Requests\PreviewServiceCommitmentRequest;
use Modules\ServiceCatalog\Presentation\Http\Requests\ResolveServiceOfferingsRequest;
use Modules\ServiceCatalog\Presentation\Http\Requests\SaveCatalogRecordRequest;
use Modules\ServiceCatalog\Presentation\Http\Requests\SetCatalogRecordActiveRequest;
use Modules\ServiceCatalog\Presentation\Http\Requests\UpdateCatalogDraftRequest;
use Modules\ServiceCatalog\Presentation\Http\Requests\UpdateCommitmentScheduleRequest;
use Modules\ServiceCatalog\Presentation\Http\Requests\ValidateServiceSelectionRequest;
use Modules\ServiceCatalog\Presentation\Http\Resources\CatalogRecordResource;
use Modules\ServiceCatalog\Presentation\Http\Resources\CatalogValidationResource;
use Modules\ServiceCatalog\Presentation\Http\Resources\CatalogVersionResource;
use Modules\ServiceCatalog\Presentation\Http\Resources\CommitmentZoneGroupResource;
use Modules\ServiceCatalog\Presentation\Http\Resources\OfferingCommitmentResource;
use Modules\ServiceCatalog\Presentation\Http\Resources\PickupWindowOptionResource;
use Modules\ServiceCatalog\Presentation\Http\Resources\ResolvedServiceOfferingResource;

final class ServiceCatalogController
{
    public function commitmentZoneGroups(Request $request, CatalogAccessGuardInterface $catalogAccessGuard, CommitmentZoneResolverInterface $commitmentZoneResolver): JsonResponse
    {
        $actor = $request->attributes->get('principal');
        $catalogAccessGuard->authorizeRecord($actor);

        return ApiResponder::success($request, CommitmentZoneGroupResource::collection($commitmentZoneResolver->groups((string) $actor->hqId))->resolve($request));
    }

    public function recordDetail(
        Request $request, CatalogRecordReaderInterface $catalogRecordReader,
        string $resource,
        string $identityId,
    ): JsonResponse {
        return ApiResponder::success($request, (new CatalogRecordResource($catalogRecordReader->detail($request->attributes->get('principal'), $resource, $identityId)))->resolve());
    }

    public function saveRecord(
        SaveCatalogRecordRequest $request, IdempotentCommand $idempotency, SaveCatalogRecordHandler $saveCatalogRecordHandler,
        string $resource,
        ?string $identityId = null,
    ): JsonResponse {
        $input = $request->catalogInput();

        return $idempotency->handle($request, fn () => ApiResponder::success($request, (new CatalogRecordResource($saveCatalogRecordHandler->handle(new SaveCatalogRecordCommand($request->attributes->get('principal'), $resource, $identityId, CatalogDraftInput::record($resource, $input), (string) $request->attributes->get('correlation_id')))))->resolve(), status: $identityId === null ? 201 : 200), 'catalog.record.save');
    }

    public function recordStatus(
        SetCatalogRecordActiveRequest $request, SetCatalogRecordActiveHandler $setCatalogRecordActiveHandler,
        string $resource,
        string $identityId,
    ): JsonResponse {
        $input = $request->validated();

        return ApiResponder::success($request, (new CatalogRecordResource($setCatalogRecordActiveHandler->handle(new SetCatalogRecordActiveCommand($request->attributes->get('principal'), $resource, $identityId, $input['active'], $input['expected_version'], (string) $request->attributes->get('correlation_id')))))->resolve());
    }

    public function index(ListCatalogIdentitiesRequest $request, ListCatalogIdentitiesHandler $listCatalogIdentitiesHandler, string $resource): JsonResponse
    {
        $filters = $request->validated();
        $page = $listCatalogIdentitiesHandler->handle(new ListCatalogIdentitiesCommand($request->attributes->get('principal'), $resource, $filters));

        return ApiResponder::success($request, $page->items(), [
            'pagination' => [
                'page' => $page->currentPage(),
                'page_size' => $page->perPage(),
                'total' => $page->total(),
                'total_pages' => $page->lastPage(),
            ],
        ]);
    }

    public function publishedVersions(ListPublishedCatalogVersionsRequest $request, ListPublishedCatalogVersionsHandler $listPublishedCatalogVersionsHandler, string $resource): JsonResponse
    {
        $filters = $request->validated();
        $page = $listPublishedCatalogVersionsHandler->handle(new ListPublishedCatalogVersionsCommand($request->attributes->get('principal'), $resource, $filters));

        return ApiResponder::success($request, $page->items(), [
            'pagination' => [
                'page' => $page->currentPage(),
                'page_size' => $page->perPage(),
                'total' => $page->total(),
                'total_pages' => $page->lastPage(),
            ],
        ]);
    }

    public function audit(ListCatalogAuditEventsRequest $request, ListCatalogAuditEventsHandler $listCatalogAuditEventsHandler): JsonResponse
    {
        $filters = $request->validated();
        $page = $listCatalogAuditEventsHandler->handle(new ListCatalogAuditEventsCommand($request->attributes->get('principal'), $filters));

        return ApiResponder::success($request, $page->items(), [
            'pagination' => [
                'page' => $page->currentPage(),
                'page_size' => $page->perPage(),
                'total' => $page->total(),
                'total_pages' => $page->lastPage(),
            ],
        ]);
    }

    public function store(CreateCatalogIdentityRequest $request, CreateCatalogIdentityHandler $createCatalogIdentityHandler, string $resource): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new CatalogVersionResource($createCatalogIdentityHandler->handle(new CreateCatalogIdentityCommand($request->attributes->get('principal'), $resource, CatalogDraftInput::draft($input), (string) $request->attributes->get('correlation_id')))))->resolve(), status: 201);
    }

    public function clone(
        Request $request, CloneCatalogDraftHandler $cloneCatalogDraftHandler,
        string $resource,
        string $identityId,
    ): JsonResponse {
        return ApiResponder::success($request, (new CatalogVersionResource($cloneCatalogDraftHandler->handle(new CloneCatalogDraftCommand($request->attributes->get('principal'), $resource, $identityId, (string) $request->attributes->get('correlation_id')))))->resolve(), status: 201);
    }

    public function update(
        UpdateCatalogDraftRequest $request, UpdateCatalogDraftHandler $updateCatalogDraftHandler,
        string $resource,
        string $versionId,
    ): JsonResponse {
        $input = $request->validated();
        $expected = (int) $input['expected_version'];
        unset($input['expected_version']);

        return ApiResponder::success($request, (new CatalogVersionResource($updateCatalogDraftHandler->handle(new UpdateCatalogDraftCommand($request->attributes->get('principal'), $resource, $versionId, $expected, CatalogDraftInput::draft($input), (string) $request->attributes->get('correlation_id')))))->resolve());
    }

    public function validateVersion(
        Request $request, ValidateCatalogDraftHandler $validateCatalogDraftHandler,
        string $resource,
        string $versionId,
    ): JsonResponse {
        return ApiResponder::success($request, (new CatalogValidationResource($validateCatalogDraftHandler->handle(new ValidateCatalogDraftCommand($request->attributes->get('principal'), $resource, $versionId))))->resolve());
    }

    public function transition(
        Request $request, TransitionCatalogVersionHandler $transitionCatalogVersionHandler,
        string $resource,
        string $versionId,
        string $action,
    ): JsonResponse {
        return ApiResponder::success($request, (new CatalogVersionResource($transitionCatalogVersionHandler->handle(new TransitionCatalogVersionCommand($request->attributes->get('principal'), $resource, $versionId, $action, (string) $request->attributes->get('correlation_id')))))->resolve());
    }

    public function history(
        Request $request, GetCatalogHistoryHandler $getCatalogHistoryHandler,
        string $resource,
        string $identityId,
    ): JsonResponse {
        return ApiResponder::success($request, $getCatalogHistoryHandler->handle(new GetCatalogHistoryCommand($request->attributes->get('principal'), $resource, $identityId)));
    }

    public function resolve(ResolveServiceOfferingsRequest $request, ResolveServiceOfferingsHandler $resolveServiceOfferingsHandler): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, ResolvedServiceOfferingResource::collection($resolveServiceOfferingsHandler->handle(new ResolveServiceOfferingsCommand($request->attributes->get('principal'), OfferingSelectionInput::fromArray($input))))->resolve());
    }

    public function validateSelection(ValidateServiceSelectionRequest $request, ValidateServiceSelectionHandler $validateServiceSelectionHandler, string $offeringId): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new ResolvedServiceOfferingResource($validateServiceSelectionHandler->handle(new ValidateServiceSelectionCommand($request->attributes->get('principal'), $offeringId, $input['service_offering_version_id'] ?? null, OfferingSelectionInput::fromArray($input)))))->resolve());
    }

    public function commitments(PreviewServiceCommitmentRequest $request, PreviewServiceCommitmentHandler $previewServiceCommitmentHandler, string $offeringId): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new OfferingCommitmentResource($previewServiceCommitmentHandler->handle(new PreviewServiceCommitmentCommand($request->attributes->get('principal'), $offeringId, OfferingSelectionInput::fromArray($input)))))->resolve());
    }

    public function pickupWindows(ListPickupCommitmentWindowsRequest $request, ListPickupCommitmentWindowsHandler $listPickupCommitmentWindowsHandler): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, PickupWindowOptionResource::collection($listPickupCommitmentWindowsHandler->handle(new ListPickupCommitmentWindowsCommand($request->attributes->get('principal'), $this->nodeId($request), $input['at'] ?? null)))->resolve());
    }

    public function schedules(ListCommitmentSchedulesRequest $request, ListCommitmentSchedulesHandler $listCommitmentSchedulesHandler): JsonResponse
    {
        $filters = $request->validated();
        $page = $listCommitmentSchedulesHandler->handle(new ListCommitmentSchedulesCommand($request->attributes->get('principal'), CommitmentScheduleFiltersDto::fromValidated($filters)));

        return ApiResponder::success($request, $page->items(), [
            'pagination' => [
                'page' => $page->currentPage(),
                'page_size' => $page->perPage(),
                'total' => $page->total(),
                'total_pages' => $page->lastPage(),
            ],
        ]);
    }

    public function publishedSchedules(ListPublishedCommitmentSchedulesRequest $request, ListPublishedCommitmentSchedulesHandler $listPublishedCommitmentSchedulesHandler): JsonResponse
    {
        $filters = $request->validated();

        return ApiResponder::success($request, $listPublishedCommitmentSchedulesHandler->handle(new ListPublishedCommitmentSchedulesCommand($request->attributes->get('principal'), (array) ($filters['include_version_ids'] ?? []))));
    }

    public function createSchedule(CreateCommitmentScheduleRequest $request, CreateCommitmentScheduleHandler $createCommitmentScheduleHandler): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new CatalogVersionResource($createCommitmentScheduleHandler->handle(new CreateCommitmentScheduleCommand($request->attributes->get('principal'), CommitmentScheduleDto::fromValidated($input), (string) $request->attributes->get('correlation_id')))))->resolve(), status: 201);
    }

    public function cloneSchedule(Request $request, CloneCommitmentScheduleHandler $cloneCommitmentScheduleHandler, string $identityId): JsonResponse
    {
        return ApiResponder::success($request, (new CatalogVersionResource($cloneCommitmentScheduleHandler->handle(new CloneCommitmentScheduleCommand($request->attributes->get('principal'), $identityId, (string) $request->attributes->get('correlation_id')))))->resolve(), status: 201);
    }

    public function updateSchedule(UpdateCommitmentScheduleRequest $request, UpdateCommitmentScheduleHandler $updateCommitmentScheduleHandler, string $versionId): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new CatalogVersionResource($updateCommitmentScheduleHandler->handle(new UpdateCommitmentScheduleCommand($request->attributes->get('principal'), $versionId, CommitmentScheduleDto::fromValidated($input), (string) $request->attributes->get('correlation_id')))))->resolve());
    }

    public function validateSchedule(Request $request, ValidateCommitmentScheduleHandler $validateCommitmentScheduleHandler, string $versionId): JsonResponse
    {
        return ApiResponder::success($request, (new CatalogValidationResource($validateCommitmentScheduleHandler->handle(new ValidateCommitmentScheduleCommand($request->attributes->get('principal'), $versionId))))->resolve());
    }

    public function transitionSchedule(
        Request $request, TransitionCommitmentScheduleHandler $transitionCommitmentScheduleHandler,
        string $versionId,
        string $action,
    ): JsonResponse {
        return ApiResponder::success($request, (new CatalogVersionResource($transitionCommitmentScheduleHandler->handle(new TransitionCommitmentScheduleCommand($request->attributes->get('principal'), $versionId, $action, (string) $request->attributes->get('correlation_id')))))->resolve());
    }

    public function scheduleHistory(Request $request, GetCommitmentScheduleHistoryHandler $getCommitmentScheduleHistoryHandler, string $identityId): JsonResponse
    {
        return ApiResponder::success($request, $getCommitmentScheduleHistoryHandler->handle(new GetCommitmentScheduleHistoryCommand($request->attributes->get('principal'), $identityId)));
    }

    private function nodeId(Request $request): string
    {
        $nodeId = $request->attributes->get('node_id');
        if (! is_string($nodeId) || $nodeId === '') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'common.active_operational_node_is_required');
        }

        return $nodeId;
    }
}
