<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Serialization;

use Modules\Pricing\Domain\ValueObjects\MatrixValidationIssue;

final class MatrixValidationSerializer
{
    /** @param list<MatrixValidationIssue> $issues @return list<array{code:string,field:string}> */
    public static function serialize(array $issues): array
    {
        return array_map(static fn (MatrixValidationIssue $issue): array => ['code' => $issue->code->value, 'field' => $issue->field], $issues);
    }
}
