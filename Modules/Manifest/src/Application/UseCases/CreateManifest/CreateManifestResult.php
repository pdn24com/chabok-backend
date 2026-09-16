<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\CreateManifest;

final readonly class CreateManifestResult
{
    public function __construct(public array $data)
    {
    }
}
