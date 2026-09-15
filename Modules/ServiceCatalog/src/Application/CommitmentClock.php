<?php
declare(strict_types=1);
namespace Modules\ServiceCatalog\Application;

use Carbon\CarbonImmutable;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\ApiErrorCode;

/** Pure temporal rules; a holiday source can be supplied once its ownership is decided. */
final class CommitmentClock
{
    public function resolve(array $policy, array $windows, array $context, string $timezone, bool $includeHolidays, bool $requireSelection, string $kind, ?callable $isHoliday = null): array
    {
        $mode = $policy['mode'];
        if ($mode === 'NONE') return ['mode' => 'NONE'];
        $calculation = $policy['calculation'] ?? 'ELAPSED';
        if ((!$includeHolidays || $calculation === 'BUSINESS_DAY_END') && $isHoliday === null) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'منبع تقویم تعطیلات هنوز تعیین نشده است.', details: ['reason_code' => 'SLA_CALENDAR_UNAVAILABLE']);
        }
        $anchorName = $policy['anchor'] ?? 'CONSIGNMENT_CREATED';
        $anchorValue = match ($anchorName) {
            'PICKUP_COMPLETED' => $context['pickup_completed_at'] ?? null,
            'PICKUP_COMMITMENT_START' => $context['pickup_starts_at'] ?? null,
            'PICKUP_COMMITMENT_END' => $context['pickup_ends_at'] ?? null,
            default => $context['acceptance_at'] ?? CarbonImmutable::now()->toISOString(),
        };
        if ($anchorValue === null) return ['risk_threshold_minutes' => (int)($policy['risk_threshold_minutes']??120), 'mode' => $mode, 'anchor' => $anchorName, 'awaiting_operation' => true, 'policy' => $policy, 'computed_at' => null];
        $anchor = CarbonImmutable::parse($anchorValue)->setTimezone($timezone);
        if ($mode === 'COMPUTED' && $calculation === 'ELAPSED') {
            $seconds = (int) $policy['duration_value'] * match ($policy['duration_unit'] ?? 'HOUR') { 'MINUTE'=>60, 'DAY'=>86400, default=>3600 };
            $end = $anchor;
            $skipped=0;
            if ($includeHolidays) { $end=$anchor->addSeconds($seconds); $seconds=0; }
            while ($seconds > 0) {
                if (!$includeHolidays && $isHoliday($end)) { if(++$skipped>36600) throw new \LogicException('Calendar contains no operating day.'); $end = $end->addDay()->startOfDay(); continue; }
                $next = $end->addDay()->startOfDay();
                $available = $end->diffInSeconds($next);
                $take = min($available, $seconds); $end = $end->addSeconds($take); $seconds -= $take;
            }
            return ['risk_threshold_minutes'=>(int)($policy['risk_threshold_minutes']??120),'mode'=>$mode,'anchor'=>$anchorName,'duration_value'=>$policy['duration_value'],'duration_unit'=>$policy['duration_unit'],'computed_at'=>$end->utc()->toISOString(),'awaiting_operation'=>false];
        }
        $date = $anchor->startOfDay();
        // Pickup windows are selected for an explicit requested local service date.
        if ($kind === 'PICKUP' && !empty($context['pickup_service_date'])) $date = CarbonImmutable::parse($context['pickup_service_date'], $timezone)->startOfDay();
        $offset = (int) ($policy['day_offset'] ?? 0);
        for ($i=0; $i<$offset; $i++) {
            $date = $date->addDay();
            if ($calculation === 'BUSINESS_DAY_END' || !$includeHolidays) {
                $guard=0; while ($isHoliday($date)) { if(++$guard>366) throw new \LogicException('Calendar contains no operating day.'); $date=$date->addDay(); }
            }
        }
        if ($offset === 0 && ($calculation === 'BUSINESS_DAY_END' || !$includeHolidays)) { $guard=0; while($isHoliday($date)) { if(++$guard>366) throw new \LogicException('Calendar contains no operating day.'); $date=$date->addDay(); } }
        if ($mode === 'COMPUTED') return ['risk_threshold_minutes'=>(int)($policy['risk_threshold_minutes']??120),'mode'=>$mode,'anchor'=>$anchorName,'calculation'=>$calculation,'computed_at'=>$date->endOfDay()->utc()->toISOString(),'awaiting_operation'=>false];
        $candidates=[];
        foreach ($windows as $window) {
            if ($window['window_type'] !== $kind || !$window['active'] || (!empty($policy['window_codes']) && !in_array($window['window_code'],$policy['window_codes'],true))) continue;
            if (!in_array($date->dayOfWeekIso, array_map('intval',$window['applicable_weekdays']),true)) continue;
            if (!$includeHolidays && $isHoliday($date)) continue;
            $start=CarbonImmutable::parse($date->toDateString().' '.$window['start_time'],$timezone);
            $end=CarbonImmutable::parse($date->toDateString().' '.$window['end_time'],$timezone);
            $cutoff=CarbonImmutable::parse($date->toDateString().' '.$window['booking_cutoff_time'],$timezone);
            $booking=CarbonImmutable::parse($context['acceptance_at'] ?? CarbonImmutable::now()->toISOString());
            if($booking->greaterThan($cutoff) || $end->lessThanOrEqualTo($anchor)) continue;
            $candidates[]=['risk_threshold_minutes'=>(int)($window['risk_threshold_minutes']??120),'window_code'=>$window['window_code'],'window_type'=>$kind,'label_fa'=>$window['label_fa'],'service_date'=>$date->toDateString(),'starts_at'=>$start->utc()->toISOString(),'ends_at'=>$end->utc()->toISOString(),'booking_cutoff_at'=>$cutoff->utc()->toISOString(),'timezone'=>$timezone,'day_offset'=>$offset];
        }
        $code=$context[strtolower($kind).'_window_code'] ?? null;
        $selected=null; foreach($candidates as $candidate) if($candidate['window_code']===$code) $selected=$candidate;
        // An explicit single destination window is itself the promise, not another required selection.
        if($selected===null && !$code && count($candidates)===1 && $kind==='DELIVERY') $selected=$candidates[0];
        if($code && !$selected) throw new ApiException(ApiErrorCode::ValidationError,422,'بازه انتخاب‌شده در این تاریخ یا پس از ساعت برش قابل استفاده نیست.',details:['reason_code'=>$kind.'_WINDOW_INVALID']);
        if($requireSelection && !$selected) throw new ApiException(ApiErrorCode::ValidationError,422,'بازه قابل استفاده را انتخاب کنید.',details:['reason_code'=>$kind.'_WINDOW_REQUIRED']);
        return ['risk_threshold_minutes'=>(int)($policy['risk_threshold_minutes']??120),'mode'=>$mode,'anchor'=>$anchorName,'windows'=>$candidates,'selected'=>$selected,...($selected ?? [])];
    }
}
