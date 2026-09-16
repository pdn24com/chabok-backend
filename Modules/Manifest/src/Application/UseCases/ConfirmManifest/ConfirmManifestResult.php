<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\ConfirmManifest;

final readonly class ConfirmManifestResult
{
    public function __construct(public array $data)
    {
    }
}
