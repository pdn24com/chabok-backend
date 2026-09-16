<?php

declare(strict_types=1);

namespace Modules\Notification\Infrastructure\Gateway;

use Illuminate\Support\Facades\Crypt;
use Modules\Foundation\Application\OutboxPublishException;
use Modules\Notification\Application\Contracts\DeliveryCipher;

final class LaravelDeliveryCipher implements DeliveryCipher
{
    public function decrypt(string $ciphertext): string
    {
        try {
            return Crypt::decryptString($ciphertext);
        } catch (\Throwable) {
            throw new OutboxPublishException('DELIVERY_SECRET_INVALID', false);
        }
    }
}
