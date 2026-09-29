<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\DocumentStore\Application\Contracts\DocumentAccessGuardInterface;
use Modules\DocumentStore\Application\Ports\DocumentResourceDirectoryInterface;
use Modules\DocumentStore\Application\Repositories\DocumentCategoryRepositoryInterface;
use Modules\DocumentStore\Application\Repositories\DocumentLinkRepositoryInterface;
use Modules\DocumentStore\Application\Repositories\DocumentRepositoryInterface;
use Modules\DocumentStore\Application\Services\DocumentAccessGuard;
use Modules\DocumentStore\Infrastructure\Adapters\DocumentResourceDirectory;
use Modules\DocumentStore\Infrastructure\Repositories\EloquentDocumentCategoryRepository;
use Modules\DocumentStore\Infrastructure\Repositories\EloquentDocumentLinkRepository;
use Modules\DocumentStore\Infrastructure\Repositories\EloquentDocumentRepository;

final class DocumentStoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DocumentRepositoryInterface::class, EloquentDocumentRepository::class);
        $this->app->bind(DocumentCategoryRepositoryInterface::class, EloquentDocumentCategoryRepository::class);
        $this->app->bind(DocumentLinkRepositoryInterface::class, EloquentDocumentLinkRepository::class);
        $this->app->bind(DocumentResourceDirectoryInterface::class, DocumentResourceDirectory::class);
        $this->app->bind(DocumentAccessGuardInterface::class, DocumentAccessGuard::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
        $this->app->register(RouteServiceProvider::class);
    }
}
