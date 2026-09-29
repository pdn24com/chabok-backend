<?php

declare(strict_types=1);

namespace Modules\CrmTask\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\CrmTask\Application\Contracts\TaskAccessGuardInterface;
use Modules\CrmTask\Application\Contracts\TaskValidatorInterface;
use Modules\CrmTask\Application\Repositories\ActivityRepositoryInterface;
use Modules\CrmTask\Application\Repositories\TaskAssignmentEventRepositoryInterface;
use Modules\CrmTask\Application\Repositories\TaskRepositoryInterface;
use Modules\CrmTask\Application\Services\TaskAccessGuard;
use Modules\CrmTask\Application\Validators\TaskValidator;
use Modules\CrmTask\Infrastructure\Repositories\EloquentActivityRepository;
use Modules\CrmTask\Infrastructure\Repositories\EloquentTaskAssignmentEventRepository;
use Modules\CrmTask\Infrastructure\Repositories\EloquentTaskRepository;

final class CrmTaskServiceProvider extends ServiceProvider
{
    /** One repository per table, so a use case injects only the tables it actually touches. */
    private const REPOSITORIES = [
        TaskRepositoryInterface::class => EloquentTaskRepository::class,
        TaskAssignmentEventRepositoryInterface::class => EloquentTaskAssignmentEventRepository::class,
        ActivityRepositoryInterface::class => EloquentActivityRepository::class,
    ];

    public function register(): void
    {
        foreach (self::REPOSITORIES as $contract => $implementation) {
            $this->app->bind($contract, $implementation);
        }
        $this->app->bind(TaskAccessGuardInterface::class, TaskAccessGuard::class);
        $this->app->bind(TaskValidatorInterface::class, TaskValidator::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
        $this->app->register(RouteServiceProvider::class);
    }
}
