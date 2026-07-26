<?php

declare(strict_types=1);

namespace Modules\Outbox\Infrastructure\Console;

use Illuminate\Console\Command;
use Modules\Outbox\Application\OutboxProcessor;

final class OutboxWorkCommand extends Command
{
    protected $signature = 'chabok:outbox:work
        {--once : Process one batch and exit}
        {--limit=25 : Maximum events per batch}
        {--worker-id= : Stable worker identity}';

    protected $description = 'Claim and publish Chabok transactional outbox events.';

    public function handle(OutboxProcessor $processor): int
    {
        do {
            $result = $processor->runOnce(
                (int) $this->option('limit'),
                $this->option('worker-id') ?: null,
            );
            $this->line(json_encode($result, JSON_THROW_ON_ERROR));
            if ($this->option('once')) {
                break;
            }
            if ($result['claimed'] === 0) {
                usleep(500_000);
            }
        } while (true);

        return self::SUCCESS;
    }
}
