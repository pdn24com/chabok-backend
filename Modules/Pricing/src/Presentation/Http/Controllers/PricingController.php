<?php

declare(strict_types=1);

namespace Modules\Pricing\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class PricingController
{
    public function __construct(
        private \Modules\Pricing\Application\UseCases\PrepareMatrixWorkbook\PrepareMatrixWorkbookHandler $matrixWorkbook,
        private \Modules\Pricing\Application\UseCases\ListTariffs\ListTariffsHandler $listTariffs,
        private \Modules\Pricing\Application\UseCases\ListPricingZoneSets\ListPricingZoneSetsHandler $listZoneSets,
        private \Modules\Pricing\Application\UseCases\ListPricingZoneVersionReferences\ListPricingZoneVersionReferencesHandler $listZoneSetVersionReferences,
        private \Modules\Pricing\Application\UseCases\ListServiceTariffReferences\ListServiceTariffReferencesHandler $serviceTariffReferences,
        private \Modules\Pricing\Application\UseCases\ListPricingChargeTypes\ListPricingChargeTypesHandler $listChargeTypes,
        private \Modules\Pricing\Application\UseCases\ListPricingAuditEvents\ListPricingAuditEventsHandler $auditEvents,
        private \Modules\Pricing\Application\UseCases\GetPricingHistory\GetPricingHistoryHandler $history,
        private \Modules\Pricing\Application\UseCases\ClonePricingDraft\ClonePricingDraftHandler $cloneDraft,
        private \Modules\Pricing\Application\UseCases\CreatePricingChargeType\CreatePricingChargeTypeHandler $createChargeType,
        private \Modules\Pricing\Application\UseCases\CreatePricingZoneSet\CreatePricingZoneSetHandler $createZoneSet,
        private \Modules\Pricing\Application\UseCases\UpdatePricingZoneVersion\UpdatePricingZoneVersionHandler $updateZoneVersion,
        private \Modules\Pricing\Application\UseCases\CreateTariff\CreateTariffHandler $createTariff,
        private \Modules\Pricing\Application\UseCases\UpdateTariffVersion\UpdateTariffVersionHandler $updateTariffVersion,
        private \Modules\Pricing\Application\UseCases\ValidateTariff\ValidateTariffHandler $validateTariff,
        private \Modules\Pricing\Application\UseCases\ValidatePricingZoneSet\ValidatePricingZoneSetHandler $validateZoneSet,
        private \Modules\Pricing\Application\UseCases\TransitionPricingVersion\TransitionPricingVersionHandler $transition,
        private \Modules\Pricing\Application\UseCases\CalculatePricingQuote\CalculatePricingQuoteHandler $calculateQuote,
        private \Modules\Pricing\Application\UseCases\SimulateTariffDraft\SimulateTariffDraftHandler $simulateDraft,
        private \Modules\Pricing\Application\UseCases\RejectPricingQuote\RejectPricingQuoteHandler $rejectQuote,
        private \Modules\Pricing\Application\UseCases\AcceptPricingQuote\AcceptPricingQuoteHandler $acceptQuote,
        private \Modules\Pricing\Application\Services\PricingReader $reader,
    )
    {
    }

    public function serviceTariffReferences(Request $request): JsonResponse
    {
        return ApiResponder::success($request, $this->serviceTariffReferences->handle(new \Modules\Pricing\Application\UseCases\ListServiceTariffReferences\ListServiceTariffReferencesCommand($this->principal($request)))->data);
    }

