<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Repositories;

interface PricingAcceptanceRepository
{
    public function lockQuote(string $hqId, string $quoteId): ?object;

    public function quoteLines(string $quoteId): array;

    public function insertSnapshot(array $attributes): void;

    public function insertChargeLine(array $attributes): void;

    public function updateQuote(string $quoteId, array $changes): void;
}
