<?php

declare(strict_types=1);

namespace Modules\Identity\Application\UseCases\ListSessions;

use Modules\Identity\Application\Data\IdentityData;
use Modules\Identity\Application\Repositories\SessionRepository;

final readonly class ListSessionsHandler
{
    public function __construct(private SessionRepository $sessionRepository)
    {
    }

    public function handle(ListSessionsCommand $command): ListSessionsResult
    {
        return new ListSessionsResult($this->execute($command->userId));
    }

    private function execute(string $userId): array
    {
        return array_map(fn($row): array => [
            'session_id' => $row->session_id,
            'device_id' => $row->device_id,
            'device_name' => $row->device_name,
            'issued_at' => IdentityData::iso((string) $row->issued_at),
            'expires_at' => IdentityData::iso((string) $row->expires_at),
            'last_seen_at' => IdentityData::iso((string) ($row->last_seen_at ?? $row->issued_at)),
            'revoked' => $row->revoked_at !== null,
        ], $this->sessionRepository->allForUser($userId));
    }
}
