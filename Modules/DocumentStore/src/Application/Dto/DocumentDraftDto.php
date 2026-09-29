<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Application\Dto;

use DateTimeImmutable;

/**
 * A new document as the form sends it, with the records it is attached to. A document is registered in
 * one write together with its links, so nothing is ever filed without the pipeline knowing what it is for.
 */
final readonly class DocumentDraftDto
{
    public function __construct(
        public string $title,
        /** Free text: the list of access classifications is still an open decision. */
        public string $classification,
        public ?string $categoryId = null,
        public ?string $referenceNo = null,
        public ?DateTimeImmutable $expiresOn = null,
        /** @var list<DocumentLinkDto> */
        public array $links = [],
    ) {}
}
