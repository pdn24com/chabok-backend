<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\ListManifests;

use Modules\Foundation\Application\Data\Page;

final readonly class ListManifestsResult
{
    public function __construct(public Page $data)
    {
    }
}
