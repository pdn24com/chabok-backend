<?php

declare(strict_types=1);

namespace Modules\Outbox\Infrastructure\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class OutboxInspectCommand extends Command
{
    protected $signature = 'chabok:outbox:inspect {--state=} {--limit=50}';

    protected $description = 'Inspect safe outbox lifecycle metadata without payloads.';

    public function handle(): int
    {
        $rows = DB::table('outbox_events')
            ->when($this->option('state'), fn ($query, $state) => $query->where('publication_state', strtoupper((string) $state)))
            ->orderByDesc('occurred_at')->limit(min(250, (int) $this->option('limit')))
            ->get([
                'event_id', 'hq_id', 'event_type', 'publication_state', 'attempts',
                'next_attempt_at', 'claimed_by', 'claimed_at', 'last_failure_code',
                'published_at', 'dead_lettered_at', 'correlation_id',
            ])->map(fn ($row) => (array) $row)->all();
        $this->table(array_keys($rows[0] ?? ['event_id' => null]), $rows);

        return self::SUCCESS;
    }
}
