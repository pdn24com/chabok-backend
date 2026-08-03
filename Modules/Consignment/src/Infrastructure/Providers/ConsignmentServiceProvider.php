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
        $this->app->singleton(PricingQuoteProvider::class, static fn ($app) => config('chabok.pricing.provider', 'legacy') === 'internal'
            ? $app->make(InternalPricingAdapter::class)
            : $app->make(LegacyCorePricingAdapter::class));
        $this->app->singleton(QuoteBundleStore::class, RedisQuoteBundleStore::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
        $this->loadRoutesFrom(dirname(__DIR__, 3).'/routes/api.php');
    }
}
