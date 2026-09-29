<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

use Modules\Foundation\Domain\Enums\ApiErrorCode;

final readonly class PolygonValidationFailureDto
{
    public function __construct(public ApiErrorCode $code, public string $message, public ?string $reasonCode = null) {}
}
