<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\ResubmitManifestException;

final readonly class ResubmitManifestExceptionResult
{
    public function __construct(public array $data)
    {
    }
}
