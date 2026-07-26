<?php

declare(strict_types=1);

namespace Modules\Foundation\Infrastructure\Logging;

use Illuminate\Log\Logger as LaravelLogger;
use Monolog\LogRecord;
use Modules\Foundation\Application\SensitiveDataRedactor;

final class RedactSensitiveLogContext
{
    public function __invoke(LaravelLogger $logger): void
    {
        $logger->getLogger()->pushProcessor(
            static fn (LogRecord $record): LogRecord => $record->with(
                message: SensitiveDataRedactor::message($record->message),
                context: SensitiveDataRedactor::context($record->context),
                extra: SensitiveDataRedactor::context($record->extra),
            ),
        );
    }
}
