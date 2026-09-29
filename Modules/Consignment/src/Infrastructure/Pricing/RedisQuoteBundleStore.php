<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Pricing;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Redis;
use JsonException;
use Modules\Consignment\Application\Contracts\QuoteBundleStoreInterface;
use Modules\Consignment\Application\Dto\ConsignmentQuoteBundleDto;
use Modules\Consignment\Application\Mappers\ConsignmentQuoteMapper;
use Modules\Consignment\Application\Serialization\ConsignmentQuoteDocument;
use UnexpectedValueException;

final class RedisQuoteBundleStore implements QuoteBundleStoreInterface
{
    public function put(
        string $quoteId,
        ConsignmentQuoteBundleDto $bundle,
        int $ttlSeconds,
    ): void {
        $encrypted = Crypt::encryptString(json_encode(ConsignmentQuoteDocument::bundle($bundle), JSON_THROW_ON_ERROR));
        Redis::connection('cache')->setex($this->key($quoteId), $ttlSeconds, $encrypted);
    }

    public function get(string $quoteId): ?ConsignmentQuoteBundleDto
    {
        $encrypted = Redis::connection('cache')->get($this->key($quoteId));
        if (! is_string($encrypted)) {
            return null;
        }
        try {
            $decoded = json_decode(Crypt::decryptString($encrypted), true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? ConsignmentQuoteMapper::bundle($decoded) : null;
        } catch (DecryptException|JsonException|UnexpectedValueException) {
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
