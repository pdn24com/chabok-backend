<?php

declare(strict_types=1);

namespace Modules\Foundation\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;
use Modules\Foundation\Application\UseCases\ExecuteIdempotentCommand\ExecuteIdempotentCommandCommand;
use Modules\Foundation\Application\UseCases\ExecuteIdempotentCommand\ExecuteIdempotentCommandHandler;
use Modules\Foundation\Application\UseCases\ExecuteIdempotentCommand\ExecuteIdempotentCommandResult;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Symfony\Component\HttpFoundation\Response;

final readonly class IdempotentCommand
{
    public function __construct(private ExecuteIdempotentCommandHandler $executeIdempotentCommandHandler) {}

    public function handle(
        Request $request,
        Closure $next,
        string $commandName,
    ): Response {
        $key = (string) $request->header('Idempotency-Key', '');
        if (strlen($key) < 16 || strlen($key) > 128) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid', ['Idempotency-Key' => ['foundation.idempotency_key_length_is_invalid']]);
        }
        /** @var AuthenticatedPrincipal $actor */
        $actor = $request->attributes->get('principal');
        $fingerprint = hash('sha256', $request->method().'|'.$request->path().'|'.$request->getContent());
        $freshResponse = null;
        $result = $this->executeIdempotentCommandHandler->handle(new ExecuteIdempotentCommandCommand($actor, $commandName, $key, $fingerprint), function () use ($request, $next, &$freshResponse): ExecuteIdempotentCommandResult {
            $freshResponse = $next($request);
            if (! $freshResponse instanceof JsonResponse) {
                throw new LogicException('Idempotent commands must return a successful JSON response.');
            }

            return new ExecuteIdempotentCommandResult($freshResponse->getStatusCode(), (string) $freshResponse->getContent());
        });
        if (! $result->replayed) {
            return $freshResponse;
        }
        $payload = json_decode($result->body, true, 512, JSON_THROW_ON_ERROR);
        $payload['correlation_id'] = (string) $request->attributes->get('correlation_id');

        return response()->json($payload, $result->status)->header('X-Correlation-ID', (string) $request->attributes->get('correlation_id'));
    }
}
