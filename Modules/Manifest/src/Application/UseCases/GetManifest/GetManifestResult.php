<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\GetManifest;

final readonly class GetManifestResult
{
    public function __construct(public array $data)
    {
    }
}
