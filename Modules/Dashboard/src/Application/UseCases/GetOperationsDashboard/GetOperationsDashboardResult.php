<?php

declare(strict_types=1);

namespace Modules\Dashboard\Application\UseCases\GetOperationsDashboard;

use DateTimeImmutable;
use Modules\Dashboard\Application\Dto\ConsignmentCountsDto;
use Modules\Dashboard\Application\Dto\DashboardAttentionDto;
use Modules\Dashboard\Application\Dto\DashboardCapabilitiesDto;
use Modules\Dashboard\Application\Dto\DashboardShortcutDto;
use Modules\Dashboard\Application\Dto\DashboardUpdateDto;
use Modules\Dashboard\Application\Dto\DriverCountsDto;
use Modules\Dashboard\Application\Dto\ManifestCountsDto;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

final readonly class GetOperationsDashboardResult
{
    /** @param list<DashboardAttentionDto> $attention @param list<DashboardUpdateDto> $updates @param list<DashboardShortcutDto> $shortcuts */
    public function __construct(
        public DateTimeImmutable $asOf,
        public NodeRecord $node,
        public DashboardCapabilitiesDto $capabilities,
        public ?ConsignmentCountsDto $consignments,
        public ?ManifestCountsDto $manifests,
        public ?DriverCountsDto $drivers,
        public array $attention,
        public array $updates,
        public array $shortcuts,
    ) {}
}
