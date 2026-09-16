<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\ListManifestCandidates;

use Modules\Foundation\Application\Data\Page;

final readonly class ListManifestCandidatesResult
{
    public function __construct(public Page $data)
    {
    }
}
