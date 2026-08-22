<?php

declare(strict_types=1);

namespace Modules\Manifest\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Application\ApiResponder;
use Modules\Foundation\Application\StrictPayload;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Manifest\Application\ManifestService;

final readonly class ManifestController
{
    public function __construct(private ManifestService $manifests) {}

    public function index(Request $request): JsonResponse
    {
        $input = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'page_size' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:160'],
            'state' => ['sometimes', 'nullable', 'in:DRAFT,OPEN,CLOSED'],
            'manifest_status' => ['sometimes', 'nullable', 'in:IR,OF,OD'],
        ]);

        return ApiResponder::paginated(
            $request,
            $this->manifests->list($this->actor($request), $this->node($request), $input),
            fn ($row): array => $this->manifests->listItem($row),
        );
    }

    public function store(Request $request): JsonResponse
    {
        StrictPayload::assertOnly($request, ['manifest_status', 'origin_node_id', 'destination_node_id', 'route_plan_id', 'route_plan_leg_id', 'transport_run_id', 'assigned_driver_id', 'assigned_vehicle_id']);
        $input = $request->validate([
            'manifest_status' => ['required', 'in:IR,OF,OD'],
            'origin_node_id' => ['sometimes', 'nullable', 'uuid'],
            'destination_node_id' => ['sometimes', 'nullable', 'uuid'],
            'route_plan_id' => ['sometimes', 'nullable', 'uuid'],
            'route_plan_leg_id' => ['sometimes', 'nullable', 'uuid'],
            'transport_run_id' => ['sometimes', 'nullable', 'uuid'],
            'assigned_driver_id' => ['sometimes', 'nullable', 'uuid'],
            'assigned_vehicle_id' => ['sometimes', 'nullable', 'uuid'],
        ]);

        return ApiResponder::success(
            $request,
            $this->manifests->create(
                $this->actor($request),
                $this->node($request),
                $input,
                $this->correlation($request),
            ),
            status: 201,
        );
    }

    public function show(Request $request, string $manifestId): JsonResponse
    {
        return ApiResponder::success(
            $request,
            $this->manifests->get($this->actor($request), $this->node($request), $manifestId),
        );
    }

    public function update(Request $request, string $manifestId): JsonResponse
    {
        StrictPayload::assertOnly($request, ['expected_version', 'manifest_status', 'origin_node_id', 'destination_node_id', 'route_plan_id', 'route_plan_leg_id', 'transport_run_id', 'assigned_driver_id', 'assigned_vehicle_id']);
        $input = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
            'manifest_status' => ['sometimes', 'in:IR,OF,OD'],
            'origin_node_id' => ['sometimes', 'nullable', 'uuid'],
            'destination_node_id' => ['sometimes', 'nullable', 'uuid'],
            'route_plan_id' => ['sometimes', 'nullable', 'uuid'],
            'route_plan_leg_id' => ['sometimes', 'nullable', 'uuid'],
            'transport_run_id' => ['sometimes', 'nullable', 'uuid'],
            'assigned_driver_id' => ['sometimes', 'nullable', 'uuid'],
            'assigned_vehicle_id' => ['sometimes', 'nullable', 'uuid'],
        ]);
        if (count($input) === 1) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'A context change is required.');
        }

        return ApiResponder::success($request, $this->manifests->update(
            $this->actor($request),
            $this->node($request),
            $manifestId,
            $input,
            $this->correlation($request),
        ));
    }

    public function eligible(Request $request, string $manifestId): JsonResponse
    {
        $input = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'page_size' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:160'],
        ]);

        return ApiResponder::paginated(
            $request,
            $this->manifests->eligible(
                $this->actor($request),
                $this->node($request),
                $manifestId,
                $input,
            ),
            static fn ($row): array => [
                'parcel_id' => (string) $row->parcel_id,
                'parcel_number' => (string) $row->parcel_number,
                'consignment_id' => (string) $row->consignment_id,
                'consignment_number' => (string) $row->consignment_number,
                'receiver_contact_name' => (string) $row->receiver_contact_name,
                'current_status' => (string) $row->current_status,
            ],
        );
    }

    public function addParcels(Request $request, string $manifestId): JsonResponse
    {
        StrictPayload::assertOnly($request, ['expected_version', 'input_source', 'identifiers']);
        $input = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
            'input_source' => ['required', 'in:SCAN,MANUAL,BATCH,AWAITING'],
            'identifiers' => ['required', 'array', 'min:1', 'max:200'],
            'identifiers.*' => ['required', 'string', 'max:64', 'distinct'],
        ]);
        $result = $this->manifests->add(
            $this->actor($request),
            $this->node($request),
            $manifestId,
            $input,
            $this->correlation($request),
        );

        return ApiResponder::success(
            $request,
            $result['detail'],
            ['input_outcomes' => $result['outcomes']],
        );
    }

    public function validateManifest(Request $request, string $manifestId): JsonResponse
    {
        return ApiResponder::success($request, $this->manifests->validate(
            $this->actor($request),
            $this->node($request),
            $manifestId,
            $this->expected($request),
            $this->correlation($request),
        ));
    }

    public function confirm(Request $request, string $manifestId): JsonResponse
    {
        StrictPayload::assertOnly($request, ['expected_version', 'acknowledge_partial_success']);
        $input = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
            'acknowledge_partial_success' => ['required', 'accepted'],
        ]);

        return ApiResponder::success($request, $this->manifests->confirm(
            $this->actor($request),
            $this->node($request),
            $manifestId,
            (int) $input['expected_version'],
            $this->correlation($request),
        ));
    }

    private function expected(Request $request): int
    {
        StrictPayload::assertOnly($request, ['expected_version']);

        return (int) $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
        ])['expected_version'];
    }

    private function actor(Request $request): AuthenticatedPrincipal
    {
        return $request->attributes->get('principal');
    }

    private function correlation(Request $request): string
    {
        return (string) $request->attributes->get('correlation_id');
    }

    private function node(Request $request): string
    {
        $id = $request->attributes->get('node_id');
        if (! is_string($id) || $id === '') {
            throw new ApiException(
                ApiErrorCode::ValidationError,
                422,
                'An active operational node is required.',
            );
        }

        return $id;
    }
}
