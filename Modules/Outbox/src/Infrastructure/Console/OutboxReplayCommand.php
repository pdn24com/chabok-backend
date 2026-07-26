<?php

declare(strict_types=1);

namespace Modules\Outbox\Infrastructure\Console;

use Illuminate\Console\Command;
use Modules\Outbox\Application\OutboxProcessor;

final class OutboxReplayCommand extends Command
{
    protected $signature = 'chabok:outbox:replay {event_id}';

    protected $description = 'Reset one failed/dead-lettered event for controlled replay.';

    public function handle(OutboxProcessor $processor): int
    {
        $processor->replay((string) $this->argument('event_id'));
        $this->info('Outbox event scheduled for replay.');

        return self::SUCCESS;
    }
}
