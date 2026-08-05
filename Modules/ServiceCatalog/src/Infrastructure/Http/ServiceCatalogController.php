<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Application\ApiResponder;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\ServiceCatalog\Application\ServiceCatalogService;

final readonly class ServiceCatalogController
{
    public function __construct(private ServiceCatalogService $catalog) {}

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
        $input = $request->validate(['channel' => ['required', 'in:BRANCH,VENDOR,DRIVER,HQ,API,TRACKING,LEGACY'], 'as_of_timestamp' => ['nullable', 'date'], 'sender' => ['required', 'array'], 'receiver' => ['required', 'array'], 'parcels' => ['nullable', 'array'], 'selected_option_version_ids' => ['nullable', 'array'], 'selected_option_version_ids.*' => ['uuid']]);
        return ApiResponder::success($request, $this->catalog->resolve($this->principal($request), $input));
    }

    public function validateSelection(Request $request, string $offeringId): JsonResponse
    {
        $input = $request->validate(['service_offering_version_id' => ['nullable', 'uuid'], 'channel' => ['required', 'in:BRANCH,VENDOR,DRIVER,HQ,API,TRACKING,LEGACY'], 'as_of_timestamp' => ['nullable', 'date'], 'sender' => ['required', 'array'], 'receiver' => ['required', 'array'], 'parcels' => ['nullable', 'array'], 'selected_option_version_ids' => ['nullable', 'array'], 'selected_option_version_ids.*' => ['uuid']]);
        return ApiResponder::success($request, $this->catalog->validateSelection($this->principal($request), $offeringId, $input['service_offering_version_id'] ?? null, $input));
    }

    public function commitments(Request $request, string $offeringId): JsonResponse
    {
        $input = $request->validate(['service_offering_version_id' => ['nullable', 'uuid'], 'channel' => ['required', 'string'], 'acceptance_at' => ['nullable', 'date'], 'sender' => ['required', 'array'], 'receiver' => ['required', 'array'], 'parcels' => ['nullable', 'array']]);
        return ApiResponder::success($request, $this->catalog->commitmentPreview($this->principal($request), $offeringId, $input));
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
        ];
    }

    private function principal(Request $request): AuthenticatedPrincipal { return $request->attributes->get('principal'); }
    private function correlation(Request $request): string { return (string) $request->attributes->get('correlation_id'); }
}
