<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Contracts;

interface QuoteBundleStore
{
    /** @param array<string, mixed> $bundle */
    public function put(string $quoteId, array $bundle, int $ttlSeconds): void;

    /** @return array<string, mixed>|null */
    public function get(string $quoteId): ?array;

    public function forget(string $quoteId): void;
}
