<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Manifest\Application\Dto\ManifestCountsDto;
use Modules\Manifest\Application\Dto\ManifestDetailDto;
use Modules\Manifest\Application\Dto\ManifestListItemDto;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;

interface ManifestReaderInterface
{
    public function listItem(ManifestRecord|ManifestListItemDto $row): ManifestListItemDto;

    public function detail(AuthenticatedPrincipal $actor, string $nodeId, string $id, AccessContextDto $context): ManifestDetailDto;

    public function statusEvents(string $hqId, string $manifestId): Collection;

    public function timeline(string $hqId, string $manifestId): Collection;

    public function visible(AuthenticatedPrincipal $actor, string $nodeId, string $id): ManifestRecord;

    public function locked(AuthenticatedPrincipal $actor, string $nodeId, string $id): ManifestRecord;

    public function counts(string $id): ManifestCountsDto;
}
