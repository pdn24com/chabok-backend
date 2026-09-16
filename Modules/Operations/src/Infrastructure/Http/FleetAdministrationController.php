<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Application\ApiResponder;
use Modules\Foundation\Application\StrictPayload;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Operations\Application\FleetAdministrationService;

final readonly class FleetAdministrationController
{
    public function __construct(private FleetAdministrationService $fleet) {}

    public function drivers(Request $request): JsonResponse
    {
        $filters = $request->validate($this->listRules() + ['unlinked' => ['sometimes', 'boolean'], 'capability' => ['sometimes', 'nullable', 'in:PICKUP,LINEHAUL,DELIVERY']]);
        $page = $this->fleet->drivers($this->principal($request), $filters);

        return ApiResponder::success($request, $page->items(), $this->pagination($page));
    }

    public function createDriver(Request $request): JsonResponse
    {
        $fields = ['driver_code', 'display_name', 'user_id', 'home_node_id', 'mobile', 'capabilities'];
        StrictPayload::assertOnly($request, $fields);
        $input = $request->validate([
            'driver_code' => ['required', 'string', 'max:80'],
            'display_name' => ['required', 'string', 'max:200'],
            'user_id' => ['sometimes', 'nullable', 'uuid'],
            'home_node_id' => ['required', 'uuid'],
            'mobile' => ['sometimes', 'nullable', 'string', 'max:32'],
            'capabilities' => ['required', 'array', 'min:1'],
            'capabilities.*' => ['required', 'in:PICKUP,LINEHAUL,DELIVERY', 'distinct'],
        ]);

        return ApiResponder::success($request, $this->fleet->createDriver($this->principal($request), $input, $this->correlation($request)), status: 201);
    }

    public function driver(Request $request, string $driver_id): JsonResponse
    {
        return ApiResponder::success($request, $this->fleet->driverDetail($this->principal($request), $driver_id));
    }

    public function updateDriver(Request $request, string $driver_id): JsonResponse
    {
        $fields = ['display_name', 'user_id', 'home_node_id', 'mobile', 'capabilities', 'status', 'availability_status', 'expected_version'];
        StrictPayload::assertOnly($request, $fields);
        $input = $request->validate([
            'display_name' => ['sometimes', 'string', 'max:200'],
            'user_id' => ['sometimes', 'nullable', 'uuid'],
            'home_node_id' => ['sometimes', 'uuid'],
            'mobile' => ['sometimes', 'nullable', 'string', 'max:32'],
            'capabilities' => ['sometimes', 'array', 'min:1'],
            'capabilities.*' => ['required_with:capabilities', 'in:PICKUP,LINEHAUL,DELIVERY', 'distinct'],
            'status' => ['sometimes', 'in:ACTIVE,INACTIVE'],
            'availability_status' => ['sometimes', 'in:AVAILABLE,ON_MISSION,TEMPORARILY_INACTIVE,MAINTENANCE,INACTIVE'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ]);
        $this->assertMutation($input);

        return ApiResponder::success($request, $this->fleet->updateDriver($this->principal($request), $driver_id, $input, $this->correlation($request)));
    }

    public function vehicles(Request $request): JsonResponse
    {
        $filters = $request->validate($this->listRules() + ['vehicle_type' => ['sometimes', 'nullable', 'in:MOTORCYCLE,CAR,VAN,LIGHT_TRUCK,TRUCK,TRAILER,OTHER']]);
        $page = $this->fleet->vehicles($this->principal($request), $filters);

        return ApiResponder::success($request, $page->items(), $this->pagination($page));
    }

    public function createVehicle(Request $request): JsonResponse
    {
        $fields = ['vehicle_code', 'plate_number', 'vehicle_type', 'home_node_id', 'capacity_weight_grams', 'capacity_volume_cm3'];
        StrictPayload::assertOnly($request, $fields);
        $input = $request->validate([
            'vehicle_code' => ['required', 'string', 'max:80'],
            'plate_number' => ['required', 'string', 'max:40'],
            'vehicle_type' => ['required', 'in:MOTORCYCLE,CAR,VAN,LIGHT_TRUCK,TRUCK,TRAILER,OTHER'],
            'home_node_id' => ['required', 'uuid'],
            'capacity_weight_grams' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'capacity_volume_cm3' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        return ApiResponder::success($request, $this->fleet->createVehicle($this->principal($request), $input, $this->correlation($request)), status: 201);
    }

    public function vehicle(Request $request, string $vehicle_id): JsonResponse
    {
        return ApiResponder::success($request, $this->fleet->vehicleDetail($this->principal($request), $vehicle_id));
    }

    public function updateVehicle(Request $request, string $vehicle_id): JsonResponse
    {
        $fields = ['plate_number', 'vehicle_type', 'home_node_id', 'capacity_weight_grams', 'capacity_volume_cm3', 'status', 'availability_status', 'expected_version'];
        StrictPayload::assertOnly($request, $fields);
        $input = $request->validate([
            'plate_number' => ['sometimes', 'string', 'max:40'],
            'vehicle_type' => ['sometimes', 'in:MOTORCYCLE,CAR,VAN,LIGHT_TRUCK,TRUCK,TRAILER,OTHER'],
            'home_node_id' => ['sometimes', 'uuid'],
            'capacity_weight_grams' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'capacity_volume_cm3' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'status' => ['sometimes', 'in:ACTIVE,INACTIVE'],
            'availability_status' => ['sometimes', 'in:AVAILABLE,ON_MISSION,TEMPORARILY_INACTIVE,MAINTENANCE,INACTIVE'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ]);
        $this->assertMutation($input);

        return ApiResponder::success($request, $this->fleet->updateVehicle($this->principal($request), $vehicle_id, $input, $this->correlation($request)));
    }

    /** @return array<string, mixed> */
    private function listRules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'status' => ['sometimes', 'nullable', 'in:ACTIVE,INACTIVE'],
            'availability_status' => ['sometimes', 'nullable', 'in:AVAILABLE,ON_MISSION,TEMPORARILY_INACTIVE,MAINTENANCE,INACTIVE'],
            'home_node_id' => ['sometimes', 'nullable', 'uuid'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** @param array<string,mixed> $input */
    private function assertMutation(array $input): void
    {
        if (count($input) < 2) throw new ApiException(ApiErrorCode::ValidationError, 422, 'At least one field must be changed.');
    }

    private function principal(Request $request): AuthenticatedPrincipal
    {
        return $request->attributes->get('principal');
    }

    private function correlation(Request $request): string
    {
        return (string) $request->attributes->get('correlation_id');
    }

    /** @return array{pagination:array{page:int,page_size:int,total:int,total_pages:int}} */
    private function pagination(\Illuminate\Contracts\Pagination\LengthAwarePaginator $page): array
    {
        return ['pagination' => [
            'page' => $page->currentPage(),
            'page_size' => $page->perPage(),
            'total' => $page->total(),
            'total_pages' => $page->lastPage(),
        ]];
    }
}
