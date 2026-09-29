<?php

declare(strict_types=1);

namespace Modules\Iam\Infrastructure\Security;

use Illuminate\Support\Facades\Crypt;
use Modules\Iam\Application\Contracts\DeliveryCipherInterface;

final class LaravelDeliveryCipher implements DeliveryCipherInterface
{
    public function encrypt(string $plaintext): string
    {
        return Crypt::encryptString($plaintext);
    }
}
