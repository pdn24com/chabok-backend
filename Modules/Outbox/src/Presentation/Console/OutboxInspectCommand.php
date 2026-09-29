<?php

declare(strict_types=1);

namespace Modules\Outbox\Presentation\Console;

use Illuminate\Console\Command;
use Modules\Outbox\Application\UseCases\InspectEvents\InspectEventsCommand;
use Modules\Outbox\Application\UseCases\InspectEvents\InspectEventsHandler;

final class OutboxInspectCommand extends Command
{
    protected $signature = 'chabok:outbox:inspect {--state=} {--limit=50}';

    protected $description = 'Inspect safe outbox lifecycle metadata without payloads.';

    public function handle(InspectEventsHandler $handler): int
    {
        $rows = $handler->handle(new InspectEventsCommand($this->option('state') === null ? null : (string) $this->option('state'), (int) $this->option('limit')))->data->toArray();
        $this->table(array_keys($rows[0] ?? ['event_id' => null]), $rows);

        return self::SUCCESS;
    }
}
