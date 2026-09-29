<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Dto;

use Modules\Manifest\Domain\Enums\ManifestInputSource;

final readonly class ManifestParcelInputDto
{
    /** @param list<string> $identifiers */
    public function __construct(public int $expectedVersion, public array $identifiers, public ManifestInputSource $inputSource) {}

    public static function fromArray(array $input): self
    {
        return new self((int) $input['expected_version'], array_values(array_map('strval', $input['identifiers'])), ManifestInputSource::from($input['input_source']));
    }
}
