<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Validators;

use Modules\Pricing\Domain\Enums\ZoneMemberType;

final class ZoneMembershipValidator
{
    public function ambiguous(array $members): bool
    {
        foreach ($members as $index => $left) {
            if ($left->type === ZoneMemberType::POLYGON) {
                continue;
            }
            for ($rightIndex = $index + 1; $rightIndex < count($members); $rightIndex++) {
                $right = $members[$rightIndex];
                if ($left->zoneId === $right->zoneId) {
                    continue;
                }
                if ($left->sameReference($right) || $left->overlapsPostalRange($right)) {
                    return true;
                }
            }
        }

        return false;
    }
}
