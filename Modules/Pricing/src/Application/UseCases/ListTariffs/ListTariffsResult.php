<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ListTariffs;

use Modules\Foundation\Application\Data\Page;

final readonly class ListTariffsResult
{
    public function __construct(public Page $data)
    {
    }
}
