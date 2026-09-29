<?php

declare(strict_types=1);

namespace Modules\Iam\Domain\ValueObjects;

final readonly class NormalizedIdentifiers
{
    public function __construct(public ?string $username, public ?string $mobile, public ?string $email) {}

    /** @return list<string|null> */
    public function values(): array
    {
        return [$this->username, $this->mobile, $this->email];
    }
}
