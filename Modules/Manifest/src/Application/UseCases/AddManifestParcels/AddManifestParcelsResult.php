<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\AddManifestParcels;

use Modules\Manifest\Application\Dto\ManifestDetailDto;
use Modules\Manifest\Application\Dto\ManifestParcelOutcomeDto;

final readonly class AddManifestParcelsResult
{
    /** @param list<ManifestParcelOutcomeDto> $outcomes */
    public function __construct(public ManifestDetailDto $detail, public array $outcomes) {}
}
