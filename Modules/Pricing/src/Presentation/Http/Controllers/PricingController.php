<?php

declare(strict_types=1);

namespace Modules\Pricing\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Pricing\Application\Contracts\PricingReaderInterface;
use Modules\Pricing\Application\Dto\MatrixDefinitionDto;
use Modules\Pricing\Application\Dto\MatrixWorkbookPreviewDto;
use Modules\Pricing\Application\Dto\MatrixWorkbookSampleDto;
use Modules\Pricing\Application\Dto\PricingChargeTypeDto;
use Modules\Pricing\Application\Dto\PricingFiltersDto;
use Modules\Pricing\Application\Dto\PricingZoneSetDto;
use Modules\Pricing\Application\Dto\PricingZoneVersionSearchDto;
use Modules\Pricing\Application\Dto\TariffDraftDto;
use Modules\Pricing\Application\Mappers\QuoteInputMapper;
use Modules\Pricing\Application\UseCases\AcceptPricingQuote\AcceptPricingQuoteCommand;
use Modules\Pricing\Application\UseCases\AcceptPricingQuote\AcceptPricingQuoteHandler;
use Modules\Pricing\Application\UseCases\CalculatePricingQuote\CalculatePricingQuoteCommand;
use Modules\Pricing\Application\UseCases\CalculatePricingQuote\CalculatePricingQuoteHandler;
use Modules\Pricing\Application\UseCases\ClonePricingDraft\ClonePricingDraftCommand;
use Modules\Pricing\Application\UseCases\ClonePricingDraft\ClonePricingDraftHandler;
use Modules\Pricing\Application\UseCases\CreatePricingChargeType\CreatePricingChargeTypeCommand;
use Modules\Pricing\Application\UseCases\CreatePricingChargeType\CreatePricingChargeTypeHandler;
use Modules\Pricing\Application\UseCases\CreatePricingZoneSet\CreatePricingZoneSetCommand;
use Modules\Pricing\Application\UseCases\CreatePricingZoneSet\CreatePricingZoneSetHandler;
use Modules\Pricing\Application\UseCases\CreateTariff\CreateTariffCommand;
use Modules\Pricing\Application\UseCases\CreateTariff\CreateTariffHandler;
use Modules\Pricing\Application\UseCases\GetPricingHistory\GetPricingHistoryCommand;
use Modules\Pricing\Application\UseCases\GetPricingHistory\GetPricingHistoryHandler;
use Modules\Pricing\Application\UseCases\ListPricingAuditEvents\ListPricingAuditEventsCommand;
use Modules\Pricing\Application\UseCases\ListPricingAuditEvents\ListPricingAuditEventsHandler;
use Modules\Pricing\Application\UseCases\ListPricingChargeTypes\ListPricingChargeTypesCommand;
use Modules\Pricing\Application\UseCases\ListPricingChargeTypes\ListPricingChargeTypesHandler;
use Modules\Pricing\Application\UseCases\ListPricingZoneSets\ListPricingZoneSetsCommand;
use Modules\Pricing\Application\UseCases\ListPricingZoneSets\ListPricingZoneSetsHandler;
use Modules\Pricing\Application\UseCases\ListPricingZoneVersionReferences\ListPricingZoneVersionReferencesCommand;
use Modules\Pricing\Application\UseCases\ListPricingZoneVersionReferences\ListPricingZoneVersionReferencesHandler;
use Modules\Pricing\Application\UseCases\ListServiceTariffReferences\ListServiceTariffReferencesCommand;
use Modules\Pricing\Application\UseCases\ListServiceTariffReferences\ListServiceTariffReferencesHandler;
use Modules\Pricing\Application\UseCases\ListTariffs\ListTariffsCommand;
use Modules\Pricing\Application\UseCases\ListTariffs\ListTariffsHandler;
use Modules\Pricing\Application\UseCases\PrepareMatrixWorkbook\PrepareMatrixWorkbookCommand;
use Modules\Pricing\Application\UseCases\PrepareMatrixWorkbook\PrepareMatrixWorkbookHandler;
use Modules\Pricing\Application\UseCases\RejectPricingQuote\RejectPricingQuoteCommand;
use Modules\Pricing\Application\UseCases\RejectPricingQuote\RejectPricingQuoteHandler;
use Modules\Pricing\Application\UseCases\SimulateTariffDraft\SimulateTariffDraftCommand;
use Modules\Pricing\Application\UseCases\SimulateTariffDraft\SimulateTariffDraftHandler;
use Modules\Pricing\Application\UseCases\TransitionPricingVersion\TransitionPricingVersionCommand;
use Modules\Pricing\Application\UseCases\TransitionPricingVersion\TransitionPricingVersionHandler;
use Modules\Pricing\Application\UseCases\UpdatePricingZoneVersion\UpdatePricingZoneVersionCommand;
use Modules\Pricing\Application\UseCases\UpdatePricingZoneVersion\UpdatePricingZoneVersionHandler;
use Modules\Pricing\Application\UseCases\UpdateTariffVersion\UpdateTariffVersionCommand;
use Modules\Pricing\Application\UseCases\UpdateTariffVersion\UpdateTariffVersionHandler;
use Modules\Pricing\Application\UseCases\ValidatePricingZoneSet\ValidatePricingZoneSetCommand;
use Modules\Pricing\Application\UseCases\ValidatePricingZoneSet\ValidatePricingZoneSetHandler;
use Modules\Pricing\Application\UseCases\ValidateTariff\ValidateTariffCommand;
use Modules\Pricing\Application\UseCases\ValidateTariff\ValidateTariffHandler;
use Modules\Pricing\Domain\Enums\PricingResource;
use Modules\Pricing\Domain\Enums\PricingTransition;
use Modules\Pricing\Presentation\Http\Requests\AcceptPricingQuoteRequest;
use Modules\Pricing\Presentation\Http\Requests\CalculatePricingQuoteRequest;
use Modules\Pricing\Presentation\Http\Requests\CreatePricingChargeTypeRequest;
use Modules\Pricing\Presentation\Http\Requests\CreatePricingZoneSetRequest;
use Modules\Pricing\Presentation\Http\Requests\CreateTariffRequest;
use Modules\Pricing\Presentation\Http\Requests\ListPricingAuditEventsRequest;
use Modules\Pricing\Presentation\Http\Requests\ListPricingZoneSetsRequest;
use Modules\Pricing\Presentation\Http\Requests\ListPricingZoneVersionReferencesRequest;
use Modules\Pricing\Presentation\Http\Requests\ListTariffsRequest;
use Modules\Pricing\Presentation\Http\Requests\MatrixWorkbookPreviewRequest;
use Modules\Pricing\Presentation\Http\Requests\MatrixWorkbookSampleRequest;
use Modules\Pricing\Presentation\Http\Requests\SimulateTariffDraftRequest;
use Modules\Pricing\Presentation\Http\Requests\UpdatePricingZoneVersionRequest;
use Modules\Pricing\Presentation\Http\Requests\UpdateTariffVersionRequest;
use Modules\Pricing\Presentation\Http\Resources\MatrixWorkbookPreviewResource;
use Modules\Pricing\Presentation\Http\Resources\PricingHistoryResource;
use Modules\Pricing\Presentation\Http\Resources\PricingQuoteResource;
use Modules\Pricing\Presentation\Http\Resources\PricingSimulationResource;
use Modules\Pricing\Presentation\Http\Resources\PricingSnapshotResource;
use Modules\Pricing\Presentation\Http\Resources\PricingValidationResource;
use Modules\Pricing\Presentation\Http\Resources\PricingVersionResource;
use Modules\Pricing\Presentation\Http\Resources\PricingZoneVersionReferenceResource;
use Modules\Pricing\Presentation\Http\Resources\WorkbookFileResource;

