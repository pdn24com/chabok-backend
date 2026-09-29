<?php

declare(strict_types=1);

namespace Modules\Foundation\Infrastructure\Adapters;

use Modules\Foundation\Application\Contracts\CorrelationIdProviderInterface;

final class LaravelCorrelationIdProvider implements CorrelationIdProviderInterface
{
    public function current(): string
    {
        $value = request()?->attributes->get('correlation_id');

        return is_string($value) && $value !== '' ? $value : bin2hex(random_bytes(16));
    }
}
