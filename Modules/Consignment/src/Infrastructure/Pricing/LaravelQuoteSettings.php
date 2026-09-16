<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Pricing;

use Modules\Consignment\Application\Contracts\QuoteSettings;

final class LaravelQuoteSettings implements QuoteSettings
{
    public function quoteTtlSeconds(): int
    {
        return (int) config('chabok.consignment.quote_ttl_seconds', 900);
    }
}
