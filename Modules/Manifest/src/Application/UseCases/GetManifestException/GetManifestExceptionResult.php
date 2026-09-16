<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\GetManifestException;

final readonly class GetManifestExceptionResult
{
    public function __construct(public array $data)
    {
    }
}
