<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ResolveOfferingCommitment;

use Carbon\CarbonImmutable;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class ResolveOfferingCommitmentHandler
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\Repositories\CommitmentScheduleRepository $schedules,
        private \Modules\ServiceCatalog\Application\CurrentCatalog $currentCatalog,
        private \Modules\ServiceCatalog\Application\Services\SchedulePolicyResolver $schedulePolicyResolver,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\ServiceCatalog\Application\Services\ScheduleWindows $scheduleWindows,
    )
    {
    }

    public function handle(ResolveOfferingCommitmentCommand $command): ResolveOfferingCommitmentResult
    {
        return new ResolveOfferingCommitmentResult($this->execute($command->offeringVersionId, $command->context, $command->requireSelection));
    }

    private function execute(string $offeringVersionId, array $context, bool $requireSelection = true): ?array
    {
        $binding = $this->schedules->offeringBinding($offeringVersionId);
        if ($binding === null) {
            return null;
        }
        $owner = $this->schedules->offeringOwner($offeringVersionId);
        $version = (object) $this->currentCatalog->resolve('commitment-schedules', (string) $binding->commitment_schedule_version_id, $owner);
        if ($version === null) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The bound commitment schedule is not published.', details: ['reason_code' => 'COMMITMENT_SCHEDULE_NOT_PUBLISHED']);
        }
        $scopes = $this->schedules->scopes($version->commitment_schedule_version_id);
        if (!array_filter($scopes, fn($scope) => $scope->scope_type === 'HQ' || in_array($scope->node_id, $context['schedule_node_ids'] ?? [], true))) {
            throw new ApiException(ApiErrorCode::ScopeAccessDenied, 403, 'برنامه تعهد برای محدوده دسترسی شما قابل استفاده نیست.', details: ['reason_code' => 'COMMITMENT_SCOPE_UNAVAILABLE']);
        }
        if (!empty($version->commitment_policy)) {
            return $this->schedulePolicyResolver->resolvePolicy($version, $context, $requireSelection, (string) $owner);
        }
        $serviceDate = (string) ($context['pickup_service_date'] ?? CarbonImmutable::instance($this->clock->now())->setTimezone((string) $version->timezone)->toDateString());
        $pickup = ['mode' => (string) $binding->pickup_mode];
        if ($binding->pickup_mode === 'SELECTABLE_WINDOW') {
            $code = (string) ($context['pickup_window_code'] ?? '');
            $windows = $this->scheduleWindows->pickupWindowOptions((string) $version->commitment_schedule_version_id, (string) $version->timezone, isset($context['pickup_service_date']) ? $serviceDate : null);
            if ($code === '' && $requireSelection) {
                return [
                    'eligible' => false,
                    'reason_code' => 'PICKUP_WINDOW_REQUIRED',
                    'binding' => (array) $binding,
                    'pickup' => null,
                    'delivery' => null,
                ];
            }
            $selected = $code === '' ? null : $this->scheduleWindows->windowInstance((string) $version->commitment_schedule_version_id, 'PICKUP', $code, $serviceDate, (string) $version->timezone);
            $pickup = ['mode' => 'SELECTABLE_WINDOW', 'windows' => $windows, 'selected' => $selected, ...$selected ?? []];
        }
        $delivery = ['mode' => (string) $binding->delivery_mode];
        if ($binding->delivery_mode === 'SELECTABLE_WINDOW') {
            $delivery['windows'] = array_map(fn($window) => $this->scheduleWindows->windowInstance((string) $version->commitment_schedule_version_id, 'DELIVERY', (string) $window->window_code, $serviceDate, (string) $version->timezone), $this->schedules->deliveryWindows($version->commitment_schedule_version_id));
            $selectedCode = (string) ($context['delivery_window_code'] ?? '');
            if ($selectedCode === '' && $requireSelection) {
                return [
                    'eligible' => false,
                    'reason_code' => 'DELIVERY_WINDOW_REQUIRED',
                    'binding' => (array) $binding,
                    'pickup' => $pickup,
                    'delivery' => $delivery,
                ];
            }
            $delivery['selected'] = $selectedCode === '' ? null : $this->scheduleWindows->windowInstance((string) $version->commitment_schedule_version_id, 'DELIVERY', $selectedCode, $serviceDate, (string) $version->timezone);
        } elseif ($binding->delivery_mode === 'COMPUTED') {
            $anchor = match ((string) $binding->duration_anchor) {
                'CONSIGNMENT_CREATED' => CarbonImmutable::parse((string) ($context['acceptance_at'] ?? CarbonImmutable::instance($this->clock->now())->utc()->toISOString()))->utc(),
                'PICKUP_COMMITMENT_START' => isset($pickup['starts_at']) ? CarbonImmutable::parse($pickup['starts_at']) : null,
                'PICKUP_COMMITMENT_END' => isset($pickup['ends_at']) ? CarbonImmutable::parse($pickup['ends_at']) : null,
                default => null,
            };
            $computed = $anchor === null ? null : match ((string) $binding->duration_unit) {
                'MINUTE' => $anchor->addMinutes((int) $binding->duration_value),
                'DAY' => $anchor->addDays((int) $binding->duration_value),
                default => $anchor->addHours((int) $binding->duration_value),
            };
            $delivery += [
                'anchor' => (string) $binding->duration_anchor,
                'duration_value' => $binding->duration_value,
                'duration_unit' => $binding->duration_unit,
                'computed_at' => $computed?->toISOString(),
                'awaiting_operation' => $binding->duration_anchor === 'PICKUP_COMPLETED',
            ];
        }
        return [
            'eligible' => true,
            'reason_code' => null,
            'schedule_version_id' => (string) $version->commitment_schedule_version_id,
            'timezone' => (string) $version->timezone,
            'binding' => (array) $binding,
            'pickup' => $pickup,
            'delivery' => $delivery,
        ];
    }
}
