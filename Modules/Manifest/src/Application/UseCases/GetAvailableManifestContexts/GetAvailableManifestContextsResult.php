<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\GetAvailableManifestContexts;

final readonly class GetAvailableManifestContextsResult
{
    public function __construct(public array $data)
    {
    }
}
