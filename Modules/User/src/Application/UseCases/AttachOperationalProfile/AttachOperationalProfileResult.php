<?php

declare(strict_types=1);

namespace Modules\User\Application\UseCases\AttachOperationalProfile;

final readonly class AttachOperationalProfileResult
{
    public function __construct(public array $data)
    {
    }
}
