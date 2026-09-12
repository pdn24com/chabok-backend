<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Application\ApiResponder;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Pricing\Application\PricingService;

final readonly class PricingController
{
    public function __construct(private PricingService $pricing) {}

    public function quote(Request $request): JsonResponse
    {
        $input = $request->validate($this->quoteRules());
        return ApiResponder::success($request, $this->pricing->calculateQuote($this->principal($request), $input, (string) $request->header('Idempotency-Key')), status: 201);
    }
    public function quoteDetail(Request $request, string $quoteId): JsonResponse { return ApiResponder::success($request, $this->pricing->quoteDetail($this->principal($request), $quoteId)); }
    public function reject(Request $request, string $quoteId): JsonResponse { return ApiResponder::success($request, $this->pricing->rejectQuote($this->principal($request), $quoteId)); }
    public function accept(Request $request): JsonResponse
    {
        $input = $request->validate(['quote_id' => ['required', 'uuid'], 'object_type' => ['required', 'string', 'max:80'], 'object_id' => ['required', 'uuid'], 'input_fingerprint' => ['required', 'size:64']]);
        return ApiResponder::success($request, $this->pricing->acceptQuote($this->principal($request), $input['quote_id'], $input['object_type'], $input['object_id'], $input['input_fingerprint'], (string) $request->header('Idempotency-Key')), status: 201);
    }
    public function tariffs(Request $request): JsonResponse
    {
        $filters = $request->validate(['page' => ['integer', 'min:1'], 'page_size' => ['integer', 'min:1', 'max:100'], 'search' => ['nullable', 'string', 'max:120']]); $page = $this->pricing->listTariffs($this->principal($request), $filters);
        return ApiResponder::success($request, $page->items(), ['pagination' => ['page' => $page->currentPage(), 'page_size' => $page->perPage(), 'total' => $page->total(), 'total_pages' => $page->lastPage()]]);
    }
    public function zoneSets(Request $request): JsonResponse
    {
        $filters = $request->validate(['page' => ['integer', 'min:1'], 'page_size' => ['integer', 'min:1', 'max:100'], 'search' => ['nullable', 'string', 'max:120']]); $page = $this->pricing->listZoneSets($this->principal($request), $filters);
        return ApiResponder::success($request, $page->items(), ['pagination' => ['page' => $page->currentPage(), 'page_size' => $page->perPage(), 'total' => $page->total(), 'total_pages' => $page->lastPage()]]);
    }
    public function zoneSetVersionReferences(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'page' => ['integer', 'min:1'],
            'page_size' => ['integer', 'min:1', 'max:100'],
            'search' => ['nullable', 'string', 'max:120'],
            'include_version_id' => ['nullable', 'uuid'],
        ]);
        $page = $this->pricing->listZoneSetVersionReferences($this->principal($request), $filters);

        return ApiResponder::success($request, $page->items(), ['pagination' => [
            'page' => $page->currentPage(),
            'page_size' => $page->perPage(),
            'total' => $page->total(),
            'total_pages' => $page->lastPage(),
        ]]);
    }
    public function chargeTypes(Request $request): JsonResponse { return ApiResponder::success($request, $this->pricing->listChargeTypes($this->principal($request))); }
    public function audit(Request $request): JsonResponse { $filters = $request->validate(['page' => ['integer', 'min:1'], 'page_size' => ['integer', 'min:1', 'max:100'], 'target_id' => ['nullable', 'uuid']]); $page = $this->pricing->auditEvents($this->principal($request), $filters); return ApiResponder::success($request, $page->items(), ['pagination' => ['page' => $page->currentPage(), 'page_size' => $page->perPage(), 'total' => $page->total(), 'total_pages' => $page->lastPage()]]); }
    public function history(Request $request, string $kind, string $identityId): JsonResponse { return ApiResponder::success($request, $this->pricing->history($this->principal($request), $kind, $identityId)); }
    public function clone(Request $request, string $kind, string $identityId): JsonResponse { return ApiResponder::success($request, $this->pricing->cloneDraft($this->principal($request), $kind, $identityId, $this->correlation($request)), status: 201); }
    public function chargeType(Request $request): JsonResponse { $input = $request->validate(['code' => ['required', 'in:BASE_FREIGHT,PICKUP_FEE,DELIVERY_FEE,REMOTE_AREA,EXTRA_PARCEL,INSURANCE,INSURANCE_FEE,COD_FEE,FUEL_SURCHARGE,DISCOUNT,TAX,COMMISSION'], 'category' => ['required', 'in:BASE,SURCHARGE,DISCOUNT,TAX,COMMISSION'], 'accounting_mapping_key' => ['required', 'string', 'max:120'], 'taxable' => ['boolean'], 'active' => ['boolean']]); return ApiResponder::success($request, $this->pricing->createChargeType($this->principal($request), $input), status: 201); }
    public function zoneSet(Request $request): JsonResponse { $input = $request->validate($this->zoneSetRules(true)); return ApiResponder::success($request, $this->pricing->createZoneSet($this->principal($request), $input, $this->correlation($request)), status: 201); }
    public function updateZone(Request $request, string $versionId): JsonResponse { $input = $request->validate($this->zoneSetRules(false) + ['expected_version' => ['required', 'integer', 'min:1']]); return ApiResponder::success($request, $this->pricing->updateZoneVersion($this->principal($request), $versionId, $input)); }
    public function tariff(Request $request): JsonResponse { $input = $request->validate($this->tariffRules(true)); return ApiResponder::success($request, $this->pricing->createTariff($this->principal($request), $input, $this->correlation($request)), status: 201); }
    public function updateTariff(Request $request, string $versionId): JsonResponse { $input = $request->validate($this->tariffRules(false) + ['expected_version' => ['required', 'integer', 'min:1']]); return ApiResponder::success($request, $this->pricing->updateTariffVersion($this->principal($request), $versionId, $input)); }
    public function validateTariff(Request $request, string $versionId): JsonResponse { return ApiResponder::success($request, $this->pricing->validateTariff($this->principal($request), $versionId)); }
    public function validateZoneSet(Request $request, string $versionId): JsonResponse { return ApiResponder::success($request, $this->pricing->validateZoneSet($this->principal($request), $versionId)); }
    public function transition(Request $request, string $kind, string $versionId, string $action): JsonResponse { return ApiResponder::success($request, $this->pricing->transition($this->principal($request), $kind, $versionId, $action, $this->correlation($request))); }

    /** @return array<string,mixed> */
    private function quoteRules(): array { return ['purpose' => ['sometimes', 'in:SALES,PURCHASE,COMMISSION,INTERNAL_TRANSFER'], 'channel' => ['sometimes', 'in:BRANCH,VENDOR,DRIVER,HQ,API,TRACKING,LEGACY'], 'as_of_timestamp' => ['nullable', 'date'], 'service_offering_id' => ['required', 'uuid'], 'service_offering_version_id' => ['nullable', 'uuid'], 'selected_option_version_ids' => ['array'], 'selected_option_version_ids.*' => ['uuid'], 'pickup_service_date' => ['nullable', 'date_format:Y-m-d'], 'pickup_window_code' => ['nullable', 'string', 'max:80'], 'delivery_window_code' => ['nullable', 'string', 'max:80'], 'sender' => ['required', 'array'], 'sender.latitude' => ['nullable', 'numeric', 'between:-90,90'], 'sender.longitude' => ['nullable', 'numeric', 'between:-180,180'], 'sender.city_id' => ['required', 'uuid'], 'sender.postal_code' => ['sometimes', 'nullable', 'string', 'max:32'], 'receiver' => ['required', 'array'], 'receiver.latitude' => ['nullable', 'numeric', 'between:-90,90'], 'receiver.longitude' => ['nullable', 'numeric', 'between:-180,180'], 'receiver.city_id' => ['required', 'uuid'], 'receiver.postal_code' => ['sometimes', 'nullable', 'string', 'max:32'], 'parcels' => ['array'], 'parcels.*.content_description' => ['nullable', 'string', 'max:500'], 'parcels.*.weight_kg' => ['required', 'numeric', 'gt:0'], 'parcels.*.width_cm' => ['nullable', 'numeric', 'gt:0'], 'parcels.*.length_cm' => ['nullable', 'numeric', 'gt:0'], 'parcels.*.height_cm' => ['nullable', 'numeric', 'gt:0'], 'weight_kg' => ['nullable', 'numeric', 'gt:0'], 'width_cm' => ['nullable', 'numeric', 'gt:0'], 'length_cm' => ['nullable', 'numeric', 'gt:0'], 'height_cm' => ['nullable', 'numeric', 'gt:0'], 'declared_value_amount' => ['required', 'integer', 'min:0'], 'insurance_enabled' => ['required', 'boolean'], 'cod_enabled' => ['required', 'boolean'], 'cod_amount' => ['nullable', 'integer', 'min:0']]; }
    /** @return array<string,mixed> */
    private function zoneSetRules(bool $create): array
    {
        $rules = ['zones.*.members.*.geometry' => ['required_if:zones.*.members.*.member_type,POLYGON', 'nullable', 'array'], 'valid_from' => ['nullable', 'date'], 'valid_to' => ['nullable', 'date'], 'zones' => ['required', 'array', 'min:1'], 'zones.*.pricing_zone_id' => ['nullable', 'uuid', 'distinct'], 'zones.*.rank' => ['nullable', 'integer', 'min:1', 'distinct'], 'zones.*.code' => ['required', 'regex:/^[A-Z][A-Z0-9_]{1,79}$/'], 'zones.*.title' => ['required', 'string', 'max:200'], 'zones.*.remote_area' => ['sometimes', 'boolean'], 'zones.*.members' => ['required', 'array', 'min:1'], 'zones.*.members.*.member_type' => ['required', 'in:EXPLICIT_OVERRIDE,POSTAL_RANGE,CITY,PROVINCE,POLYGON'], 'zones.*.members.*.reference_value' => ['required_unless:zones.*.members.*.member_type,CITY,PROVINCE,POLYGON', 'nullable', 'string', 'max:200'], 'zones.*.members.*.city_id' => ['required_if:zones.*.members.*.member_type,CITY', 'nullable', 'uuid'], 'zones.*.members.*.province_id' => ['required_if:zones.*.members.*.member_type,PROVINCE', 'nullable', 'uuid'], 'zones.*.members.*.range_end' => ['nullable', 'string', 'max:200']];
        if ($create) $rules += ['code' => ['required', 'regex:/^[A-Z][A-Z0-9_]{1,79}$/'], 'purpose' => ['required', 'in:SALES,PURCHASE,COMMISSION,INTERNAL_TRANSFER'], 'title' => ['required', 'string', 'max:200']];
        return $rules;
    }
    /** @return array<string,mixed> */
    private function tariffRules(bool $create): array
    {
        $rules = $this->matrixRules() + ['zone_set_version_id' => ['required', 'uuid'], 'valid_from' => ['nullable', 'date'], 'valid_to' => ['nullable', 'date'], 'volumetric_divisor' => ['numeric', 'gt:0'], 'weight_rounding_step_kg' => ['numeric', 'gt:0'], 'rounding_mode' => ['in:HALF_UP,HALF_EVEN,CEILING,FLOOR,STEP_UP'], 'rules' => ['present', 'array', 'max:10000'], 'rules.*.service_offering_version_id' => ['required', 'uuid'], 'rules.*.service_option_version_id' => ['nullable', 'uuid'], 'rules.*.charge_type_id' => ['required', 'uuid'], 'rules.*.origin_zone_id' => ['nullable', 'uuid'], 'rules.*.destination_zone_id' => ['nullable', 'uuid'], 'rules.*.calculation_method' => ['required', 'in:FIXED,PER_UNIT,SLAB,TIERED,PERCENT,MIN_MAX'], 'rules.*.basis' => ['sometimes', 'in:FLAT,SHIPMENT,ACTUAL_WEIGHT,BILLABLE_WEIGHT,PARCEL_COUNT,DECLARED_VALUE,COD_AMOUNT'], 'rules.*.range_from' => ['nullable', 'numeric', 'min:0'], 'rules.*.range_to' => ['nullable', 'numeric', 'gt:rules.*.range_from'], 'rules.*.fixed_amount' => ['nullable', 'integer', 'min:0'], 'rules.*.unit_rate' => ['nullable', 'numeric', 'min:0'], 'rules.*.percentage_bps' => ['nullable', 'integer', 'min:0', 'max:10000'], 'rules.*.minimum_amount' => ['nullable', 'integer', 'min:0'], 'rules.*.maximum_amount' => ['nullable', 'integer', 'min:0'], 'rules.*.amount_rounding_mode' => ['sometimes', 'in:NONE,CEIL,FLOOR,HALF_UP'], 'rules.*.amount_rounding_step' => ['nullable', 'integer', 'min:1'], 'rules.*.basis_charge_codes' => ['nullable', 'array'], 'rules.*.basis_charge_codes.*' => ['string', 'max:80'], 'rules.*.conditions' => ['nullable', 'array'], 'rules.*.priority' => ['sometimes', 'integer', 'min:1', 'max:65535']];
        if ($create) $rules += ['code' => ['required', 'regex:/^[A-Z][A-Z0-9_]{1,79}$/'], 'purpose' => ['required', 'in:SALES,PURCHASE,COMMISSION,INTERNAL_TRANSFER'], 'currency' => ['required', 'in:IRR'], 'title' => ['required', 'string', 'max:200'], 'scope_type' => ['in:PLATFORM,TENANT,SEGMENT,CUSTOMER,CONTRACT'], 'scope_value' => ['nullable', 'string', 'max:120'], 'priority' => ['integer', 'min:1', 'max:65535']];
        return $rules;
    }
    private function matrixRules(): array
    {
        return [
            'zone_policy' => ['sometimes', 'in:DIRECTIONAL,HIGHER_ZONE_RANK'],
            'freight_matrices' => ['sometimes', 'array', 'max:100'],
            'freight_matrices.*.id' => ['required', 'uuid', 'distinct'],
            'freight_matrices.*.service_offering_version_id' => ['required', 'uuid'],
            'freight_matrices.*.service_option_version_id' => ['nullable', 'uuid'],
            'freight_matrices.*.origin_zone_id' => ['nullable', 'uuid'],
            'freight_matrices.*.zone_ids' => ['required', 'array', 'min:1', 'max:100'],
            'freight_matrices.*.zone_ids.*' => ['uuid'],
            'freight_matrices.*.bands' => ['present', 'array', 'max:500'],
            'freight_matrices.*.bands.*.id' => ['required', 'uuid'],
            'freight_matrices.*.bands.*.from' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            'freight_matrices.*.bands.*.to' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'freight_matrices.*.bands.*.cells' => ['present', 'array', 'max:100'],
            'freight_matrices.*.bands.*.cells.*.id' => ['required', 'uuid'],
            'freight_matrices.*.bands.*.cells.*.zone_id' => ['required', 'uuid'],
            'freight_matrices.*.bands.*.cells.*.state' => ['required', 'in:EMPTY,RATE,UNCOVERED'],
            'freight_matrices.*.bands.*.cells.*.amount' => ['nullable', 'integer', 'min:0', 'max:9007199254740991'],
            'freight_matrices.*.linear_tail' => ['nullable', 'array:id,from,step_kg,cells'],
            'freight_matrices.*.linear_tail.id' => ['required_with:freight_matrices.*.linear_tail', 'uuid'],
            'freight_matrices.*.linear_tail.from' => ['required_with:freight_matrices.*.linear_tail', 'numeric', 'min:0', 'decimal:0,4'],
            'freight_matrices.*.linear_tail.step_kg' => ['required_with:freight_matrices.*.linear_tail', 'numeric', 'gt:0', 'max:99999999', 'decimal:0,4'],
            'freight_matrices.*.linear_tail.cells' => ['required_with:freight_matrices.*.linear_tail', 'array', 'max:100'],
            'freight_matrices.*.linear_tail.cells.*.id' => ['required', 'uuid'],
            'freight_matrices.*.linear_tail.cells.*.zone_id' => ['required', 'uuid'],
            'freight_matrices.*.linear_tail.cells.*.state' => ['required', 'in:EMPTY,RATE,UNCOVERED'],
            'freight_matrices.*.linear_tail.cells.*.amount' => ['nullable', 'integer', 'min:0', 'max:9007199254740991'],
            'rules.*.matrix_cell_id' => ['nullable', 'uuid'],
            'rules.*.taxable' => ['nullable', 'boolean'],
        ];
    }

    public function simulateDraft(Request $request, string $versionId): JsonResponse
    {
        $input = $request->validate($this->quoteRules() + ['expected_version' => ['required', 'integer', 'min:1']]);
        $expected = (int) $input['expected_version'];
        unset($input['expected_version']);
        return ApiResponder::success($request, $this->pricing->simulateDraft($this->principal($request), $versionId, $input, $expected));
    }

    private function principal(Request $request): AuthenticatedPrincipal { return $request->attributes->get('principal'); }
    private function correlation(Request $request): string { return (string) $request->attributes->get('correlation_id'); }
}
