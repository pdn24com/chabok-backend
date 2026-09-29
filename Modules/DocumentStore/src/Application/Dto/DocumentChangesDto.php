<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Application\Dto;

use DateTimeImmutable;

/**
 * A PATCH of one document. The title and the classification cannot be cleared, so a null simply means
 * "left alone"; every field that may be cleared carries its own *Specified flag, so an explicit null is
 * told apart from an absent key. The status is not here: archiving is its own action.
 */
final readonly class DocumentChangesDto
{
    public function __construct(
        public ?string $title = null,
        public ?string $classification = null,
        public ?string $categoryId = null,
        public bool $categorySpecified = false,
        public ?string $referenceNo = null,
        public bool $referenceNoSpecified = false,
        public ?DateTimeImmutable $expiresOn = null,
        public bool $expiresOnSpecified = false,
    ) {}

    public function touchesNothing(): bool
    {
        return $this->title === null && $this->classification === null
            && ! $this->categorySpecified && ! $this->referenceNoSpecified && ! $this->expiresOnSpecified;
    }
}
