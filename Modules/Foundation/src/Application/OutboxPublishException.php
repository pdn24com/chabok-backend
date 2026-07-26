<?php

declare(strict_types=1);

namespace Modules\Foundation\Application;

final class OutboxPublishException extends \RuntimeException
{
    public function __construct(
        public readonly string $failureCode,
        public readonly bool $retryable = true,
    ) {
        parent::__construct('Outbox event publication failed.');
    }
}
