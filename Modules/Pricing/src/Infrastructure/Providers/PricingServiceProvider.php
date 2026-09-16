<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Pricing\Domain\DeterministicCalculator;

final class PricingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\Modules\Pricing\Application\Repositories\CommitmentZoneRepository::class, \Modules\Pricing\Infrastructure\Repositories\EloquentCommitmentZoneRepository::class);
        $this->app->singleton(\Modules\Pricing\Application\Repositories\ServiceTariffRepository::class, \Modules\Pricing\Infrastructure\Repositories\EloquentServiceTariffRepository::class);
        $this->app->singleton(\Modules\Pricing\Application\Repositories\TariffMatrixRepository::class, \Modules\Pricing\Infrastructure\Repositories\EloquentTariffMatrixRepository::class);
        $this->app->singleton(\Modules\Pricing\Application\Repositories\PricingRepository::class, \Modules\Pricing\Infrastructure\Repositories\EloquentPricingRepository::class);
        $this->app->singleton(\Modules\Pricing\Application\Contracts\MatrixWorkbookStorage::class, \Modules\Pricing\Infrastructure\Workbooks\XlsxMatrixWorkbookStorage::class);
        $this->app->singleton(\Modules\Pricing\Application\Contracts\PricingSettings::class, \Modules\Pricing\Infrastructure\Adapters\LaravelPricingSettings::class);
        $this->app->singleton(\Modules\Pricing\Application\Contracts\ConsignmentQuoteAcceptance::class, \Modules\Pricing\Application\Services\ConsignmentQuoteAcceptor::class);
        $this->app->singleton(\Modules\Pricing\Application\Repositories\PricingAcceptanceRepository::class, \Modules\Pricing\Infrastructure\Repositories\EloquentPricingAcceptanceRepository::class);
        $this->app->singleton(\Modules\ServiceCatalog\Application\Contracts\CommitmentZoneResolver::class, \Modules\Pricing\Application\CommitmentZoneReader::class);
        $this->app->singleton(DeterministicCalculator::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3) . '/database/migrations');
        $this->app->register(RouteServiceProvider::class);
    }
}
