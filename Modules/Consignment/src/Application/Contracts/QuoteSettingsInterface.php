<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Contracts;

interface QuoteSettingsInterface
{
    public function quoteTtlSeconds(): int;
}
