<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Manifest\Application\Contracts\ManifestNumberAllocatorInterface;
use Modules\Manifest\Application\Repositories\ManifestNumberRepositoryInterface;

final readonly class ManifestNumberAllocator implements ManifestNumberAllocatorInterface
{
    public function __construct(
        private ClockInterface $clock,
        private ManifestNumberRepositoryInterface $manifestNumberRepository,
    ) {}

    public function next(): string
    {
        $key = CarbonImmutable::instance($this->clock->now())->utc()->format('ym');

        $row = $this->manifestNumberRepository->lockSequence($key, $this->clock->now());
        $value = (int) ($row?->next_value ?? 0);
        if ($value < 1 || $value > 99999) {
            throw new ApiException(ApiErrorCode::InternalServerError, 500, 'manifest.unable_allocate_manifest_number');
        }
        $row->forceFill(['next_value' => $value + 1, 'updated_at' => $this->clock->now()])->save();

        return sprintf('MNF-%s-%05d', $key, $value);
    }
}
