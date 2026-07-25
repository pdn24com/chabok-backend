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
        return response()->json([
            'data' => $data,
            'meta' => (object) $meta,
            'correlation_id' => self::correlationId($request),
        ], $status);
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
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
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
        return response()->json([
            'error_code' => $errorCode instanceof ApiErrorCode ? $errorCode->value : $errorCode,
            'message' => $message,
            'field_errors' => (object) $fieldErrors,
            'details' => (object) $details,
            'correlation_id' => self::correlationId($request),
        ], $status);
    }

    private static function correlationId(Request $request): string
    {
        return (string) $request->attributes->get('correlation_id', '');
    }
}
