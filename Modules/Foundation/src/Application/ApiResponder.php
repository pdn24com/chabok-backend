<?php

declare(strict_types=1);

namespace Modules\Foundation\Application;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Domain\ApiErrorCode;

final class ApiResponder
{
    /**
     * @param mixed $data
     * @param array<string, mixed> $meta
     */
    public static function success(
        Request $request,
        mixed $data,
        array $meta = [],
        int $status = 200,
    ): JsonResponse {
        $correlationId = self::correlationId($request);

        $response = response()->json([
            'data' => $data,
            'meta' => (object) $meta,
            'correlation_id' => $correlationId,
        ], $status)->header('X-Correlation-ID', $correlationId);

        return self::attachConfiguredCors($request, $response);
    }

    /**
     * @template T
     * @param LengthAwarePaginator<T> $paginator
     * @param callable(T): mixed|null $map
     */
    public static function paginated(
        Request $request,
        LengthAwarePaginator $paginator,
        ?callable $map = null,
    ): JsonResponse {
        $items = $paginator->items();
        if ($map !== null) {
            $items = array_map($map, $items);
        }

        return self::success($request, $items, [
            'pagination' => [
                'page' => $paginator->currentPage(),
                'page_size' => $paginator->perPage(),
                'total' => $paginator->total(),
                'total_pages' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * @param array<string, list<string>> $fieldErrors
     * @param array<string, mixed> $details
     */
    public static function error(
        Request $request,
        ApiErrorCode|string $errorCode,
        string $message,
        int $status,
        array $fieldErrors = [],
        array $details = [],
    ): JsonResponse {
        $correlationId = self::correlationId($request);

        $response = response()->json([
            'error_code' => $errorCode instanceof ApiErrorCode ? $errorCode->value : $errorCode,
            'message' => $message,
            'field_errors' => (object) $fieldErrors,
            'details' => (object) $details,
            'correlation_id' => $correlationId,
        ], $status)->header('X-Correlation-ID', $correlationId);

        return self::attachConfiguredCors($request, $response);
    }

    private static function correlationId(Request $request): string
    {
        return (string) $request->attributes->get('correlation_id', '');
    }

    private static function attachConfiguredCors(Request $request, JsonResponse $response): JsonResponse
    {
        $origin = (string) $request->header('Origin', '');
        if ($origin !== '' && in_array($origin, (array) config('chabok.branch_panel.origins', []), true)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Expose-Headers', 'X-Correlation-ID');
            $response->headers->set('Vary', 'Origin');
        }

        return $response;
    }
}
