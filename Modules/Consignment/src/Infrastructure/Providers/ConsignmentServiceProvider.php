<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Consignment\Application\Contracts\PricingQuoteProvider;
use Modules\Consignment\Application\Contracts\QuoteBundleStore;
use Modules\Consignment\Infrastructure\Pricing\LegacyCorePricingAdapter;
use Modules\Consignment\Infrastructure\Pricing\InternalPricingAdapter;
use Modules\Consignment\Infrastructure\Pricing\RedisQuoteBundleStore;

final class ConsignmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\Modules\Consignment\Application\Contracts\ManifestConsignmentAccess::class, \Modules\Consignment\Infrastructure\Repositories\EloquentManifestConsignmentAccess::class);
        $this->app->singleton(\Modules\Consignment\Application\Repositories\ConsignmentRepository::class, \Modules\Consignment\Infrastructure\Repositories\EloquentConsignmentRepository::class);
        $this->app->singleton(\Modules\Consignment\Application\Contracts\ConsignmentSettings::class, \Modules\Consignment\Infrastructure\Persistence\LaravelConsignmentSettings::class);
        $this->app->singleton(\Modules\Consignment\Application\Repositories\PricingImpactReader::class, \Modules\Consignment\Infrastructure\Repositories\SqlPricingImpactReader::class);
        $this->app->singleton(\Modules\Consignment\Application\Contracts\QuoteSettings::class, \Modules\Consignment\Infrastructure\Pricing\LaravelQuoteSettings::class);
        $this->app->singleton(\Modules\Consignment\Application\Repositories\OperationalStatusRepository::class, \Modules\Consignment\Infrastructure\Repositories\EloquentOperationalStatusRepository::class);
        $this->app->when(\Modules\Consignment\Application\UseCases\SaveOperationalStatus\SaveOperationalStatusHandler::class)->needs(\Modules\Foundation\Application\Contracts\TransactionManager::class)->give(fn() => new \Modules\Foundation\Infrastructure\Persistence\LaravelTransactionManager(1));
        $this->app->singleton(\Modules\Consignment\Application\Repositories\NumberRangeRepository::class, \Modules\Consignment\Infrastructure\Repositories\EloquentNumberRangeRepository::class);
        $this->app->singleton(\Modules\Consignment\Application\Repositories\ConsignmentLedgerRepository::class, \Modules\Consignment\Infrastructure\Repositories\EloquentConsignmentLedgerRepository::class);
        $this->app->singleton(PricingQuoteProvider::class, static fn($app) => config('chabok.pricing.provider', 'legacy') === 'internal' ? $app->make(InternalPricingAdapter::class) : $app->make(LegacyCorePricingAdapter::class));
        $this->app->singleton(QuoteBundleStore::class, RedisQuoteBundleStore::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3) . '/database/migrations');
        $this->app->register(RouteServiceProvider::class);
    }
}
