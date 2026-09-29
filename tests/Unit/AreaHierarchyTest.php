<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Foundation\Domain\ValueObjects\AreaEdge;
use Modules\Foundation\Domain\ValueObjects\AreaHierarchy;
use PHPUnit\Framework\TestCase;

final class AreaHierarchyTest extends TestCase
{
    public function test_descendants_are_unique_and_a_cycle_never_returns_the_root(): void
    {
        $hierarchy = new AreaHierarchy([
            new AreaEdge('75576469', '212432914'), new AreaEdge('75576469', '65158786'),
            new AreaEdge('212432914', '167317858'), new AreaEdge('65158786', '167317858'),
            new AreaEdge('167317858', '75576469'), new AreaEdge('227711138', 'unrelated'),
        ]);
        self::assertSame(['212432914', '65158786', '167317858'], $hierarchy->descendants('75576469'));
        self::assertSame([], $hierarchy->descendants('missing'));
    }

    public function test_deep_hierarchies_do_not_depend_on_database_recursion_limits(): void
    {
        $edges = [];
        for ($i = 0; $i < 1500; $i++) {
            $edges[] = new AreaEdge('area-'.$i, 'area-'.($i + 1));
        }
        $descendants = (new AreaHierarchy($edges))->descendants('area-0');
        self::assertCount(1500, $descendants);
        self::assertSame('area-1500', $descendants[1499]);
    }
}
