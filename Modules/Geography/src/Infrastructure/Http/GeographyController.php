<?php

declare(strict_types=1);

namespace Modules\Geography\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Application\ApiResponder;
use Modules\Geography\Application\GeographyQuery;

final readonly class GeographyController
{
    public function __construct(private GeographyQuery $geography) {}

    public function provinces(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:160'],
            'active' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return ApiResponder::paginated($request, $this->geography->provinces($filters),
            fn ($row): array => $this->geography->provinceResource((array) $row));
    }

    public function cities(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'province_id' => ['sometimes', 'nullable', 'uuid'],
            'province_code' => ['sometimes', 'nullable', 'string', 'max:4'],
            'search' => ['sometimes', 'nullable', 'string', 'max:200'],
            'active' => ['sometimes', 'boolean'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return ApiResponder::paginated($request, $this->geography->cities($filters),
            fn ($row): array => $this->geography->cityResource((array) $row));
    }

    public function city(Request $request, string $cityId): JsonResponse
    {
        return ApiResponder::success($request, $this->geography->city($cityId));
    }
}
