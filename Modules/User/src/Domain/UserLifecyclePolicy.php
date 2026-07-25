<?php

declare(strict_types=1);

namespace Modules\User\Domain;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final class UserLifecyclePolicy
{
    /** @var array<string, list<string>> */
    private const TRANSITIONS = [
        // Invited users become Active only by consuming an approved activation
        // proof in Identity; the administrative lifecycle endpoint cannot bypass it.
        'INVITED' => ['DEACTIVATED'],
        'ACTIVE' => ['SUSPENDED', 'DEACTIVATED'],
        'SUSPENDED' => ['ACTIVE', 'DEACTIVATED'],
        'DEACTIVATED' => ['ACTIVE'],
    ];

    public function assertTransition(string $from, string $to): void
    {
        if ($from === $to || ! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new ApiException(
                ApiErrorCode::ValidationError,
                422,
                'The requested lifecycle transition is not allowed.',
                details: ['from' => $from, 'to' => $to],
            );
        }
    }
}
