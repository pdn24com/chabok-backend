<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure\Security;

use Illuminate\Support\Facades\Crypt;
use Modules\Identity\Application\Contracts\DeliveryCipher;

final class LaravelDeliveryCipher implements DeliveryCipher
{
    public function encrypt(string $plaintext): string
    {
        return Crypt::encryptString($plaintext);
    }
}
