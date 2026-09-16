<?php

declare(strict_types=1);

namespace Modules\Outbox\Presentation\Console;

use Illuminate\Console\Command;
use Modules\Outbox\Application\UseCases\ReplayEvent\ReplayEventHandler;
use Modules\Outbox\Application\UseCases\ReplayEvent\ReplayEventCommand;

final class OutboxReplayCommand extends Command
{
    protected $signature = 'chabok:outbox:replay {event_id}';
    protected $description = 'Reset one failed/dead-lettered event for controlled replay.';

    public function handle(ReplayEventHandler $processor): int
    {
        $processor->handle(new ReplayEventCommand((string) $this->argument('event_id')));
        $this->info('Outbox event scheduled for replay.');
        return self::SUCCESS;
    }
}
