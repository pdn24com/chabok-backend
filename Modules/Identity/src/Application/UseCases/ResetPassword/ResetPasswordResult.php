<?php

declare(strict_types=1);

namespace Modules\Identity\Application\UseCases\ResetPassword;

final readonly class ResetPasswordResult
{
    public function __construct(public array $data)
    {
    }
}
