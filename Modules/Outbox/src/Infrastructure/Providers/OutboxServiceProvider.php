<?php

declare(strict_types=1);

namespace Modules\Outbox\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Foundation\Application\Contracts\OutboxEventPublisher;
use Modules\Outbox\Application\OutboxProcessor;
use Modules\Outbox\Application\OutboxHealthService;
use Modules\Outbox\Application\OutboxEventSchemaRegistry;
use Modules\Outbox\Presentation\Console\OutboxInspectCommand;
use Modules\Outbox\Presentation\Console\OutboxReplayCommand;
use Modules\Outbox\Presentation\Console\OutboxWorkCommand;
use Modules\Outbox\Infrastructure\Persistence\MySqlOutboxWriter;
use Modules\Outbox\Infrastructure\Publishing\FailClosedOutboxEventPublisher;

final class OutboxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OutboxWriter::class, MySqlOutboxWriter::class);
        $this->app->bindIf(OutboxEventPublisher::class, FailClosedOutboxEventPublisher::class);
        $this->app->singleton(\Modules\Outbox\Application\Contracts\OutboxSettings::class, \Modules\Outbox\Infrastructure\Adapters\LaravelOutboxSettings::class);
        $this->app->singleton(\Modules\Outbox\Application\Contracts\WorkerRuntime::class, \Modules\Outbox\Infrastructure\Adapters\LaravelWorkerRuntime::class);
        $this->app->singleton(\Modules\Outbox\Application\Contracts\ReadinessProbe::class, \Modules\Outbox\Infrastructure\Adapters\LaravelReadinessProbe::class);
        $this->app->singleton(\Modules\Outbox\Application\Repositories\OutboxEventRepository::class, \Modules\Outbox\Infrastructure\Repositories\EloquentOutboxEventRepository::class);
        $this->app->when([
            \Modules\Outbox\Application\Services\OutboxClaimService::class,
            \Modules\Outbox\Application\Services\PublicationRecorder::class,
            \Modules\Outbox\Application\UseCases\ReplayEvent\ReplayEventHandler::class,
        ])->needs(\Modules\Foundation\Application\Contracts\TransactionManager::class)->give(\Modules\Outbox\Infrastructure\Persistence\SingleAttemptTransactionManager::class);
        $this->app->singleton(OutboxProcessor::class);
        $this->app->singleton(OutboxHealthService::class);
        $this->app->singleton(OutboxEventSchemaRegistry::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3) . '/database/migrations');
        $this->app->register(RouteServiceProvider::class);
        if ($this->app->runningInConsole()) {
            $this->commands([OutboxWorkCommand::class, OutboxReplayCommand::class, OutboxInspectCommand::class]);
        }
    }
}
