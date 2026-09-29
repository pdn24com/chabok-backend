<?php

declare(strict_types=1);

namespace Modules\Foundation\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Foundation\Application\Dto\SessionSummaryDto;

/** @mixin SessionSummaryDto */
final class SessionSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'session_id' => $this->sessionId,
            'device_id' => $this->deviceId,
            'device_name' => $this->deviceName,
            'issued_at' => $this->issuedAt->format(DATE_ATOM),
            'expires_at' => $this->expiresAt->format(DATE_ATOM),
            'last_seen_at' => $this->lastSeenAt->format(DATE_ATOM),
            'revoked' => $this->revoked,
        ];
    }
}
