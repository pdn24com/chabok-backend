<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Consignment\Application\Contracts\ConsignmentAccessGuardInterface;
use Modules\Consignment\Application\Contracts\ConsignmentAggregateProjectorInterface;
use Modules\Consignment\Application\Contracts\ConsignmentDraftInterface;
use Modules\Consignment\Application\Contracts\ConsignmentLedgerAccessInterface;
use Modules\Consignment\Application\Contracts\ConsignmentNumberAllocatorInterface;
use Modules\Consignment\Application\Contracts\ConsignmentNumberRangeDefinitionInterface;
use Modules\Consignment\Application\Contracts\ConsignmentPricingWriterInterface;
use Modules\Consignment\Application\Contracts\ConsignmentQuoteAccessInterface;
use Modules\Consignment\Application\Contracts\ConsignmentQuoteInputInterface;
use Modules\Consignment\Application\Contracts\ConsignmentSettingsInterface;
use Modules\Consignment\Application\Contracts\ConsignmentTimeInterface;
use Modules\Consignment\Application\Contracts\EditPricingImpactInterface;
use Modules\Consignment\Application\Contracts\ManifestConsignmentAccessInterface;
use Modules\Consignment\Application\Contracts\NumberRangeAccessInterface;
use Modules\Consignment\Application\Contracts\OperationalStatusAccessInterface;
use Modules\Consignment\Application\Contracts\PricingQuoteProviderInterface;
use Modules\Consignment\Application\Contracts\PricingServiceInterface;
use Modules\Consignment\Application\Contracts\QuoteBundleStoreInterface;
use Modules\Consignment\Application\Contracts\QuoteSettingsInterface;
use Modules\Consignment\Application\Repositories\ConsignmentPricingRepositoryInterface;
use Modules\Consignment\Application\Repositories\ConsignmentRepositoryInterface;
use Modules\Consignment\Application\Repositories\CustodyEventRepositoryInterface;
use Modules\Consignment\Application\Repositories\NumberRangeRepositoryInterface;
use Modules\Consignment\Application\Repositories\OperationalStatusRepositoryInterface;
use Modules\Consignment\Application\Repositories\ParcelRepositoryInterface;
use Modules\Consignment\Application\Repositories\StatusEventRepositoryInterface;
use Modules\Consignment\Application\Services\ConsignmentAccessGuard;
use Modules\Consignment\Application\Services\ConsignmentAggregateProjector;
use Modules\Consignment\Application\Services\ConsignmentDraft;
use Modules\Consignment\Application\Services\ConsignmentNumberAllocator;
use Modules\Consignment\Application\Services\ConsignmentNumberRangeDefinition;
use Modules\Consignment\Application\Services\ConsignmentPricingWriter;
use Modules\Consignment\Application\Services\ConsignmentQuoteAccess;
use Modules\Consignment\Application\Services\ConsignmentQuoteInput;
use Modules\Consignment\Application\Services\ConsignmentTime;
use Modules\Consignment\Application\Services\EditPricingImpact;
use Modules\Consignment\Application\Services\NumberRangeAccess;
use Modules\Consignment\Application\Services\OperationalStatusAccess;
use Modules\Consignment\Application\Services\PricingService;
use Modules\Consignment\Infrastructure\Adapters\ConsignmentPricingTarget;
use Modules\Consignment\Infrastructure\Persistence\EloquentConsignmentLedgerAccess;
use Modules\Consignment\Infrastructure\Persistence\LaravelConsignmentSettings;
use Modules\Consignment\Infrastructure\Pricing\InternalPricingAdapter;
use Modules\Consignment\Infrastructure\Pricing\LaravelQuoteSettings;
use Modules\Consignment\Infrastructure\Pricing\LegacyCorePricingAdapter;
use Modules\Consignment\Infrastructure\Pricing\RedisQuoteBundleStore;
use Modules\Consignment\Infrastructure\Repositories\EloquentConsignmentPricingRepository;
use Modules\Consignment\Infrastructure\Repositories\EloquentConsignmentRepository;
use Modules\Consignment\Infrastructure\Repositories\EloquentCustodyEventRepository;
use Modules\Consignment\Infrastructure\Repositories\EloquentManifestConsignmentAccess;
use Modules\Consignment\Infrastructure\Repositories\EloquentNumberRangeRepository;
use Modules\Consignment\Infrastructure\Repositories\EloquentOperationalStatusRepository;
use Modules\Consignment\Infrastructure\Repositories\EloquentParcelRepository;
use Modules\Consignment\Infrastructure\Repositories\EloquentStatusEventRepository;
use Modules\Pricing\Application\Ports\PricingTargetLookupInterface;

final class ConsignmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ConsignmentPricingRepositoryInterface::class, EloquentConsignmentPricingRepository::class);
        $this->app->bind(OperationalStatusRepositoryInterface::class, EloquentOperationalStatusRepository::class);
        $this->app->bind(NumberRangeRepositoryInterface::class, EloquentNumberRangeRepository::class);
        $this->app->bind(CustodyEventRepositoryInterface::class, EloquentCustodyEventRepository::class);
        $this->app->bind(StatusEventRepositoryInterface::class, EloquentStatusEventRepository::class);
        $this->app->bind(ParcelRepositoryInterface::class, EloquentParcelRepository::class);
        $this->app->bind(ConsignmentRepositoryInterface::class, EloquentConsignmentRepository::class);
        $this->app->bind(ConsignmentNumberRangeDefinitionInterface::class, ConsignmentNumberRangeDefinition::class);
        $this->app->bind(PricingTargetLookupInterface::class, ConsignmentPricingTarget::class);
        $this->app->singleton(ManifestConsignmentAccessInterface::class, EloquentManifestConsignmentAccess::class);
        $this->app->singleton(ConsignmentSettingsInterface::class, LaravelConsignmentSettings::class);
        $this->app->singleton(QuoteSettingsInterface::class, LaravelQuoteSettings::class);
        $this->app->singleton(ConsignmentLedgerAccessInterface::class, EloquentConsignmentLedgerAccess::class);
        $this->app->singleton(PricingQuoteProviderInterface::class, static fn ($app) => config('chabok.pricing.provider', 'legacy') === 'internal' ? $app->make(InternalPricingAdapter::class) : $app->make(LegacyCorePricingAdapter::class));
        $this->app->singleton(QuoteBundleStoreInterface::class, RedisQuoteBundleStore::class);
        $this->app->bind(EditPricingImpactInterface::class, EditPricingImpact::class);
        $this->app->bind(ConsignmentAggregateProjectorInterface::class, ConsignmentAggregateProjector::class);
        $this->app->bind(PricingServiceInterface::class, PricingService::class);
        $this->app->bind(ConsignmentPricingWriterInterface::class, ConsignmentPricingWriter::class);
        $this->app->bind(ConsignmentDraftInterface::class, ConsignmentDraft::class);
        $this->app->bind(ConsignmentAccessGuardInterface::class, ConsignmentAccessGuard::class);
        $this->app->bind(ConsignmentQuoteAccessInterface::class, ConsignmentQuoteAccess::class);
        $this->app->bind(ConsignmentQuoteInputInterface::class, ConsignmentQuoteInput::class);
        $this->app->bind(OperationalStatusAccessInterface::class, OperationalStatusAccess::class);
        $this->app->bind(NumberRangeAccessInterface::class, NumberRangeAccess::class);
        $this->app->bind(ConsignmentTimeInterface::class, ConsignmentTime::class);
        $this->app->bind(ConsignmentNumberAllocatorInterface::class, ConsignmentNumberAllocator::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
        $this->app->register(RouteServiceProvider::class);
    }
}
