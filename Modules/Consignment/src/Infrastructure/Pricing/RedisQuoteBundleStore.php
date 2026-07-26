<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Pricing;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Redis;
use Modules\Consignment\Application\Contracts\QuoteBundleStore;

final class RedisQuoteBundleStore implements QuoteBundleStore
{
    public function put(string $quoteId, array $bundle, int $ttlSeconds): void
    {
        $encrypted = Crypt::encryptString(json_encode($bundle, JSON_THROW_ON_ERROR));
        Redis::connection('cache')->setex($this->key($quoteId), $ttlSeconds, $encrypted);
    }

    public function get(string $quoteId): ?array
    {
        $encrypted = Redis::connection('cache')->get($this->key($quoteId));
        if (! is_string($encrypted)) {
            return null;
        }
        try {
            $decoded = json_decode(Crypt::decryptString($encrypted), true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (DecryptException|\JsonException) {
            $this->forget($quoteId);

            return null;
        }
    }

    public function forget(string $quoteId): void
    {
        Redis::connection('cache')->del($this->key($quoteId));
    }

    private function key(string $quoteId): string
    {
        return "chabok:consignment:quote:{$quoteId}";
    }
}
