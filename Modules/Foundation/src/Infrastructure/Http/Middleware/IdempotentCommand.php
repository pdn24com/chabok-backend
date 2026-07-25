<?php

declare(strict_types=1);

namespace Modules\Foundation\Infrastructure\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Symfony\Component\HttpFoundation\Response;

final class IdempotentCommand
{
    public function handle(Request $request, Closure $next, string $commandName): Response
    {
        $key = (string) $request->header('Idempotency-Key', '');
        if (strlen($key) < 16 || strlen($key) > 128) {
            throw new ApiException(
                ApiErrorCode::ValidationError,
                422,
                'The request is invalid.',
                ['Idempotency-Key' => ['A 16 to 128 character idempotency key is required.']],
            );
        }
        /** @var AuthenticatedPrincipal $actor */
        $actor = $request->attributes->get('principal');
        $fingerprint = hash('sha256', $request->method().'|'.$request->path().'|'.$request->getContent());

        return DB::transaction(function () use ($request, $next, $commandName, $key, $actor, $fingerprint): Response {
            // Serialize retry-sensitive commands per actor before inspecting the
            // unique key so concurrent identical requests cannot race the insert.
            DB::table('users')->where('user_id', $actor->userId)->lockForUpdate()->first();
            $existing = DB::table('idempotency_records')
                ->where('actor_id', $actor->userId)
                ->where('command_name', $commandName)
                ->where('idempotency_key', $key)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if (! hash_equals((string) $existing->request_fingerprint, $fingerprint)) {
                    throw new ApiException(
                        ApiErrorCode::IdempotencyKeyReused,
                        409,
                        'The idempotency key was used for a different request.',
                    );
                }
                if ($existing->state !== 'COMPLETED') {
                    throw new ApiException(ApiErrorCode::Conflict, 409, 'The command is already in progress.');
                }

                $payload = json_decode(
                    (string) $existing->safe_response,
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                );
                $payload['correlation_id'] = (string) $request->attributes->get('correlation_id');

                return response()->json($payload, (int) $existing->response_status);
            }

            $recordId = (string) Str::uuid();
            DB::table('idempotency_records')->insert([
                'record_id' => $recordId,
                'hq_id' => $actor->hqId,
                'actor_id' => $actor->userId,
                'command_name' => $commandName,
                'idempotency_key' => $key,
                'request_fingerprint' => $fingerprint,
                'state' => 'IN_PROGRESS',
                'expires_at' => now()->addDay(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $response = $next($request);
            if (! $response instanceof JsonResponse || $response->getStatusCode() >= 400) {
                throw new \LogicException('Idempotent commands must return a successful JSON response.');
            }
            DB::table('idempotency_records')->where('record_id', $recordId)->update([
                'state' => 'COMPLETED',
                'response_status' => $response->getStatusCode(),
                'safe_response' => $response->getContent(),
                'updated_at' => now(),
            ]);

            return $response;
        }, 3);
    }
}
