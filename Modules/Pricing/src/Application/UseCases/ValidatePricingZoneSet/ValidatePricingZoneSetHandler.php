<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ValidatePricingZoneSet;

use Carbon\CarbonImmutable;
use Modules\Pricing\Application\Contracts\PricingAccessGuardInterface;
use Modules\Pricing\Application\Contracts\PricingReaderInterface;
use Modules\Pricing\Application\Contracts\PricingVersionGuardInterface;
use Modules\Pricing\Application\Contracts\PricingZoneGuardInterface;
use Modules\Pricing\Application\Dto\PricingVersionPeriodDto;
use Modules\Pricing\Application\Mappers\PricingZoneInput;
use Modules\Pricing\Domain\Enums\PricingResource;
use Modules\Pricing\Domain\Enums\PricingValidationCode;
use Modules\Pricing\Domain\Validators\ZoneMembershipValidator;
use Modules\Pricing\Domain\ValueObjects\PricingValidationIssue;
use Modules\Pricing\Domain\ValueObjects\PricingValidationResult;
use Modules\Pricing\Domain\ValueObjects\ZoneMembership;

final readonly class ValidatePricingZoneSetHandler
{
    public function __construct(
        private PricingAccessGuardInterface $pricingAccessGuard,
        private PricingReaderInterface $pricingReader,
        private PricingZoneGuardInterface $pricingZoneGuard,
        private PricingVersionGuardInterface $pricingVersionGuard,
        private ZoneMembershipValidator $zoneMembershipValidator,
    ) {}

    public function handle(ValidatePricingZoneSetCommand $command): PricingValidationResult
    {
        $actor = $command->actor;
        $versionId = $command->versionId;
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.tariff.manage_draft');
        $version = $this->pricingReader->zoneVersion($actor, $versionId);
        $zones = PricingZoneInput::fromRecords($version->zones);
        $errors = [];
        if ($this->pricingZoneGuard->inspectPolygons($zones) !== null) {
            $errors[] = new PricingValidationIssue(PricingValidationCode::POLYGON_INVALID_OR_OVERLAPPING, 'zones');
        }
        if (! $version->valid_from) {
            $errors[] = new PricingValidationIssue(PricingValidationCode::VALID_FROM_REQUIRED, 'valid_from');
        }
        if ($version->valid_from && $version->valid_to && $version->valid_to <= $version->valid_from) {
            $errors[] = new PricingValidationIssue(PricingValidationCode::EFFECTIVE_INTERVAL_INVALID, 'valid_to');
        }
        if ($this->pricingVersionGuard->hasVersionOverlap(PricingResource::ZoneSets, new PricingVersionPeriodDto($version->zone_set_version_id, $version->pricing_zone_set_id, (int) $version->version_number, $version->valid_from === null ? null : CarbonImmutable::parse($version->valid_from), $version->valid_to === null ? null : CarbonImmutable::parse($version->valid_to)))) {
            $errors[] = new PricingValidationIssue(PricingValidationCode::EFFECTIVE_INTERVAL_OVERLAP, 'valid_from');
        }
        if ($version->zones->isEmpty()) {
            $errors[] = new PricingValidationIssue(PricingValidationCode::ZONE_REQUIRED, 'zones');
        }
        $members = [];
        foreach ($zones as $zone) {
            foreach ($zone->members as $member) {
                $members[] = new ZoneMembership($zone->id, $member->type, $member->reference, $member->rangeEnd, $member->cityId, $member->provinceId);
            }
        }
        if ($this->zoneMembershipValidator->ambiguous($members)) {
            $errors[] = new PricingValidationIssue(PricingValidationCode::ZONE_AMBIGUOUS, 'zones');
        }

        return new PricingValidationResult($errors);
    }
}
