<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Pricing\Domain\DeterministicCalculator;

final class PricingServiceProvider extends ServiceProvider
{
    public function register(): void { $this->app->singleton(\Modules\ServiceCatalog\Application\Contracts\CommitmentZoneResolver::class, \Modules\Pricing\Application\CommitmentZoneReader::class); $this->app->singleton(DeterministicCalculator::class); }
    public function boot(): void { $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations'); $this->loadRoutesFrom(dirname(__DIR__, 3).'/routes/api.php'); }
}
