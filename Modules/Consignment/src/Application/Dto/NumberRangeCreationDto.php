<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Dto;

use Modules\Consignment\Domain\ValueObjects\NumberRangeInput;

final readonly class NumberRangeCreationDto
{
    public function __construct(public string $title, public NumberRangeInput $definition) {}

    public static function fromValidated(array $input): self
    {
        return new self(trim($input['title']), NumberRangeInput::fromValidated($input));
    }
}