    public function matrixWorkbookSample(\Modules\Pricing\Presentation\Http\Requests\MatrixWorkbookSampleRequest $request): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->matrixWorkbook->handle(new \Modules\Pricing\Application\UseCases\PrepareMatrixWorkbook\PrepareMatrixWorkbookCommand($this->principal($request), $input, true))->data);
    }

    public function matrixWorkbookPreview(\Modules\Pricing\Presentation\Http\Requests\MatrixWorkbookPreviewRequest $request): JsonResponse
    {
        $input = $request->validated();
        $input['matrix'] = [
            'id' => $input['matrix']['id'],
            'service_offering_version_id' => $input['matrix']['service_offering_version_id'] ?? null,
            'service_option_version_id' => $input['matrix']['service_option_version_id'] ?? null,
            'origin_zone_id' => $input['matrix']['origin_zone_id'] ?? null,
            'zone_ids' => $input['matrix']['zone_ids'],
        ];
        return ApiResponder::success($request, $this->matrixWorkbook->handle(new \Modules\Pricing\Application\UseCases\PrepareMatrixWorkbook\PrepareMatrixWorkbookCommand($this->principal($request), $input, false))->data);
    }

    public function quote(\Modules\Pricing\Presentation\Http\Requests\CalculatePricingQuoteRequest $request): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->calculateQuote->handle(new \Modules\Pricing\Application\UseCases\CalculatePricingQuote\CalculatePricingQuoteCommand($this->principal($request), $input, (string) $request->header('Idempotency-Key')))->data, status: 201);
    }

    public function quoteDetail(Request $request, string $quoteId): JsonResponse
    {
        return ApiResponder::success($request, $this->reader->quoteDetail($this->principal($request), $quoteId));
    }

    public function reject(Request $request, string $quoteId): JsonResponse
    {
        return ApiResponder::success($request, $this->rejectQuote->handle(new \Modules\Pricing\Application\UseCases\RejectPricingQuote\RejectPricingQuoteCommand($this->principal($request), $quoteId))->data);
    }

    public function accept(\Modules\Pricing\Presentation\Http\Requests\AcceptPricingQuoteRequest $request): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->acceptQuote->handle(new \Modules\Pricing\Application\UseCases\AcceptPricingQuote\AcceptPricingQuoteCommand($this->principal($request), $input['quote_id'], $input['object_type'], $input['object_id'], $input['input_fingerprint'], (string) $request->header('Idempotency-Key')))->data, status: 201);
    }

    public function tariffs(\Modules\Pricing\Presentation\Http\Requests\ListTariffsRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $page = $this->listTariffs->handle(new \Modules\Pricing\Application\UseCases\ListTariffs\ListTariffsCommand($this->principal($request), $filters))->data;
        return ApiResponder::success($request, $page->items(), ['pagination' => [
            'page' => $page->currentPage(),
            'page_size' => $page->perPage(),
            'total' => $page->total(),
            'total_pages' => $page->lastPage(),
        ]]);
    }

    public function zoneSets(\Modules\Pricing\Presentation\Http\Requests\ListPricingZoneSetsRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $page = $this->listZoneSets->handle(new \Modules\Pricing\Application\UseCases\ListPricingZoneSets\ListPricingZoneSetsCommand($this->principal($request), $filters))->data;
        return ApiResponder::success($request, $page->items(), ['pagination' => [
            'page' => $page->currentPage(),
            'page_size' => $page->perPage(),
            'total' => $page->total(),
            'total_pages' => $page->lastPage(),
        ]]);
    }

    public function zoneSetVersionReferences(\Modules\Pricing\Presentation\Http\Requests\ListPricingZoneVersionReferencesRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $page = $this->listZoneSetVersionReferences->handle(new \Modules\Pricing\Application\UseCases\ListPricingZoneVersionReferences\ListPricingZoneVersionReferencesCommand($this->principal($request), $filters))->data;
        return ApiResponder::success($request, $page->items(), ['pagination' => [
            'page' => $page->currentPage(),
            'page_size' => $page->perPage(),
            'total' => $page->total(),
            'total_pages' => $page->lastPage(),
        ]]);
    }

    public function chargeTypes(Request $request): JsonResponse
    {
        return ApiResponder::success($request, $this->listChargeTypes->handle(new \Modules\Pricing\Application\UseCases\ListPricingChargeTypes\ListPricingChargeTypesCommand($this->principal($request)))->data);
    }

    public function audit(\Modules\Pricing\Presentation\Http\Requests\ListPricingAuditEventsRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $page = $this->auditEvents->handle(new \Modules\Pricing\Application\UseCases\ListPricingAuditEvents\ListPricingAuditEventsCommand($this->principal($request), $filters))->data;
        return ApiResponder::success($request, $page->items(), ['pagination' => [
            'page' => $page->currentPage(),
            'page_size' => $page->perPage(),
            'total' => $page->total(),
            'total_pages' => $page->lastPage(),
        ]]);
    }

    public function history(Request $request, string $kind, string $identityId): JsonResponse
    {
        return ApiResponder::success($request, $this->history->handle(new \Modules\Pricing\Application\UseCases\GetPricingHistory\GetPricingHistoryCommand($this->principal($request), $kind, $identityId))->data);
    }

    public function clone(Request $request, string $kind, string $identityId): JsonResponse
    {
        return ApiResponder::success($request, $this->cloneDraft->handle(new \Modules\Pricing\Application\UseCases\ClonePricingDraft\ClonePricingDraftCommand($this->principal($request), $kind, $identityId, $this->correlation($request)))->data, status: 201);
    }

    public function chargeType(\Modules\Pricing\Presentation\Http\Requests\CreatePricingChargeTypeRequest $request): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->createChargeType->handle(new \Modules\Pricing\Application\UseCases\CreatePricingChargeType\CreatePricingChargeTypeCommand($this->principal($request), $input))->data, status: 201);
    }

    public function zoneSet(\Modules\Pricing\Presentation\Http\Requests\CreatePricingZoneSetRequest $request): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->createZoneSet->handle(new \Modules\Pricing\Application\UseCases\CreatePricingZoneSet\CreatePricingZoneSetCommand($this->principal($request), $input, $this->correlation($request)))->data, status: 201);
    }

    public function updateZone(
        \Modules\Pricing\Presentation\Http\Requests\UpdatePricingZoneVersionRequest $request,
        string $versionId,
    ): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->updateZoneVersion->handle(new \Modules\Pricing\Application\UseCases\UpdatePricingZoneVersion\UpdatePricingZoneVersionCommand($this->principal($request), $versionId, $input))->data);
    }

    public function tariff(\Modules\Pricing\Presentation\Http\Requests\CreateTariffRequest $request): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->createTariff->handle(new \Modules\Pricing\Application\UseCases\CreateTariff\CreateTariffCommand($this->principal($request), $input, $this->correlation($request)))->data, status: 201);
    }

    public function updateTariff(\Modules\Pricing\Presentation\Http\Requests\UpdateTariffVersionRequest $request, string $versionId): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->updateTariffVersion->handle(new \Modules\Pricing\Application\UseCases\UpdateTariffVersion\UpdateTariffVersionCommand($this->principal($request), $versionId, $input))->data);
    }

    public function validateTariff(Request $request, string $versionId): JsonResponse
    {
        return ApiResponder::success($request, $this->validateTariff->handle(new \Modules\Pricing\Application\UseCases\ValidateTariff\ValidateTariffCommand($this->principal($request), $versionId))->data);
    }

    public function validateZoneSet(Request $request, string $versionId): JsonResponse
    {
        return ApiResponder::success($request, $this->validateZoneSet->handle(new \Modules\Pricing\Application\UseCases\ValidatePricingZoneSet\ValidatePricingZoneSetCommand($this->principal($request), $versionId))->data);
    }

    public function transition(Request $request, string $kind, string $versionId, string $action): JsonResponse
    {
        return ApiResponder::success($request, $this->transition->handle(new \Modules\Pricing\Application\UseCases\TransitionPricingVersion\TransitionPricingVersionCommand($this->principal($request), $kind, $versionId, $action, $this->correlation($request)))->data);
    }
    /** @return array<string,mixed> */

    public function simulateDraft(\Modules\Pricing\Presentation\Http\Requests\SimulateTariffDraftRequest $request, string $versionId): JsonResponse
    {
        $input = $request->validated();
        $expected = (int) $input['expected_version'];
        unset($input['expected_version']);
        return ApiResponder::success($request, $this->simulateDraft->handle(new \Modules\Pricing\Application\UseCases\SimulateTariffDraft\SimulateTariffDraftCommand($this->principal($request), $versionId, $input, $expected))->data);
    }

    private function principal(Request $request): AuthenticatedPrincipal
    {
        return $request->attributes->get('principal');
    }

    private function correlation(Request $request): string
    {
        return (string) $request->attributes->get('correlation_id');
    }
}
