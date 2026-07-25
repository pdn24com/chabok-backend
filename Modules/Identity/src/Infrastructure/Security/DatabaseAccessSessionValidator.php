<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure\Security;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Application\Contracts\AccessSessionValidator;
use Modules\Foundation\Domain\AccessTokenClaims;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Identity\Application\Contracts\PlatformContextValidator;

final readonly class DatabaseAccessSessionValidator implements AccessSessionValidator
{
    public function __construct(
        private RedisSessionRegistry $registry,
        private PlatformContextValidator $platformContext,
    ) {}

    public function validate(AccessTokenClaims $claims): AuthenticatedPrincipal
    {
        if ($this->registry->isInvalidated($claims->sessionId)) {
            $this->reject();
        }

        $row = DB::table('user_sessions as s')
            ->join('users as u', 'u.user_id', '=', 's.user_id')
            ->where('s.session_id', $claims->sessionId)
            ->whereNull('s.revoked_at')
            ->where('s.expires_at', '>', now())
            ->select([
                's.user_id', 's.hq_id', 'u.must_change_password', 'u.status',
            ])
            ->first();

        if (
            $row === null
            || $row->user_id !== $claims->userId
            || $row->hq_id !== $claims->hqId
            || $row->status !== 'ACTIVE'
            || ($row->hq_id === null && ! $this->platformContext->hasActivePlatformAssignment((string) $row->user_id))
        ) {
            $this->reject();
        }

        return new AuthenticatedPrincipal(
            (string) $row->user_id,
            $claims->sessionId,
            $row->hq_id === null ? null : (string) $row->hq_id,
            (bool) $row->must_change_password,
        );
    }

    private function reject(): never
    {
        throw new ApiException(
            ApiErrorCode::AuthenticationRequired,
            401,
            'Authentication required.',
        );
    }
}
