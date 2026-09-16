<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Contracts;

interface QuoteSettings
{
    public function quoteTtlSeconds(): int;
}
