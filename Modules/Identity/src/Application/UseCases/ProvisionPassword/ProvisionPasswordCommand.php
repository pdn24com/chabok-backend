<?php

declare(strict_types=1);

namespace Modules\Identity\Application\UseCases\ProvisionPassword;

final readonly class ProvisionPasswordCommand
{
    public function __construct(public string $userId, public string $password)
    {
    }
}
