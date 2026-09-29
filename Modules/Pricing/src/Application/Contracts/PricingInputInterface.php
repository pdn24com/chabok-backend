<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Contracts;

use DateTimeInterface;
use Modules\Pricing\Application\Dto\QuoteInputDto;

interface PricingInputInterface
{
    public function normalize(QuoteInputDto $input): QuoteInputDto;

    public function fingerprint(QuoteInputDto $input): string;

    public function databaseTimestamp(DateTimeInterface|string|null $value): ?string;
}
