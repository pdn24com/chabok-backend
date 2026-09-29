<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\ListSessions;

use DateTimeImmutable;
use DateTimeZone;
use Modules\Foundation\Application\Dto\SessionSummaryDto;
use Modules\Iam\Application\Repositories\SessionRepositoryInterface;

final readonly class ListSessionsHandler
{
    public function __construct(
        private SessionRepositoryInterface $sessionRepository,
    ) {}

    /** @return list<SessionSummaryDto> */
    public function handle(ListSessionsCommand $command): array
    {
        $userId = $command->userId;

        $sessions = [];
        foreach ($this->sessionRepository->forUserNewestFirst($userId) as $session) {
            $sessions[] = new SessionSummaryDto(
                $session->session_id,
                $session->device_id,
                $session->device_name,
                new DateTimeImmutable($session->issued_at, new DateTimeZone('UTC')),
                new DateTimeImmutable($session->expires_at, new DateTimeZone('UTC')),
                new DateTimeImmutable($session->last_seen_at ?? $session->issued_at, new DateTimeZone('UTC')),
                $session->revoked_at !== null,
            );
        }

        return $sessions;
    }
}
