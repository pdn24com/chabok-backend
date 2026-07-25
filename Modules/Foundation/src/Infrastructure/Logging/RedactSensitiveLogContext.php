<?php

declare(strict_types=1);

namespace Modules\Foundation\Infrastructure\Logging;

use Monolog\LogRecord;
use Monolog\Logger;
use Modules\Foundation\Application\SensitiveDataRedactor;

final class RedactSensitiveLogContext
{
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(
            static fn (LogRecord $record): LogRecord => $record->with(
                message: SensitiveDataRedactor::message($record->message),
                context: SensitiveDataRedactor::context($record->context),
                extra: SensitiveDataRedactor::context($record->extra),
            ),
        );
    }
}
