<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\UseCases\ExecuteIdempotentCommand;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\CommandActorLockInterface;
use Modules\Foundation\Application\Repositories\IdempotencyRepositoryInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\IdempotencyState;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class ExecuteIdempotentCommandHandler
{
    public function __construct(
        private CommandActorLockInterface $commandActorLock,
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private IdempotencyRepositoryInterface $idempotencyRepository,
    ) {}

    /** @param callable(): ExecuteIdempotentCommandResult $operation */
    public function handle(ExecuteIdempotentCommandCommand $command, callable $operation): ExecuteIdempotentCommandResult
    {
        return $this->connection->transaction(function () use ($command, $operation): ExecuteIdempotentCommandResult {
            $actor = $command->actor;
            $this->commandActorLock->lock($actor->userId);
            $existing = $this->idempotencyRepository->lockForCommand($actor->userId, $command->name, $command->key);
            if ($existing !== null) {
                if (! hash_equals((string) $existing->request_fingerprint, $command->fingerprint)) {
                    throw new ApiException(ApiErrorCode::IdempotencyKeyReused, 409, 'foundation.idempotency_key_used_different_request');
                }
                if ($existing->state !== IdempotencyState::COMPLETED) {
                    throw new ApiException(ApiErrorCode::Conflict, 409, 'foundation.command_is_already_progress');
                }

                return new ExecuteIdempotentCommandResult((int) $existing->response_status, (string) $existing->safe_response, true);
            }
            $record = $this->idempotencyRepository->create([

                'hq_id' => $actor->hqId,
                'actor_id' => $actor->userId,
                'command_name' => $command->name,
                'idempotency_key' => $command->key,
                'request_fingerprint' => $command->fingerprint,
                'state' => IdempotencyState::IN_PROGRESS,
                'expires_at' => $this->clock->now()->modify('+1 day'),
            ]);
            $result = $operation();
            if ($result->status >= 400) {
                $this->idempotencyRepository->delete($record);

                return $result;
            }
            $this->idempotencyRepository->apply($record, [
                'state' => IdempotencyState::COMPLETED,
                'response_status' => $result->status,
                'safe_response' => $result->body,
            ]);

            return $result;
        }, attempts: 3);
    }
}