final class PricingController
{
    public function serviceTariffReferences(Request $request, ListServiceTariffReferencesHandler $listServiceTariffReferencesHandler): JsonResponse
    {
        return ApiResponder::success($request, $listServiceTariffReferencesHandler->handle(new ListServiceTariffReferencesCommand($request->attributes->get('principal'))));
    }

    public function matrixWorkbookSample(MatrixWorkbookSampleRequest $request, PrepareMatrixWorkbookHandler $prepareMatrixWorkbookHandler): JsonResponse
    {
        $input = new MatrixWorkbookSampleDto($request->validated('zone_titles'));
        $file = $prepareMatrixWorkbookHandler->handle(new PrepareMatrixWorkbookCommand($request->attributes->get('principal'), $input));

        return ApiResponder::success($request, (new WorkbookFileResource($file))->resolve($request));
    }

    public function matrixWorkbookPreview(MatrixWorkbookPreviewRequest $request, PrepareMatrixWorkbookHandler $prepareMatrixWorkbookHandler): JsonResponse
    {
        $validated = $request->validated();
        $matrix = $validated['matrix'];
        $input = new MatrixWorkbookPreviewDto($validated['content_base64'], new MatrixDefinitionDto(id: $matrix['id'], serviceOfferingVersionId: $matrix['service_offering_version_id'] ?? null, serviceOptionVersionId: $matrix['service_option_version_id'] ?? null, originZoneId: $matrix['origin_zone_id'] ?? null, zoneIds: $matrix['zone_ids']));
        $preview = $prepareMatrixWorkbookHandler->handle(new PrepareMatrixWorkbookCommand($request->attributes->get('principal'), $input));

        return ApiResponder::success($request, (new MatrixWorkbookPreviewResource($preview))->resolve($request));
    }

