<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ServiceCatalogController
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\UseCases\ListCatalogIdentities\ListCatalogIdentitiesHandler $cataloglistIdentities,
        private \Modules\ServiceCatalog\Application\UseCases\ListPublishedCatalogVersions\ListPublishedCatalogVersionsHandler $cataloglistPublishedVersions,
        private \Modules\ServiceCatalog\Application\UseCases\ListCatalogAuditEvents\ListCatalogAuditEventsHandler $catalogauditEvents,
        private \Modules\ServiceCatalog\Application\UseCases\CreateCatalogIdentity\CreateCatalogIdentityHandler $catalogcreateIdentity,
        private \Modules\ServiceCatalog\Application\UseCases\CloneCatalogDraft\CloneCatalogDraftHandler $catalogcloneDraft,
        private \Modules\ServiceCatalog\Application\UseCases\UpdateCatalogDraft\UpdateCatalogDraftHandler $catalogupdateDraft,
        private \Modules\ServiceCatalog\Application\UseCases\ValidateCatalogDraft\ValidateCatalogDraftHandler $catalogvalidateDraft,
        private \Modules\ServiceCatalog\Application\UseCases\TransitionCatalogVersion\TransitionCatalogVersionHandler $catalogtransition,
        private \Modules\ServiceCatalog\Application\UseCases\GetCatalogHistory\GetCatalogHistoryHandler $cataloghistory,
        private \Modules\ServiceCatalog\Application\UseCases\ResolveServiceOfferings\ResolveServiceOfferingsHandler $catalogresolve,
        private \Modules\ServiceCatalog\Application\UseCases\ValidateServiceSelection\ValidateServiceSelectionHandler $catalogvalidateSelection,
        private \Modules\ServiceCatalog\Application\UseCases\PreviewServiceCommitment\PreviewServiceCommitmentHandler $catalogcommitmentPreview,
        private \Modules\ServiceCatalog\Application\UseCases\ListCommitmentSchedules\ListCommitmentSchedulesHandler $scheduleslist,
        private \Modules\ServiceCatalog\Application\UseCases\ListPublishedCommitmentSchedules\ListPublishedCommitmentSchedulesHandler $schedulespublished,
        private \Modules\ServiceCatalog\Application\UseCases\CreateCommitmentSchedule\CreateCommitmentScheduleHandler $schedulescreate,
        private \Modules\ServiceCatalog\Application\UseCases\CloneCommitmentSchedule\CloneCommitmentScheduleHandler $schedulescloneDraft,
        private \Modules\ServiceCatalog\Application\UseCases\UpdateCommitmentSchedule\UpdateCommitmentScheduleHandler $schedulesupdate,
        private \Modules\ServiceCatalog\Application\UseCases\ValidateCommitmentSchedule\ValidateCommitmentScheduleHandler $schedulesvalidate,
        private \Modules\ServiceCatalog\Application\UseCases\TransitionCommitmentSchedule\TransitionCommitmentScheduleHandler $schedulestransition,
        private \Modules\ServiceCatalog\Application\UseCases\GetCommitmentScheduleHistory\GetCommitmentScheduleHistoryHandler $scheduleshistory,
        private \Modules\ServiceCatalog\Application\UseCases\ListPickupCommitmentWindows\ListPickupCommitmentWindowsHandler $schedulespickupWindows,
        private \Modules\ServiceCatalog\Application\UseCases\SaveCatalogRecord\SaveCatalogRecordHandler $recordssave,
        private \Modules\ServiceCatalog\Application\UseCases\SetCatalogRecordActive\SetCatalogRecordActiveHandler $recordssetActive,
        private \Modules\ServiceCatalog\Application\Services\CatalogRecordAccessGuard $recordAccess,
        private \Modules\ServiceCatalog\Application\Services\CatalogRecordReader $recordReader,
        private \Modules\ServiceCatalog\Application\Contracts\CommitmentZoneResolver $zones,
        private \Modules\Foundation\Presentation\Http\Middleware\IdempotentCommand $idempotency,
    )
    {
    }

    public function commitmentZoneGroups(Request $request): JsonResponse
    {
        $actor = $this->principal($request);
        $this->recordAccess->authorize($actor);
        return ApiResponder::success($request, $this->zones->groups((string) $actor->hqId));
    }

    public function recordDetail(Request $request, string $resource, string $identityId): JsonResponse
    {
        return ApiResponder::success($request, $this->recordReader->detail($this->principal($request), $resource, $identityId));
    }

    public function saveRecord(
        \Modules\ServiceCatalog\Presentation\Http\Requests\SaveCatalogRecordRequest $request,
        string $resource,
        ?string $identityId = null,
    ): JsonResponse
    {
        $input = $request->catalogInput();
        return $this->idempotency->handle($request, fn() => ApiResponder::success($request, $this->recordssave->handle(new \Modules\ServiceCatalog\Application\UseCases\SaveCatalogRecord\SaveCatalogRecordCommand($this->principal($request), $resource, $identityId, $input, $this->correlation($request)))->data, status: $identityId === null ? 201 : 200), 'catalog.record.save');
    }

    public function recordStatus(
        \Modules\ServiceCatalog\Presentation\Http\Requests\SetCatalogRecordActiveRequest $request,
        string $resource,
        string $identityId,
    ): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->recordssetActive->handle(new \Modules\ServiceCatalog\Application\UseCases\SetCatalogRecordActive\SetCatalogRecordActiveCommand($this->principal($request), $resource, $identityId, $input['active'], $input['expected_version'], $this->correlation($request)))->data);
    }

    public function index(
        \Modules\ServiceCatalog\Presentation\Http\Requests\ListCatalogIdentitiesRequest $request,
        string $resource,
    ): JsonResponse
    {
        $filters = $request->validated();
        $page = $this->cataloglistIdentities->handle(new \Modules\ServiceCatalog\Application\UseCases\ListCatalogIdentities\ListCatalogIdentitiesCommand($this->principal($request), $resource, $filters))->data;
        return ApiResponder::success($request, $page->items(), ['pagination' => [
            'page' => $page->currentPage(),
            'page_size' => $page->perPage(),
            'total' => $page->total(),
            'total_pages' => $page->lastPage(),
        ]]);
    }

    public function publishedVersions(
        \Modules\ServiceCatalog\Presentation\Http\Requests\ListPublishedCatalogVersionsRequest $request,
        string $resource,
    ): JsonResponse
    {
        $filters = $request->validated();
        $page = $this->cataloglistPublishedVersions->handle(new \Modules\ServiceCatalog\Application\UseCases\ListPublishedCatalogVersions\ListPublishedCatalogVersionsCommand($this->principal($request), $resource, $filters))->data;
        return ApiResponder::success($request, $page->items(), ['pagination' => [
            'page' => $page->currentPage(),
            'page_size' => $page->perPage(),
            'total' => $page->total(),
            'total_pages' => $page->lastPage(),
        ]]);
    }

    public function audit(\Modules\ServiceCatalog\Presentation\Http\Requests\ListCatalogAuditEventsRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $page = $this->catalogauditEvents->handle(new \Modules\ServiceCatalog\Application\UseCases\ListCatalogAuditEvents\ListCatalogAuditEventsCommand($this->principal($request), $filters))->data;
        return ApiResponder::success($request, $page->items(), ['pagination' => [
            'page' => $page->currentPage(),
            'page_size' => $page->perPage(),
            'total' => $page->total(),
            'total_pages' => $page->lastPage(),
        ]]);
    }

    public function store(
        \Modules\ServiceCatalog\Presentation\Http\Requests\CreateCatalogIdentityRequest $request,
        string $resource,
    ): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->catalogcreateIdentity->handle(new \Modules\ServiceCatalog\Application\UseCases\CreateCatalogIdentity\CreateCatalogIdentityCommand($this->principal($request), $resource, $input, $this->correlation($request)))->data, status: 201);
    }

    public function clone(Request $request, string $resource, string $identityId): JsonResponse
    {
        return ApiResponder::success($request, $this->catalogcloneDraft->handle(new \Modules\ServiceCatalog\Application\UseCases\CloneCatalogDraft\CloneCatalogDraftCommand($this->principal($request), $resource, $identityId, $this->correlation($request)))->data, status: 201);
    }

    public function update(
        \Modules\ServiceCatalog\Presentation\Http\Requests\UpdateCatalogDraftRequest $request,
        string $resource,
        string $versionId,
    ): JsonResponse
    {
        $input = $request->validated();
        $expected = (int) $input['expected_version'];
        unset($input['expected_version']);
        return ApiResponder::success($request, $this->catalogupdateDraft->handle(new \Modules\ServiceCatalog\Application\UseCases\UpdateCatalogDraft\UpdateCatalogDraftCommand($this->principal($request), $resource, $versionId, $expected, $input, $this->correlation($request)))->data);
    }

    public function validateVersion(Request $request, string $resource, string $versionId): JsonResponse
    {
        return ApiResponder::success($request, $this->catalogvalidateDraft->handle(new \Modules\ServiceCatalog\Application\UseCases\ValidateCatalogDraft\ValidateCatalogDraftCommand($this->principal($request), $resource, $versionId))->data);
    }

    public function transition(Request $request, string $resource, string $versionId, string $action): JsonResponse
    {
        return ApiResponder::success($request, $this->catalogtransition->handle(new \Modules\ServiceCatalog\Application\UseCases\TransitionCatalogVersion\TransitionCatalogVersionCommand($this->principal($request), $resource, $versionId, $action, $this->correlation($request)))->data);
    }

    public function history(Request $request, string $resource, string $identityId): JsonResponse
    {
        return ApiResponder::success($request, $this->cataloghistory->handle(new \Modules\ServiceCatalog\Application\UseCases\GetCatalogHistory\GetCatalogHistoryCommand($this->principal($request), $resource, $identityId))->data);
    }

    public function resolve(\Modules\ServiceCatalog\Presentation\Http\Requests\ResolveServiceOfferingsRequest $request): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->catalogresolve->handle(new \Modules\ServiceCatalog\Application\UseCases\ResolveServiceOfferings\ResolveServiceOfferingsCommand($this->principal($request), $input))->data);
    }

    public function validateSelection(
        \Modules\ServiceCatalog\Presentation\Http\Requests\ValidateServiceSelectionRequest $request,
        string $offeringId,
    ): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->catalogvalidateSelection->handle(new \Modules\ServiceCatalog\Application\UseCases\ValidateServiceSelection\ValidateServiceSelectionCommand($this->principal($request), $offeringId, $input['service_offering_version_id'] ?? null, $input))->data);
    }

    public function commitments(
        \Modules\ServiceCatalog\Presentation\Http\Requests\PreviewServiceCommitmentRequest $request,
        string $offeringId,
    ): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->catalogcommitmentPreview->handle(new \Modules\ServiceCatalog\Application\UseCases\PreviewServiceCommitment\PreviewServiceCommitmentCommand($this->principal($request), $offeringId, $input))->data);
    }

    public function pickupWindows(\Modules\ServiceCatalog\Presentation\Http\Requests\ListPickupCommitmentWindowsRequest $request): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->schedulespickupWindows->handle(new \Modules\ServiceCatalog\Application\UseCases\ListPickupCommitmentWindows\ListPickupCommitmentWindowsCommand($this->principal($request), $this->nodeId($request), $input['at'] ?? null))->data);
    }

    public function schedules(\Modules\ServiceCatalog\Presentation\Http\Requests\ListCommitmentSchedulesRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $page = $this->scheduleslist->handle(new \Modules\ServiceCatalog\Application\UseCases\ListCommitmentSchedules\ListCommitmentSchedulesCommand($this->principal($request), $filters))->data;
        return ApiResponder::success($request, $page->items(), ['pagination' => [
            'page' => $page->currentPage(),
            'page_size' => $page->perPage(),
            'total' => $page->total(),
            'total_pages' => $page->lastPage(),
        ]]);
    }

    public function publishedSchedules(\Modules\ServiceCatalog\Presentation\Http\Requests\ListPublishedCommitmentSchedulesRequest $request): JsonResponse
    {
        $filters = $request->validated();
        return ApiResponder::success($request, $this->schedulespublished->handle(new \Modules\ServiceCatalog\Application\UseCases\ListPublishedCommitmentSchedules\ListPublishedCommitmentSchedulesCommand($this->principal($request), (array) ($filters['include_version_ids'] ?? [])))->data);
    }

    public function createSchedule(\Modules\ServiceCatalog\Presentation\Http\Requests\CreateCommitmentScheduleRequest $request): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->schedulescreate->handle(new \Modules\ServiceCatalog\Application\UseCases\CreateCommitmentSchedule\CreateCommitmentScheduleCommand($this->principal($request), $input, $this->correlation($request)))->data, status: 201);
    }

    public function cloneSchedule(Request $request, string $identityId): JsonResponse
    {
        return ApiResponder::success($request, $this->schedulescloneDraft->handle(new \Modules\ServiceCatalog\Application\UseCases\CloneCommitmentSchedule\CloneCommitmentScheduleCommand($this->principal($request), $identityId, $this->correlation($request)))->data, status: 201);
    }

    public function updateSchedule(
        \Modules\ServiceCatalog\Presentation\Http\Requests\UpdateCommitmentScheduleRequest $request,
        string $versionId,
    ): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->schedulesupdate->handle(new \Modules\ServiceCatalog\Application\UseCases\UpdateCommitmentSchedule\UpdateCommitmentScheduleCommand($this->principal($request), $versionId, $input, $this->correlation($request)))->data);
    }

    public function validateSchedule(Request $request, string $versionId): JsonResponse
    {
        return ApiResponder::success($request, $this->schedulesvalidate->handle(new \Modules\ServiceCatalog\Application\UseCases\ValidateCommitmentSchedule\ValidateCommitmentScheduleCommand($this->principal($request), $versionId))->data);
    }

    public function transitionSchedule(Request $request, string $versionId, string $action): JsonResponse
    {
        return ApiResponder::success($request, $this->schedulestransition->handle(new \Modules\ServiceCatalog\Application\UseCases\TransitionCommitmentSchedule\TransitionCommitmentScheduleCommand($this->principal($request), $versionId, $action, $this->correlation($request)))->data);
    }

    public function scheduleHistory(Request $request, string $identityId): JsonResponse
    {
        return ApiResponder::success($request, $this->scheduleshistory->handle(new \Modules\ServiceCatalog\Application\UseCases\GetCommitmentScheduleHistory\GetCommitmentScheduleHistoryCommand($this->principal($request), $identityId))->data);
    }
    /** @return array<string, mixed> */

    private function principal(Request $request): AuthenticatedPrincipal
    {
        return $request->attributes->get('principal');
    }

    private function correlation(Request $request): string
    {
        return (string) $request->attributes->get('correlation_id');
    }

    private function nodeId(Request $request): string
    {
        $nodeId = $request->attributes->get('node_id');
        if (!is_string($nodeId) || $nodeId === '') {
            throw new \Modules\Foundation\Domain\ApiException(\Modules\Foundation\Domain\ApiErrorCode::ValidationError, 422, 'An active operational node is required.');
        }
        return $nodeId;
    }
}
