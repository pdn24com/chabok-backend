<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\ResetPassword;

final readonly class ResetPasswordResult
{
    public function __construct(public string $userId, public int $revokedSessionCount) {}
}
