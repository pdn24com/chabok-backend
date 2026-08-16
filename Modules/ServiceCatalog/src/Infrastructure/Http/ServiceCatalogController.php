<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Application\ApiResponder;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\ServiceCatalog\Application\ServiceCatalogService;
use Modules\ServiceCatalog\Application\CommitmentScheduleService;

final readonly class ServiceCatalogController
{
    public function __construct(
        private ServiceCatalogService $catalog,
        private CommitmentScheduleService $schedules,
    ) {}

    public function index(Request $request, string $resource): JsonResponse
    {
        $filters = $request->validate(['page' => ['integer', 'min:1'], 'page_size' => ['integer', 'min:1', 'max:100'], 'search' => ['nullable', 'string', 'max:120'], 'status' => ['nullable', 'string', 'max:30']]);
        $page = $this->catalog->listIdentities($this->principal($request), $resource, $filters);
        return ApiResponder::success($request, $page->items(), ['pagination' => ['page' => $page->currentPage(), 'page_size' => $page->perPage(), 'total' => $page->total(), 'total_pages' => $page->lastPage()]]);
    }

    public function publishedVersions(Request $request, string $resource): JsonResponse
    {
        $filters = $request->validate(['page' => ['integer', 'min:1'], 'page_size' => ['integer', 'min:1', 'max:100'], 'search' => ['nullable', 'string', 'max:120']]);
        $page = $this->catalog->listPublishedVersions($this->principal($request), $resource, $filters);

        return ApiResponder::success($request, $page->items(), ['pagination' => ['page' => $page->currentPage(), 'page_size' => $page->perPage(), 'total' => $page->total(), 'total_pages' => $page->lastPage()]]);
    }

    public function audit(Request $request): JsonResponse
    {
        $filters = $request->validate(['page' => ['integer', 'min:1'], 'page_size' => ['integer', 'min:1', 'max:100'], 'target_id' => ['nullable', 'uuid']]); $page = $this->catalog->auditEvents($this->principal($request), $filters);
        return ApiResponder::success($request, $page->items(), ['pagination' => ['page' => $page->currentPage(), 'page_size' => $page->perPage(), 'total' => $page->total(), 'total_pages' => $page->lastPage()]]);
    }

    public function store(Request $request, string $resource): JsonResponse
    {
        $input = $request->validate($this->draftRules($resource, true));
        return ApiResponder::success($request, $this->catalog->createIdentity($this->principal($request), $resource, $input, $this->correlation($request)), status: 201);
    }

    public function clone(Request $request, string $resource, string $identityId): JsonResponse
    {
        return ApiResponder::success($request, $this->catalog->cloneDraft($this->principal($request), $resource, $identityId, $this->correlation($request)), status: 201);
    }

    public function update(Request $request, string $resource, string $versionId): JsonResponse
    {
        $input = $request->validate($this->draftRules($resource, false) + ['expected_version' => ['required', 'integer', 'min:1']]);
        $expected = (int) $input['expected_version']; unset($input['expected_version']);
        return ApiResponder::success($request, $this->catalog->updateDraft($this->principal($request), $resource, $versionId, $expected, $input, $this->correlation($request)));
    }

    public function validateVersion(Request $request, string $resource, string $versionId): JsonResponse
    {
        return ApiResponder::success($request, $this->catalog->validateDraft($this->principal($request), $resource, $versionId));
    }

    public function transition(Request $request, string $resource, string $versionId, string $action): JsonResponse
    {
        return ApiResponder::success($request, $this->catalog->transition($this->principal($request), $resource, $versionId, $action, $this->correlation($request)));
    }

    public function history(Request $request, string $resource, string $identityId): JsonResponse
    {
        return ApiResponder::success($request, $this->catalog->history($this->principal($request), $resource, $identityId));
    }

    public function resolve(Request $request): JsonResponse
    {
        $input = $request->validate(['channel' => ['required', 'in:BRANCH,VENDOR,DRIVER,HQ,API,TRACKING,LEGACY'], 'as_of_timestamp' => ['nullable', 'date'], 'acceptance_at' => ['nullable', 'date'], 'pickup_window_code' => ['nullable', 'string', 'max:80'], 'pickup_service_date' => ['nullable', 'date_format:Y-m-d'], 'delivery_window_code' => ['nullable', 'string', 'max:80'], 'sender' => ['required', 'array'], 'receiver' => ['required', 'array'], 'parcels' => ['nullable', 'array'], 'selected_option_version_ids' => ['nullable', 'array'], 'selected_option_version_ids.*' => ['uuid']]);
        return ApiResponder::success($request, $this->catalog->resolve($this->principal($request), $input));
    }

    public function validateSelection(Request $request, string $offeringId): JsonResponse
    {
        $input = $request->validate(['service_offering_version_id' => ['nullable', 'uuid'], 'channel' => ['required', 'in:BRANCH,VENDOR,DRIVER,HQ,API,TRACKING,LEGACY'], 'as_of_timestamp' => ['nullable', 'date'], 'acceptance_at' => ['nullable', 'date'], 'pickup_window_code' => ['nullable', 'string', 'max:80'], 'pickup_service_date' => ['nullable', 'date_format:Y-m-d'], 'delivery_window_code' => ['nullable', 'string', 'max:80'], 'sender' => ['required', 'array'], 'receiver' => ['required', 'array'], 'parcels' => ['nullable', 'array'], 'selected_option_version_ids' => ['nullable', 'array'], 'selected_option_version_ids.*' => ['uuid']]);
        return ApiResponder::success($request, $this->catalog->validateSelection($this->principal($request), $offeringId, $input['service_offering_version_id'] ?? null, $input));
    }

    public function commitments(Request $request, string $offeringId): JsonResponse
    {
        $input = $request->validate(['service_offering_version_id' => ['nullable', 'uuid'], 'channel' => ['required', 'string'], 'acceptance_at' => ['nullable', 'date'], 'pickup_window_code' => ['nullable', 'string', 'max:80'], 'pickup_service_date' => ['nullable', 'date_format:Y-m-d'], 'delivery_window_code' => ['nullable', 'string', 'max:80'], 'sender' => ['required', 'array'], 'receiver' => ['required', 'array'], 'parcels' => ['nullable', 'array']]);
        return ApiResponder::success($request, $this->catalog->commitmentPreview($this->principal($request), $offeringId, $input));
    }

    public function pickupWindows(Request $request): JsonResponse
    {
        $input = $request->validate(['at' => ['nullable', 'date']]);
        return ApiResponder::success($request, $this->schedules->pickupWindows($this->principal($request), $this->nodeId($request), $input['at'] ?? null));
    }

    public function schedules(Request $request): JsonResponse
    {
        $filters = $request->validate(['page' => ['integer', 'min:1'], 'page_size' => ['integer', 'min:1', 'max:100'], 'search' => ['nullable', 'string', 'max:120']]);
        $page = $this->schedules->list($this->principal($request), $filters);
        return ApiResponder::success($request, $page->items(), ['pagination' => ['page' => $page->currentPage(), 'page_size' => $page->perPage(), 'total' => $page->total(), 'total_pages' => $page->lastPage()]]);
    }

    public function publishedSchedules(Request $request): JsonResponse
    {
        return ApiResponder::success($request, $this->schedules->published($this->principal($request)));
    }

    public function createSchedule(Request $request): JsonResponse
    {
        $input = $request->validate($this->scheduleRules(true));
        return ApiResponder::success($request, $this->schedules->create($this->principal($request), $input, $this->correlation($request)), status: 201);
    }

    public function cloneSchedule(Request $request, string $identityId): JsonResponse
    {
        return ApiResponder::success($request, $this->schedules->cloneDraft($this->principal($request), $identityId, $this->correlation($request)), status: 201);
    }

    public function updateSchedule(Request $request, string $versionId): JsonResponse
    {
        $input = $request->validate($this->scheduleRules(false) + ['expected_version' => ['required', 'integer', 'min:1']]);
        return ApiResponder::success($request, $this->schedules->update($this->principal($request), $versionId, $input, $this->correlation($request)));
    }

    public function validateSchedule(Request $request, string $versionId): JsonResponse
    {
        return ApiResponder::success($request, $this->schedules->validate($this->principal($request), $versionId));
    }

    public function transitionSchedule(Request $request, string $versionId, string $action): JsonResponse
    {
        return ApiResponder::success($request, $this->schedules->transition($this->principal($request), $versionId, $action, $this->correlation($request)));
    }

    public function scheduleHistory(Request $request, string $identityId): JsonResponse
    {
        return ApiResponder::success($request, $this->schedules->history($this->principal($request), $identityId));
    }

    /** @return array<string, mixed> */
    private function draftRules(string $resource, bool $creating): array
    {
        $rules = [
            'labels' => ['required', 'array'], 'description' => ['nullable', 'string', 'max:4000'],
            'valid_from' => ['nullable', 'date'], 'valid_to' => ['nullable', 'date'],
        ];
        if ($creating) $rules['code'] = ['required', 'regex:/^[A-Z][A-Z0-9_]{1,79}$/'];
        if ($resource !== 'offerings') return $rules + ['definition' => ['sometimes', 'array']];
        return $rules + [
            'service_type_version_id' => ['required', 'uuid'], 'shipping_method_version_id' => ['required', 'uuid'],
            'sla_policy' => ['required', 'array'], 'availability_summary' => ['nullable', 'array'],
            'option_rules' => ['array'], 'eligibility_rules' => ['array'], 'coverage_references' => ['array'], 'availability_bindings' => ['required', 'array', 'min:1'],
            'option_rules.*.service_option_version_id' => ['required', 'uuid'], 'option_rules.*.compatibility' => ['required', 'in:ALLOWED,REQUIRED,FORBIDDEN,CONDITIONAL'], 'option_rules.*.condition' => ['nullable', 'array'],
            'eligibility_rules.*.dimension' => ['required', 'in:GEOGRAPHY,PHYSICAL,CONTENT,VALUE,COMMERCIAL,OPERATIONAL,TEMPORAL,OPTION,CHANNEL'], 'eligibility_rules.*.fact_key' => ['required', 'string', 'max:120'], 'eligibility_rules.*.operator' => ['required', 'in:EQ,NEQ,IN,NOT_IN,MIN,MAX,BETWEEN,EXISTS,NOT_EXISTS'], 'eligibility_rules.*.expected_value' => ['present'], 'eligibility_rules.*.reason_code' => ['required', 'string', 'max:120'], 'eligibility_rules.*.priority' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'coverage_references.*.direction' => ['required', 'in:ORIGIN,DESTINATION,LANE,BOTH'], 'coverage_references.*.reference_type' => ['required', 'in:COUNTRY,PROVINCE,CITY,POSTAL_RANGE,OPERATIONAL_AREA,PRICING_ZONE_SET'], 'coverage_references.*.reference_value' => ['required', 'string', 'max:200'], 'coverage_references.*.secondary_reference_value' => ['nullable', 'string', 'max:200'], 'coverage_references.*.priority' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'availability_bindings.*.scope_type' => ['required', 'in:PLATFORM,TENANT,CUSTOMER_SEGMENT,CUSTOMER,CONTRACT,CHANNEL'], 'availability_bindings.*.scope_value' => ['nullable', 'string', 'max:120'], 'availability_bindings.*.enabled' => ['sometimes', 'boolean'],
            'commitment_binding' => ['nullable', 'array'], 'commitment_binding.commitment_schedule_version_id' => ['required_with:commitment_binding', 'uuid'],
            'commitment_binding.pickup_mode' => ['required_with:commitment_binding', 'in:NONE,SELECTABLE_WINDOW,COMPUTED'], 'commitment_binding.delivery_mode' => ['required_with:commitment_binding', 'in:NONE,SELECTABLE_WINDOW,COMPUTED'],
            'commitment_binding.duration_value' => ['required_if:commitment_binding.delivery_mode,COMPUTED', 'nullable', 'integer', 'min:1'], 'commitment_binding.duration_unit' => ['required_if:commitment_binding.delivery_mode,COMPUTED', 'nullable', 'in:MINUTE,HOUR,DAY'],
            'commitment_binding.duration_anchor' => ['required_if:commitment_binding.delivery_mode,COMPUTED', 'nullable', 'in:CONSIGNMENT_CREATED,PICKUP_COMMITMENT_START,PICKUP_COMMITMENT_END,PICKUP_COMPLETED'],
        ];
    }

    /** @return array<string,mixed> */
    private function scheduleRules(bool $creating): array
    {
        $rules = [
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:200'], 'timezone' => ['required', 'timezone'], 'calendar_code' => ['required', 'string', 'max:80'],
            'valid_from' => ['nullable', 'date'], 'valid_to' => ['nullable', 'date', 'after:valid_from'],
            'windows' => ['required', 'array', 'min:1'], 'windows.*.window_code' => ['required', 'regex:/^[A-Z][A-Z0-9_]{1,79}$/'], 'windows.*.window_type' => ['required', 'in:PICKUP,DELIVERY'],
            'windows.*.label_fa' => ['required', 'string', 'max:200'], 'windows.*.start_time' => ['required', 'date_format:H:i'], 'windows.*.end_time' => ['required', 'date_format:H:i'], 'windows.*.booking_cutoff_time' => ['required', 'date_format:H:i'],
            'windows.*.applicable_weekdays' => ['required', 'array', 'min:1'], 'windows.*.applicable_weekdays.*' => ['integer', 'between:1,7'], 'windows.*.day_offset' => ['sometimes', 'integer', 'between:0,30'], 'windows.*.active' => ['sometimes', 'boolean'],
            'scopes' => ['required', 'array', 'min:1'], 'scopes.*.scope_type' => ['required', 'in:HQ,NODE'], 'scopes.*.node_id' => ['required_if:scopes.*.scope_type,NODE', 'nullable', 'uuid'],
        ];
        if ($creating) $rules['code'] = ['required', 'regex:/^[A-Z][A-Z0-9_]{1,79}$/'];
        return $rules;
    }

    private function principal(Request $request): AuthenticatedPrincipal { return $request->attributes->get('principal'); }
    private function correlation(Request $request): string { return (string) $request->attributes->get('correlation_id'); }
    private function nodeId(Request $request): string
    {
        $nodeId = $request->attributes->get('node_id');
        if (! is_string($nodeId) || $nodeId === '') throw new \Modules\Foundation\Domain\ApiException(\Modules\Foundation\Domain\ApiErrorCode::ValidationError, 422, 'An active operational node is required.');
        return $nodeId;
    }
}
