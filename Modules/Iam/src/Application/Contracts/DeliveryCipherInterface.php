<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Contracts;

interface DeliveryCipherInterface
{
    public function encrypt(string $plaintext): string;
}
