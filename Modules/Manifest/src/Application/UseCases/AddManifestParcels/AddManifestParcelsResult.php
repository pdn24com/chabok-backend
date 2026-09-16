<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\AddManifestParcels;

final readonly class AddManifestParcelsResult
{
    public function __construct(public array $data)
    {
    }
}
