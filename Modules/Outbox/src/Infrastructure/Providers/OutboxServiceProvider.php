<?php

declare(strict_types=1);

namespace Modules\Outbox\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Foundation\Application\Contracts\OutboxEventPublisher;
use Modules\Outbox\Application\OutboxProcessor;
use Modules\Outbox\Application\OutboxHealthService;
use Modules\Outbox\Application\OutboxEventSchemaRegistry;
use Modules\Outbox\Infrastructure\Console\OutboxInspectCommand;
use Modules\Outbox\Infrastructure\Console\OutboxReplayCommand;
use Modules\Outbox\Infrastructure\Console\OutboxWorkCommand;
use Modules\Outbox\Infrastructure\Persistence\MySqlOutboxWriter;
use Modules\Outbox\Infrastructure\Publishing\FailClosedOutboxEventPublisher;

final class OutboxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OutboxWriter::class, MySqlOutboxWriter::class);
        $this->app->bindIf(OutboxEventPublisher::class, FailClosedOutboxEventPublisher::class);
        $this->app->singleton(OutboxProcessor::class);
        $this->app->singleton(OutboxHealthService::class);
        $this->app->singleton(OutboxEventSchemaRegistry::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
        $this->loadRoutesFrom(dirname(__DIR__, 3).'/routes/operational.php');
        if ($this->app->runningInConsole()) {
            $this->commands([
                OutboxWorkCommand::class,
                OutboxReplayCommand::class,
                OutboxInspectCommand::class,
            ]);
        }
    }
}
