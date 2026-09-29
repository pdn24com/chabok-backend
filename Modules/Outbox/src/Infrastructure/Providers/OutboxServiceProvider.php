<?php

declare(strict_types=1);

namespace Modules\Outbox\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Outbox\Application\Contracts\OutboxClaimServiceInterface;
use Modules\Outbox\Application\Contracts\OutboxEventSchemaRegistryInterface;
use Modules\Outbox\Application\Contracts\OutboxSettingsInterface;
use Modules\Outbox\Application\Contracts\PublicationRecorderInterface;
use Modules\Outbox\Application\Contracts\ReadinessProbeInterface;
use Modules\Outbox\Application\Contracts\WorkerRuntimeInterface;
use Modules\Outbox\Application\Repositories\OutboxEventRepositoryInterface;
use Modules\Outbox\Application\Services\OutboxClaimService;
use Modules\Outbox\Application\Services\OutboxEventSchemaRegistry;
use Modules\Outbox\Application\Services\PublicationRecorder;
use Modules\Outbox\Infrastructure\Adapters\LaravelOutboxSettings;
use Modules\Outbox\Infrastructure\Adapters\LaravelReadinessProbe;
use Modules\Outbox\Infrastructure\Adapters\LaravelWorkerRuntime;
use Modules\Outbox\Infrastructure\Persistence\MySqlOutboxWriter;
use Modules\Outbox\Infrastructure\Repositories\EloquentOutboxEventRepository;
use Modules\Outbox\Presentation\Console\OutboxInspectCommand;
use Modules\Outbox\Presentation\Console\OutboxReplayCommand;
use Modules\Outbox\Presentation\Console\OutboxWorkCommand;

final class OutboxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OutboxEventRepositoryInterface::class, EloquentOutboxEventRepository::class);
        $this->app->singleton(OutboxWriterInterface::class, MySqlOutboxWriter::class);
        $this->app->singleton(OutboxSettingsInterface::class, LaravelOutboxSettings::class);
        $this->app->singleton(WorkerRuntimeInterface::class, LaravelWorkerRuntime::class);
        $this->app->singleton(ReadinessProbeInterface::class, LaravelReadinessProbe::class);
        $this->app->singleton(OutboxEventSchemaRegistry::class);
        $this->app->bind(OutboxEventSchemaRegistryInterface::class, OutboxEventSchemaRegistry::class);
        $this->app->bind(PublicationRecorderInterface::class, PublicationRecorder::class);
        $this->app->bind(OutboxClaimServiceInterface::class, OutboxClaimService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
        $this->app->register(RouteServiceProvider::class);
        if ($this->app->runningInConsole()) {
            $this->commands([OutboxWorkCommand::class, OutboxReplayCommand::class, OutboxInspectCommand::class]);
        }
    }
}
