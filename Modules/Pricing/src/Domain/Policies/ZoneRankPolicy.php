<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Policies;

final class ZoneRankPolicy
{
    /** Complete ranks are required on the whole version, not only the selected pair. */
    public function valid(array $ranks): bool
    {
        $seen = [];
        foreach ($ranks as $rank) {
            if ($rank === null || ! is_numeric($rank) || (int) $rank != $rank || $rank < 1 || isset($seen[(int) $rank])) {
                return false;
            }
            $seen[(int) $rank] = true;
        }

        return $ranks !== [];
    }
}
