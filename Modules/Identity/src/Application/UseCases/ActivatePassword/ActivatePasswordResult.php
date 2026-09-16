<?php

declare(strict_types=1);

namespace Modules\Identity\Application\UseCases\ActivatePassword;

final readonly class ActivatePasswordResult
{
    public function __construct(public string $data)
    {
    }
}
