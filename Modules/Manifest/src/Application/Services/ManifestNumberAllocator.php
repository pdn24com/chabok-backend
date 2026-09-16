<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Manifest\Application\Repositories\ManifestNumberRepository;
use Modules\Foundation\Application\Contracts\Clock;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class ManifestNumberAllocator
{
    public function __construct(private ManifestNumberRepository $numbers, private Clock $clock)
    {
    }

    public function next(): string
    {
        $key = CarbonImmutable::instance($this->clock->now())->utc()->format('ym');
        $this->numbers->initialize(['sequence_key' => $key, 'next_value' => 1, 'updated_at' => $this->clock->now()]);
        $row = $this->numbers->lock($key);
        $value = (int) ($row?->next_value ?? 0);
        if ($value < 1 || $value > 99999) {
            throw new ApiException(ApiErrorCode::InternalServerError, 500, 'Unable to allocate a Manifest number.');
        }
        $this->numbers->update($key, ['next_value' => $value + 1, 'updated_at' => $this->clock->now()]);
        return sprintf('MNF-%s-%05d', $key, $value);
    }
}
