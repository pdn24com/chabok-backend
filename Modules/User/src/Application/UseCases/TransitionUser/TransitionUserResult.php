<?php

declare(strict_types=1);

namespace Modules\User\Application\UseCases\TransitionUser;

final readonly class TransitionUserResult
{
    public function __construct(public array $data)
    {
    }
}
