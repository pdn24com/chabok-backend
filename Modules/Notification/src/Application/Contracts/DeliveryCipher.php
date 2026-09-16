<?php

declare(strict_types=1);

namespace Modules\Notification\Application\Contracts;

interface DeliveryCipher
{
    public function decrypt(string $ciphertext): string;
}
