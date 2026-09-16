<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ValidatePricingZoneSet;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiException;

final readonly class ValidatePricingZoneSetHandler
{
    public function __construct(
        private \Modules\Pricing\Application\Services\PricingAccessGuard $pricingAccessGuard,
        private \Modules\Pricing\Application\Services\PricingReader $pricingReader,
        private \Modules\Pricing\Application\Services\PricingZoneGuard $pricingZoneGuard,
        private \Modules\Pricing\Application\Services\PricingVersionGuard $pricingVersionGuard,
        private \Modules\Pricing\Domain\PricingRulePolicy $pricingRulePolicy,
    )
    {
    }

    public function handle(ValidatePricingZoneSetCommand $command): ValidatePricingZoneSetResult
    {
        return new ValidatePricingZoneSetResult($this->execute($command->actor, $command->versionId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $versionId): array
    {
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.tariff.manage_draft');
        $version = $this->pricingReader->zoneVersion($actor, $versionId);
        $errors = [];
        try {
            $this->pricingZoneGuard->validatePolygons($version['zones']);
        } catch (ApiException $exception) {
            $errors[] = ['code' => 'PRICING_POLYGON_INVALID_OR_OVERLAPPING', 'field' => 'zones'];
        }
        if (!$version['valid_from']) {
            $errors[] = ['code' => 'PRICING_VALID_FROM_REQUIRED', 'field' => 'valid_from'];
        }
        if ($version['valid_from'] && $version['valid_to'] && $version['valid_to'] <= $version['valid_from']) {
            $errors[] = ['code' => 'PRICING_EFFECTIVE_INTERVAL_INVALID', 'field' => 'valid_to'];
        }
        if ($this->pricingVersionGuard->hasVersionOverlap('zone-sets', $version)) {
            $errors[] = ['code' => 'PRICING_EFFECTIVE_INTERVAL_OVERLAP', 'field' => 'valid_from'];
        }
        if ($version['zones'] === []) {
            $errors[] = ['code' => 'PRICING_ZONE_REQUIRED', 'field' => 'zones'];
        }
        $members = [];
        foreach ($version['zones'] as $zone) {
            foreach ($zone['members'] as $member) {
                $members[] = [...$member, 'pricing_zone_id' => $zone['pricing_zone_id']];
            }
        }
        $groups = [];
        $ambiguous = false;
        foreach ($members as $member) {
            if ($member['member_type'] === 'POLYGON') {
                continue;
            }
            $key = implode('|', [
                $member['member_type'],
                (string) ($member['city_id'] ?? $member['province_id'] ?? mb_strtolower((string) $member['reference_value'])),
                (string) ($member['range_end'] ?? ''),
                $this->pricingRulePolicy->memberPrecedence((string) $member['member_type']),
            ]);
            $groups[$key][] = $member['pricing_zone_id'];
        }
        foreach ($groups as $group) {
            if (count(array_unique($group)) > 1) {
                $ambiguous = true;
            }
        }
        $postal = array_values(array_filter($members, fn($member) => $member['member_type'] === 'POSTAL_RANGE'));
        foreach ($postal as $index => $left) {
            foreach (array_slice($postal, $index + 1) as $right) {
                if ($left['pricing_zone_id'] !== $right['pricing_zone_id'] && strlen((string) $left['reference_value']) === strlen((string) $right['reference_value']) && strcmp((string) $left['reference_value'], (string) $right['range_end']) <= 0 && strcmp((string) $right['reference_value'], (string) $left['range_end']) <= 0) {
                    $ambiguous = true;
                }
            }
        }
        if ($ambiguous) {
            $errors[] = ['code' => 'PRICING_ZONE_AMBIGUOUS', 'field' => 'zones'];
        }
        return ['valid' => $errors === [], 'errors' => $errors];
    }
}
