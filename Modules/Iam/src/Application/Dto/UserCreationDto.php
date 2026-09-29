<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Dto;

use Modules\Foundation\Domain\ValueObjects\RoleAssignment;
use Modules\Iam\Domain\Enums\UserCreationMode;

final readonly class UserCreationDto
{
    /** @param list<RoleAssignment> $assignments */
    public function __construct(public ?UserCreationMode $mode, public ?string $username, public ?string $mobile, public ?string $email, public string $firstName, public string $lastName, public ?string $temporaryPassword, public array $assignments, public ?OperationalProfileInputDto $operationalProfile) {}
}
