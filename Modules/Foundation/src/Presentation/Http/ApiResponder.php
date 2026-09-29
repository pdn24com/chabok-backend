<?php

declare(strict_types=1);

namespace Modules\Foundation\Presentation\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Foundation\Application\Support\ApiMessage;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Presentation\Http\Middleware\NegotiateLocale;

final class ApiResponder
{
    /**
     * @param  array<string, mixed>  $meta
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
     *
     * @param  LengthAwarePaginator<T>  $paginator
     * @param  callable(T): mixed|null  $map
     * @param  array<string, mixed>  $meta
     */
    public static function paginated(
        Request $request,
        LengthAwarePaginator $paginator,
        ?callable $map = null,
        array $meta = [],
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
            ...$meta,
        ]);
    }

    /**
     * @param  string  $messageKey  Key into lang/<locale>/api.php, e.g. 'common.resource_not_found'.
     * @param  array<string, scalar>  $messageParams
     * @param  array<string, list<string>>  $fieldErrors
     * @param  array<string, mixed>  $details
     */
    public static function error(
        Request $request,
        ApiErrorCode|string $errorCode,
        string $messageKey,
        int $status,
        array $fieldErrors = [],
        array $details = [],
        array $messageParams = [],
    ): JsonResponse {
        $correlationId = self::correlationId($request);
        $locale = self::locale($request);
        $response = response()->json([
            'error_code' => $errorCode instanceof ApiErrorCode ? $errorCode->value : $errorCode,
            'message' => ApiMessage::translate($messageKey, $messageParams, $locale),
            'locale' => $locale,
            'field_errors' => (object) ApiMessage::translateFieldErrors($fieldErrors, $locale),
            'details' => (object) $details,
            'correlation_id' => $correlationId,
        ], $status)->header('X-Correlation-ID', $correlationId);

        return self::attachConfiguredCors($request, $response);
    }

    private static function locale(Request $request): string
    {
        $locale = (string) $request->attributes->get('locale', '');

        return $locale !== '' ? $locale : NegotiateLocale::DEFAULT_LOCALE;
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
