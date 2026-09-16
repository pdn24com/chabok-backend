<?php

declare(strict_types=1);

namespace Modules\User\Application\UseCases\UpdateOwnProfile;

final readonly class UpdateOwnProfileResult
{
    public function __construct(public array $data)
    {
    }
}
