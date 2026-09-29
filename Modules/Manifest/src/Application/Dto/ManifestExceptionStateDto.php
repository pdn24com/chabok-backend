<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Dto;

use Illuminate\Database\Eloquent\Collection;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;

final readonly class ManifestExceptionStateDto
{
    public function __construct(public ManifestRecord $manifest, public Collection $cases) {}
}
