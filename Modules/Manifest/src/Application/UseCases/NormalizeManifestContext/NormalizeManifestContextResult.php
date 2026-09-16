<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\NormalizeManifestContext;

final readonly class NormalizeManifestContextResult
{
    public function __construct(public array $data)
    {
    }
}