    public function quote(CalculatePricingQuoteRequest $request, CalculatePricingQuoteHandler $calculatePricingQuoteHandler): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new PricingQuoteResource($calculatePricingQuoteHandler->handle(new CalculatePricingQuoteCommand($request->attributes->get('principal'), QuoteInputMapper::quote($input), (string) $request->header('Idempotency-Key')))))->resolve(), status: 201);
    }

    public function quoteDetail(Request $request, PricingReaderInterface $pricingReader, string $quoteId): JsonResponse
    {
        return ApiResponder::success($request, (new PricingQuoteResource($pricingReader->quoteDetail($request->attributes->get('principal'), $quoteId)))->resolve());
    }

    public function reject(Request $request, RejectPricingQuoteHandler $rejectPricingQuoteHandler, string $quoteId): JsonResponse
    {
        return ApiResponder::success($request, (new PricingQuoteResource($rejectPricingQuoteHandler->handle(new RejectPricingQuoteCommand($request->attributes->get('principal'), $quoteId))))->resolve());
    }

    public function accept(AcceptPricingQuoteRequest $request, AcceptPricingQuoteHandler $acceptPricingQuoteHandler): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new PricingSnapshotResource($acceptPricingQuoteHandler->handle(new AcceptPricingQuoteCommand($request->attributes->get('principal'), $input['quote_id'], $input['object_type'], $input['object_id'], $input['input_fingerprint'], (string) $request->header('Idempotency-Key')))))->resolve(), status: 201);
    }

    public function tariffs(ListTariffsRequest $request, ListTariffsHandler $listTariffsHandler): JsonResponse
    {
        $filters = $request->validated();
        $page = $listTariffsHandler->handle(new ListTariffsCommand($request->attributes->get('principal'), PricingFiltersDto::fromInput($filters)));

        return ApiResponder::success($request, $page->items(), [
            'pagination' => [
                'page' => $page->currentPage(),
                'page_size' => $page->perPage(),
                'total' => $page->total(),
                'total_pages' => $page->lastPage(),
            ],
        ]);
    }

    public function zoneSets(ListPricingZoneSetsRequest $request, ListPricingZoneSetsHandler $listPricingZoneSetsHandler): JsonResponse
    {
        $filters = $request->validated();
        $page = $listPricingZoneSetsHandler->handle(new ListPricingZoneSetsCommand($request->attributes->get('principal'), PricingFiltersDto::fromInput($filters)));

        return ApiResponder::success($request, $page->items(), [
            'pagination' => [
                'page' => $page->currentPage(),
                'page_size' => $page->perPage(),
                'total' => $page->total(),
                'total_pages' => $page->lastPage(),
            ],
        ]);
    }

    public function zoneSetVersionReferences(ListPricingZoneVersionReferencesRequest $request, ListPricingZoneVersionReferencesHandler $listPricingZoneVersionReferencesHandler): JsonResponse
    {
        $filters = $request->validated();
        $page = $listPricingZoneVersionReferencesHandler->handle(new ListPricingZoneVersionReferencesCommand($request->attributes->get('principal'), new PricingZoneVersionSearchDto(search: $filters['search'] ?? '', includeVersionId: $filters['include_version_id'] ?? null, page: (int) ($filters['page'] ?? 1), pageSize: (int) ($filters['page_size'] ?? 50))));

        return ApiResponder::success($request, PricingZoneVersionReferenceResource::collection($page->items())->resolve($request), [
            'pagination' => [
                'page' => $page->currentPage(),
                'page_size' => $page->perPage(),
                'total' => $page->total(),
                'total_pages' => $page->lastPage(),
            ],
        ]);
    }

    public function chargeTypes(Request $request, ListPricingChargeTypesHandler $listPricingChargeTypesHandler): JsonResponse
    {
        return ApiResponder::success($request, $listPricingChargeTypesHandler->handle(new ListPricingChargeTypesCommand($request->attributes->get('principal'))));
    }

    public function audit(ListPricingAuditEventsRequest $request, ListPricingAuditEventsHandler $listPricingAuditEventsHandler): JsonResponse
    {
        $filters = $request->validated();
        $page = $listPricingAuditEventsHandler->handle(new ListPricingAuditEventsCommand($request->attributes->get('principal'), PricingFiltersDto::fromInput($filters)));

        return ApiResponder::success($request, $page->items(), [
            'pagination' => [
                'page' => $page->currentPage(),
                'page_size' => $page->perPage(),
                'total' => $page->total(),
                'total_pages' => $page->lastPage(),
            ],
        ]);
    }

    public function history(
        Request $request, GetPricingHistoryHandler $getPricingHistoryHandler,
        string $kind,
        string $identityId,
    ): JsonResponse {
        return ApiResponder::success($request, PricingHistoryResource::collection($getPricingHistoryHandler->handle(new GetPricingHistoryCommand($request->attributes->get('principal'), PricingResource::fromPath($kind), $identityId)))->resolve($request));
    }

    public function clone(
        Request $request, ClonePricingDraftHandler $clonePricingDraftHandler,
        string $kind,
        string $identityId,
    ): JsonResponse {
        return ApiResponder::success($request, (new PricingVersionResource($clonePricingDraftHandler->handle(new ClonePricingDraftCommand($request->attributes->get('principal'), PricingResource::fromPath($kind), $identityId, (string) $request->attributes->get('correlation_id')))))->resolve($request), status: 201);
    }

    public function chargeType(CreatePricingChargeTypeRequest $request, CreatePricingChargeTypeHandler $createPricingChargeTypeHandler): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, $createPricingChargeTypeHandler->handle(new CreatePricingChargeTypeCommand($request->attributes->get('principal'), PricingChargeTypeDto::fromInput($input))), status: 201);
    }

    public function zoneSet(CreatePricingZoneSetRequest $request, CreatePricingZoneSetHandler $createPricingZoneSetHandler): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new PricingVersionResource($createPricingZoneSetHandler->handle(new CreatePricingZoneSetCommand($request->attributes->get('principal'), PricingZoneSetDto::fromInput($input), (string) $request->attributes->get('correlation_id')))))->resolve($request), status: 201);
    }

    public function updateZone(UpdatePricingZoneVersionRequest $request, UpdatePricingZoneVersionHandler $updatePricingZoneVersionHandler, string $versionId): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new PricingVersionResource($updatePricingZoneVersionHandler->handle(new UpdatePricingZoneVersionCommand($request->attributes->get('principal'), $versionId, PricingZoneSetDto::fromInput($input)))))->resolve($request));
    }

    public function tariff(CreateTariffRequest $request, CreateTariffHandler $createTariffHandler): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new PricingVersionResource($createTariffHandler->handle(new CreateTariffCommand($request->attributes->get('principal'), TariffDraftDto::fromInput($input), (string) $request->attributes->get('correlation_id')))))->resolve($request), status: 201);
    }

    public function updateTariff(UpdateTariffVersionRequest $request, UpdateTariffVersionHandler $updateTariffVersionHandler, string $versionId): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new PricingVersionResource($updateTariffVersionHandler->handle(new UpdateTariffVersionCommand($request->attributes->get('principal'), $versionId, TariffDraftDto::fromInput($input)))))->resolve($request));
    }

    public function validateTariff(Request $request, ValidateTariffHandler $validateTariffHandler, string $versionId): JsonResponse
    {
        return ApiResponder::success($request, (new PricingValidationResource($validateTariffHandler->handle(new ValidateTariffCommand($request->attributes->get('principal'), $versionId))))->resolve($request));
    }

    public function validateZoneSet(Request $request, ValidatePricingZoneSetHandler $validatePricingZoneSetHandler, string $versionId): JsonResponse
    {
        return ApiResponder::success($request, (new PricingValidationResource($validatePricingZoneSetHandler->handle(new ValidatePricingZoneSetCommand($request->attributes->get('principal'), $versionId))))->resolve($request));
    }

    public function transition(
        Request $request, TransitionPricingVersionHandler $transitionPricingVersionHandler,
        string $kind,
        string $versionId,
        string $action,
    ): JsonResponse {
        return ApiResponder::success($request, (new PricingVersionResource($transitionPricingVersionHandler->handle(new TransitionPricingVersionCommand($request->attributes->get('principal'), PricingResource::fromPath($kind), $versionId, PricingTransition::fromInput($action), (string) $request->attributes->get('correlation_id')))))->resolve($request));
    }

    public function simulateDraft(SimulateTariffDraftRequest $request, SimulateTariffDraftHandler $simulateTariffDraftHandler, string $versionId): JsonResponse
    {
        $input = $request->validated();
        $expected = (int) $input['expected_version'];
        unset($input['expected_version']);

        return ApiResponder::success($request, (new PricingSimulationResource($simulateTariffDraftHandler->handle(new SimulateTariffDraftCommand($request->attributes->get('principal'), $versionId, QuoteInputMapper::quote($input), $expected))))->resolve());
    }
}
