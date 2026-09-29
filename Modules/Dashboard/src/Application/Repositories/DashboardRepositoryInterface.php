<?php

declare(strict_types=1);

namespace Modules\Dashboard\Application\Repositories;

use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;
use Modules\Dashboard\Application\Dto\ConsignmentCountsDto;
use Modules\Dashboard\Application\Dto\DashboardUpdateDto;
use Modules\Dashboard\Application\Dto\DriverCountsDto;
use Modules\Dashboard\Application\Dto\ManifestCountsDto;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;

interface DashboardRepositoryInterface
{
    public function consignmentCounts(string $hqId, string $nodeId): ConsignmentCountsDto;

    public function manifestCounts(string $hqId, string $nodeId): ManifestCountsDto;

    public function driverCounts(string $hqId, string $nodeId): DriverCountsDto;

    /** @return list<ConsignmentRecord> */
    public function exceptionConsignments(string $hqId, string $nodeId, int $limit): array;

    /** @return list<ManifestRecord> */
    public function failedManifests(string $hqId, string $nodeId, int $limit): array;

    /** @return list<DashboardUpdateDto> */
    public function consignmentUpdates(string $hqId, string $nodeId, int $limit): array;

    /** @return list<DashboardUpdateDto> */
    public function manifestUpdates(string $hqId, string $nodeId, int $limit): array;
}
