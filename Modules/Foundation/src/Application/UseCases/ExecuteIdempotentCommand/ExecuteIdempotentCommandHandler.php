<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\UseCases\ExecuteIdempotentCommand;

use Modules\Foundation\Application\Contracts\Clock;
use Modules\Foundation\Application\Contracts\IdentifierGenerator;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Application\Repositories\IdempotencyRepository;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class ExecuteIdempotentCommandHandler
{
    public function __construct(
        private IdempotencyRepository $records,
        private TransactionManager $transactions,
        private Clock $clock,
        private IdentifierGenerator $identifiers,
    )
    {
    }
    /** @param callable(): ExecuteIdempotentCommandResult $operation */

    public function handle(ExecuteIdempotentCommandCommand $command, callable $operation): ExecuteIdempotentCommandResult
    {
        return $this->transactions->run(function () use ($command, $operation): ExecuteIdempotentCommandResult {
            $actor = $command->actor;
            $this->records->lockActor($actor->userId);
            $existing = $this->records->lockRecord($actor->userId, $command->name, $command->key);
            if ($existing !== null) {
                if (!hash_equals((string) $existing->request_fingerprint, $command->fingerprint)) {
                    throw new ApiException(ApiErrorCode::IdempotencyKeyReused, 409, 'The idempotency key was used for a different request.');
                }
                if ($existing->state !== 'COMPLETED') {
                    throw new ApiException(ApiErrorCode::Conflict, 409, 'The command is already in progress.');
                }
                return new ExecuteIdempotentCommandResult((int) $existing->response_status, (string) $existing->safe_response, true);
            }
            $recordId = $this->identifiers->uuid();
            $this->records->insert([
                'record_id' => $recordId,
                'hq_id' => $actor->hqId,
                'actor_id' => $actor->userId,
                'command_name' => $command->name,
                'idempotency_key' => $command->key,
                'request_fingerprint' => $command->fingerprint,
                'state' => 'IN_PROGRESS',
                'expires_at' => $this->clock->now()->modify('+1 day'),
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
            $result = $operation();
            if ($result->status >= 400) {
                $this->records->delete($recordId);
                return $result;
            }
            $this->records->update($recordId, [
                'state' => 'COMPLETED',
                'response_status' => $result->status,
                'safe_response' => $result->body,
                'updated_at' => $this->clock->now(),
            ]);
            return $result;
        });
    }
}
