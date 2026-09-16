<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\GetManifestContextOptions;

final readonly class GetManifestContextOptionsResult
{
    public function __construct(public array $data)
    {
    }
}
