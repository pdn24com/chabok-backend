<?php

declare(strict_types=1);

namespace Modules\Outbox\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Outbox\Infrastructure\Persistence\MySqlOutboxWriter;

final class OutboxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OutboxWriter::class, MySqlOutboxWriter::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
    }
}
