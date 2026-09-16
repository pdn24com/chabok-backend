<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\GetManifestExceptionState;

final readonly class GetManifestExceptionStateResult
{
    public function __construct(public array $data)
    {
    }
}
