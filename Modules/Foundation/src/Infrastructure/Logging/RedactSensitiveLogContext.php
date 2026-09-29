<?php

declare(strict_types=1);

namespace Modules\Foundation\Infrastructure\Logging;

use Illuminate\Log\Logger;
use Modules\Foundation\Application\Support\SensitiveDataRedactor;
use Monolog\LogRecord;

final class RedactSensitiveLogContext
{
    public function __invoke(Logger $logger): void
    {
        $logger->getLogger()->pushProcessor(static fn (LogRecord $record): LogRecord => $record->with(message: SensitiveDataRedactor::message($record->message), context: SensitiveDataRedactor::context($record->context), extra: SensitiveDataRedactor::context($record->extra)));
    }
}
