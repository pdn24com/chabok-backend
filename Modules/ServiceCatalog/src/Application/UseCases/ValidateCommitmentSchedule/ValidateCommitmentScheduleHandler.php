<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ValidateCommitmentSchedule;

use Modules\ServiceCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\ServiceCatalog\Application\Contracts\ScheduleReaderInterface;
use Modules\ServiceCatalog\Application\Repositories\CommitmentScheduleRepositoryInterface;
use Modules\ServiceCatalog\Domain\Enums\CatalogValidationCode;
use Modules\ServiceCatalog\Domain\ValueObjects\CatalogValidationIssue;
use Modules\ServiceCatalog\Domain\ValueObjects\CatalogValidationResult;

final readonly class ValidateCommitmentScheduleHandler
{
    public function __construct(
        private CatalogAccessGuardInterface $catalogAccessGuard,
        private ScheduleReaderInterface $scheduleReader,
        private CommitmentScheduleRepositoryInterface $commitmentScheduleRepository,
    ) {}

    public function handle(ValidateCommitmentScheduleCommand $command): CatalogValidationResult
    {
        $actor = $command->actor;
        $versionId = $command->versionId;
        $automatic = $command->automatic;
        $this->catalogAccessGuard->assertAccess($actor, 'service_catalog.manage_draft');
        $version = $this->scheduleReader->versionDetail($actor, $versionId);
        $errors = [];
        if (empty($version->commitment_policy) && $version->windows->isEmpty()) {
            $errors[] = new CatalogValidationIssue(CatalogValidationCode::CommitmentWindowRequired, 'windows');
        }
        if (empty($version->commitment_policy) && $version->windows->where('window_type', 'PICKUP')->isEmpty()) {
            $errors[] = new CatalogValidationIssue(CatalogValidationCode::PickupWindowRequired, 'windows');
        }
        foreach ($version->windows as $index => $window) {
            if ($window->start_time >= $window->end_time) {
                $errors[] = new CatalogValidationIssue(CatalogValidationCode::CommitmentWindowIntervalInvalid, "windows.{$index}.end_time");
            }
            if ($window->applicable_weekdays === []) {
                $errors[] = new CatalogValidationIssue(CatalogValidationCode::CommitmentWeekdayRequired, "windows.{$index}.applicable_weekdays");
            }
        }
        if ($version->scopes->isEmpty()) {
            $errors[] = new CatalogValidationIssue(CatalogValidationCode::CommitmentScopeRequired, 'scopes');
        }
        if ($version->valid_from && $version->valid_to && $version->valid_to <= $version->valid_from) {
            $errors[] = new CatalogValidationIssue(CatalogValidationCode::CommitmentEffectiveIntervalInvalid, 'valid_to');
        }
        $overlap = $this->commitmentScheduleRepository->hasOverlappingEffectiveVersion((string) $version->commitment_schedule_id, $versionId, $version->valid_from, $version->valid_to);
        if ($overlap && ! $automatic) {
            $errors[] = new CatalogValidationIssue(CatalogValidationCode::CommitmentEffectiveIntervalOverlap, 'valid_from');
        }

        return new CatalogValidationResult($errors);
    }
}
