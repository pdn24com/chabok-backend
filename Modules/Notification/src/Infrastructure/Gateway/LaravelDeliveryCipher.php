<?php

declare(strict_types=1);

namespace Modules\Notification\Infrastructure\Gateway;

use Illuminate\Support\Facades\Crypt;
use Modules\Foundation\Application\Exceptions\OutboxPublishException;
use Modules\Notification\Application\Contracts\DeliveryCipherInterface;
use Throwable;

final class LaravelDeliveryCipher implements DeliveryCipherInterface
{
    public function decrypt(string $ciphertext): string
    {
        try {
            return Crypt::decryptString($ciphertext);
        } catch (Throwable) {
            throw new OutboxPublishException('DELIVERY_SECRET_INVALID', false);
        }
    }
}
