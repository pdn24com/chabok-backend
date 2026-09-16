<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\RejectManifestException;

final readonly class RejectManifestExceptionResult
{
    public function __construct(public array $data)
    {
    }
}
