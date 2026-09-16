<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\ValidateManifest;

final readonly class ValidateManifestResult
{
    public function __construct(public array $data)
    {
    }
}
