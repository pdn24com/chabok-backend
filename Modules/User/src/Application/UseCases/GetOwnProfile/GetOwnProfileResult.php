<?php

declare(strict_types=1);

namespace Modules\User\Application\UseCases\GetOwnProfile;

final readonly class GetOwnProfileResult
{
    public function __construct(public array $data)
    {
    }
}
