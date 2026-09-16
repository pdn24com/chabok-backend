<?php

declare(strict_types=1);

namespace Modules\Foundation\Infrastructure\Services;

use Illuminate\Support\Str;
use Modules\Foundation\Application\Contracts\CorrelationIdProvider;

final class LaravelCorrelationIdProvider implements CorrelationIdProvider
{
    public function current(): string
    {
        $value = request()?->attributes->get('correlation_id');
        return is_string($value) && $value !== '' ? $value : (string) Str::uuid();
    }
}
