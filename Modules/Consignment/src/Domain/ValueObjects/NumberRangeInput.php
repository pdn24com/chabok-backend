<?php

declare(strict_types=1);

namespace Modules\Consignment\Domain\ValueObjects;

final readonly class NumberRangeInput
{
    public function __construct(
        public string $numericPrefix,
        public ?int $totalLength,
        public string $serialStart,
        public string $serialEnd,
    ) {}

    public static function fromValidated(array $input): self
    {
        return new self($input['numeric_prefix'], is_int($input['total_length']) ? $input['total_length'] : null, $input['serial_start'], $input['serial_end']);
    }
}
