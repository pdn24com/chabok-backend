<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class PricingVersionGuard
{
    public function __construct(private \Modules\Pricing\Application\Repositories\PricingRepository $pricing)
    {
    }

    public function assertDraft(?object $row, int $expected): void
    {
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        if ($row->status !== 'DRAFT') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only drafts are editable.');
        }
        if ((int) $row->lock_version !== $expected) {
            throw new ApiException(ApiErrorCode::VersionConflict, 409, 'The draft changed since it was loaded.', details: ['current_version' => (int) $row->lock_version]);
        }
    }

    public function hasVersionOverlap(string $kind, array $version): bool
    {
        return $this->pricing->hasVersionOverlap($kind, $version);
    }

    public function pricingMap(string $kind): array
    {
        return match ($kind) {
            'tariffs' => ['tariff_family_id', 'tariff_version_id'],
            'zone-sets' => ['pricing_zone_set_id', 'zone_set_version_id'],
            default => throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.'),
        };
    }

    public function defaultConflict(array $version, string $hqId): bool
    {
        return $this->pricing->defaultConflict($version, $hqId);
    }
}
