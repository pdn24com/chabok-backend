<?php

declare(strict_types=1);

namespace Modules\Outbox\Presentation\Console;

use Illuminate\Console\Command;
use Modules\Outbox\Application\UseCases\PublishPendingEvents\PublishPendingEventsHandler;
use Modules\Outbox\Application\UseCases\PublishPendingEvents\PublishPendingEventsCommand;

final class OutboxWorkCommand extends Command
{
    protected $signature = 'chabok:outbox:work
        {--once : Process one batch and exit}
        {--limit=25 : Maximum events per batch}
        {--worker-id= : Stable worker identity}';
    protected $description = 'Claim and publish Chabok transactional outbox events.';

    public function handle(PublishPendingEventsHandler $processor): int
    {
        do {
            $result = $processor->handle(new PublishPendingEventsCommand((int) $this->option('limit'), $this->option('worker-id') ?: null))->data;
            $this->line(json_encode($result, JSON_THROW_ON_ERROR));
            if ($this->option('once')) {
                break;
            }
            if ($result['claimed'] === 0) {
                usleep(500000);
            }
        } while (true);
        return self::SUCCESS;
    }
}
