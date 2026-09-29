<?php

declare(strict_types=1);

namespace Modules\Iam\Infrastructure\Security;

use Modules\Foundation\Application\Ports\AccessSessionValidatorInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AccessTokenClaims;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Iam\Application\Ports\PlatformContextValidatorInterface;
use Modules\Iam\Infrastructure\Persistence\Models\SessionRecord;

final readonly class DatabaseAccessSessionValidator implements AccessSessionValidatorInterface
{
    public function __construct(private RedisSessionRegistry $registry, private PlatformContextValidatorInterface $platformContextValidator) {}

    public function validate(AccessTokenClaims $claims): AuthenticatedPrincipal
    {
        if ($this->registry->isInvalidated($claims->sessionId)) {
            $this->reject();
        }
        $row = SessionRecord::query()
            ->with('user')
            ->where('session_id', $claims->sessionId)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();
        if ($row === null || $row->user_id !== $claims->userId || $row->hq_id !== $claims->hqId || $row->user?->status !== 'ACTIVE' || $row->hq_id === null && ! $this->platformContextValidator->hasActivePlatformAssignment((string) $row->user_id)) {
            $this->reject();
        }

        return new AuthenticatedPrincipal((string) $row->user_id, $claims->sessionId, $row->hq_id === null ? null : (string) $row->hq_id, (bool) $row->user->must_change_password);
    }

    private function reject(): never
    {
        throw new ApiException(ApiErrorCode::AuthenticationRequired, 401, 'common.authentication_required');
    }
}
