<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\ValueObjects;

use Modules\Pricing\Domain\Enums\MatrixValidationCode;

final readonly class MatrixValidationIssue
{
    public function __construct(public MatrixValidationCode $code, public string $field) {}
}
