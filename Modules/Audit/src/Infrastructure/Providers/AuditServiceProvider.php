<?php

declare(strict_types=1);

namespace Modules\Audit\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Audit\Application\Repositories\AuditEventRepositoryInterface;
use Modules\Audit\Infrastructure\Persistence\MySqlAuditWriter;
use Modules\Audit\Infrastructure\Repositories\EloquentAuditEventRepository;
use Modules\Foundation\Application\Ports\AuditWriterInterface;

final class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuditWriterInterface::class, MySqlAuditWriter::class);
        $this->app->bind(AuditEventRepositoryInterface::class, EloquentAuditEventRepository::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
    }
}
