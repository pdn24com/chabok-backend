<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\UpdateManifest;

final readonly class UpdateManifestResult
{
    public function __construct(public array $data)
    {
    }
}
