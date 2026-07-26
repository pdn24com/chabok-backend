<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Contracts;

interface OutboxEventPublisher
{
    /**
     * @param array<string, mixed> $event
     * @return array{provider: string, receipt: string}
     */
    public function publish(array $event): array;
}
