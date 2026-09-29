<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Dto;

final readonly class PublicationReceiptDto
{
    public function __construct(public string $provider, public string $receipt) {}
}
