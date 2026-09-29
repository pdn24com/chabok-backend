<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Dto;

/** A person who does not exist yet, created together with the relationship that introduces them. */
final readonly class NewPersonDto
{
    public function __construct(
        public string $firstName,
        public string $familyName,
        public string $mobile,
    ) {}
}
