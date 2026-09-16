<?php

declare(strict_types=1);

namespace Modules\Pricing\Application;

use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Geography\Application\PolygonGeometry;
use Modules\Geography\Domain\PersianSearchNormalizer;
use Modules\ServiceCatalog\Application\Contracts\CommitmentZoneResolver;

final readonly class CommitmentZoneReader implements CommitmentZoneResolver
{
    public function __construct(
        private \Modules\Pricing\Application\Repositories\CommitmentZoneRepository $repository,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private PersianSearchNormalizer $normalizer,
        private PolygonGeometry $polygons,
    )
    {
    }

    public function groups(string $hqId): array
    {
        $rows = $this->repository->availableGroups($hqId, $this->clock->now());
        return array_map(function ($row) use ($hqId) {
            $g = $this->group($hqId, $row->pricing_zone_set_id);
            return [
                'pricing_zone_set_id' => $row->pricing_zone_set_id,
                'zone_set_version_id' => $g['zone_set_version_id'],
                'code' => $row->code,
                'title' => $row->title,
                'zones' => $this->repository->zoneTitles($g['zone_set_version_id']),
            ];
        }, $rows);
    }

    public function group(string $hqId, string $groupId, bool $locking = false): array
    {
        if (!$this->repository->visibleGroup($hqId, $groupId, $locking)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'گروه زون در دسترس نیست.');
        }
        $v = $this->repository->currentVersion($groupId, $this->clock->now());
        if (!$v) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'گروه زون نسخه منتشرشده معتبر ندارد.');
        }
        return [
            'zone_set_id' => $groupId,
            'zone_set_version_id' => $v->zone_set_version_id,
            'zone_codes' => $this->repository->zoneCodes($v->zone_set_version_id),
        ];
    }

    public function destination(string $hqId, string $groupId, array $address): array
    {
        $g = $this->group($hqId, $groupId);
        try {
            [$zone, $match] = $this->resolveZone($g['zone_set_version_id'], $address, 'destination');
        } catch (ApiException $e) {
            if (($e->details['reason_code'] ?? null) !== 'PRICING_ZONE_UNRESOLVED') {
                throw $e;
            }
            $zone = null;
            $match = null;
        }
        return [
            'zone_set_id' => $groupId,
            'zone_set_version_id' => $g['zone_set_version_id'],
            'zone' => $zone,
            'match' => $match,
        ];
    }

    public function resolveZone(string $versionId, array $party, string $partyName): array
    {
        $members = $this->repository->members($versionId);
        $matches = [];
        if (array_filter($members, fn($member) => $member->member_type === 'POLYGON')) {
            foreach (['latitude' => 90, 'longitude' => 180] as $coordinate => $limit) {
                $value = $party[$coordinate] ?? null;
                if (!is_numeric($value) || !is_finite((float) $value) || abs((float) $value) > $limit) {
                    throw new ApiException(ApiErrorCode::PricingZoneUnresolved, 422, 'تعرفهٔ انتخاب‌شده به موقعیت دقیق نیاز دارد؛ موقعیت مبدأ و مقصد را روی نقشه ثبت کنید.', fieldErrors: ["{$partyName}.{$coordinate}" => ['موقعیت دقیق نشانی الزامی است.']], details: ['reason_code' => 'PRICING_COORDINATES_REQUIRED', 'party' => $partyName]);
                }
            }
        }
        foreach ($members as $m) {
            $matchesMember = match ($m->member_type) {
                'EXPLICIT_OVERRIDE' => ($party['zone_override'] ?? null) === $m->reference_value,
                'POSTAL_RANGE' => isset($party['postal_code']) && strcmp((string) $party['postal_code'], (string) $m->reference_value) >= 0 && strcmp((string) $party['postal_code'], (string) $m->range_end) <= 0,
                'CITY' => $m->city_id !== null ? ($party['city_id'] ?? null) === $m->city_id : isset($party['city']) && $this->normalizer->normalize((string) $party['city']) === $this->normalizer->normalize((string) $m->reference_value),
                'POLYGON' => $this->polygons->contains(json_decode($m->geometry, true, 512, JSON_THROW_ON_ERROR), (float) $party['latitude'], (float) $party['longitude']),
                'PROVINCE' => $m->province_id !== null ? ($party['province_id'] ?? null) === $m->province_id : isset($party['state']) && $this->normalizer->normalize((string) $party['state']) === $this->normalizer->normalize((string) $m->reference_value),
                default => false,
            };
            if ($matchesMember) {
                $m->effective_precedence = $this->memberPrecedence((string) $m->member_type);
                $matches[] = $m;
            }
        }
        if ($matches === []) {
            throw new ApiException(ApiErrorCode::PricingZoneUnresolved, 422, 'Pricing zone could not be resolved.', details: ['reason_code' => 'PRICING_ZONE_UNRESOLVED']);
        }
        usort($matches, fn($left, $right) => $right->effective_precedence <=> $left->effective_precedence);
        $top = $matches[0]->effective_precedence;
        $winners = array_values(array_filter($matches, fn($m) => $m->effective_precedence === $top));
        if (count(array_unique(array_map(fn($m) => $m->pricing_zone_id, $winners))) > 1) {
            throw new ApiException(ApiErrorCode::PricingZoneAmbiguous, 422, 'Pricing zone is ambiguous.', details: ['reason_code' => 'PRICING_ZONE_AMBIGUOUS']);
        }
        $winner = $winners[0];
        return [
            [
                'pricing_zone_id' => $winner->pricing_zone_id,
                'code' => $winner->code,
                'title' => $winner->title,
                'rank' => $winner->rank === null ? null : (int) $winner->rank,
                'remote_area' => (bool) $winner->remote_area,
            ],
            [
                'member_id' => $winner->zone_member_id,
                'member_type' => $winner->member_type,
                'precedence' => $winner->effective_precedence,
            ],
        ];
    }

    private function memberPrecedence(string $type): int
    {
        return match ($type) {
            'EXPLICIT_OVERRIDE' => 400,
            'POSTAL_RANGE' => 300,
            'POLYGON' => 250,
            'CITY' => 200,
            'PROVINCE' => 100,
            default => 0,
        };
    }
}
