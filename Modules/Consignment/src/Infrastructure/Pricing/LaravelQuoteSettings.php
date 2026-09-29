<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Pricing;

use Modules\Consignment\Application\Contracts\QuoteSettingsInterface;

final class LaravelQuoteSettings implements QuoteSettingsInterface
{
    public function quoteTtlSeconds(): int
    {
        return (int) config('chabok.consignment.quote_ttl_seconds', 900);
    }
}
