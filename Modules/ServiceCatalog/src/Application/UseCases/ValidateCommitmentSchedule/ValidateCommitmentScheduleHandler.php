<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ValidateCommitmentSchedule;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ValidateCommitmentScheduleHandler
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\Services\ScheduleAccessGuard $scheduleAccessGuard,
        private \Modules\ServiceCatalog\Application\Services\ScheduleReader $scheduleReader,
        private \Modules\ServiceCatalog\Application\Repositories\CommitmentScheduleRepository $schedules,
    )
    {
    }

    public function handle(ValidateCommitmentScheduleCommand $command): ValidateCommitmentScheduleResult
    {
        return new ValidateCommitmentScheduleResult($this->execute($command->actor, $command->versionId, $command->automatic));
    }

    private function execute(AuthenticatedPrincipal $actor, string $versionId, bool $automatic = false): array
    {
        $this->scheduleAccessGuard->assertAccess($actor, 'service_catalog.manage_draft');
        $version = $this->scheduleReader->versionDetail($actor, $versionId);
        $errors = [];
        if (empty($version['commitment_policy']) && $version['windows'] === []) {
            $errors[] = ['code' => 'COMMITMENT_WINDOW_REQUIRED', 'field' => 'windows'];
        }
        if (empty($version['commitment_policy']) && !array_filter($version['windows'], fn($window) => $window['window_type'] === 'PICKUP')) {
            $errors[] = ['code' => 'PICKUP_WINDOW_REQUIRED', 'field' => 'windows'];
        }
        foreach ($version['windows'] as $index => $window) {
            if ($window['start_time'] >= $window['end_time']) {
                $errors[] = ['code' => 'COMMITMENT_WINDOW_INTERVAL_INVALID', 'field' => "windows.{$index}.end_time"];
            }
            if ($window['applicable_weekdays'] === []) {
                $errors[] = ['code' => 'COMMITMENT_WEEKDAY_REQUIRED', 'field' => "windows.{$index}.applicable_weekdays"];
            }
        }
        if ($version['scopes'] === []) {
            $errors[] = ['code' => 'COMMITMENT_SCOPE_REQUIRED', 'field' => 'scopes'];
        }
        if ($version['valid_from'] && $version['valid_to'] && $version['valid_to'] <= $version['valid_from']) {
            $errors[] = ['code' => 'COMMITMENT_EFFECTIVE_INTERVAL_INVALID', 'field' => 'valid_to'];
        }
        $overlap = $this->schedules->overlaps($versionId, $version);
        if ($overlap && !$automatic) {
            $errors[] = ['code' => 'COMMITMENT_EFFECTIVE_INTERVAL_OVERLAP', 'field' => 'valid_from'];
        }
        return ['valid' => $errors === [], 'errors' => $errors];
    }
}
