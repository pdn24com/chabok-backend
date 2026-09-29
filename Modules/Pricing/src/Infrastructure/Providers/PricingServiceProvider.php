<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Pricing\Application\Contracts\ConsignmentQuoteAcceptanceInterface;
use Modules\Pricing\Application\Contracts\ConsignmentQuoteAcceptorInterface;
use Modules\Pricing\Application\Contracts\FreightMatrixRuleCompilerInterface;
use Modules\Pricing\Application\Contracts\MatrixWorkbookPreviewServiceInterface;
use Modules\Pricing\Application\Contracts\MatrixWorkbookStorageInterface;
use Modules\Pricing\Application\Contracts\PricingAccessGuardInterface;
use Modules\Pricing\Application\Contracts\PricingChangeRecorderInterface;
use Modules\Pricing\Application\Contracts\PricingConfigurationWriterInterface;
use Modules\Pricing\Application\Contracts\PricingDraftPreparationInterface;
use Modules\Pricing\Application\Contracts\PricingFactsInterface;
use Modules\Pricing\Application\Contracts\PricingInputInterface;
use Modules\Pricing\Application\Contracts\PricingMatrixMatcherInterface;
use Modules\Pricing\Application\Contracts\PricingReaderInterface;
use Modules\Pricing\Application\Contracts\PricingRuleCalculatorInterface;
use Modules\Pricing\Application\Contracts\PricingSettingsInterface;
use Modules\Pricing\Application\Contracts\PricingVersionGuardInterface;
use Modules\Pricing\Application\Contracts\PricingZoneGuardInterface;
use Modules\Pricing\Application\Contracts\PricingZoneResolverInterface;
use Modules\Pricing\Application\Contracts\PricingZoneWriterInterface;
use Modules\Pricing\Application\Contracts\QuoteCalculatorInterface;
use Modules\Pricing\Application\Contracts\QuoteWriterInterface;
use Modules\Pricing\Application\Contracts\ServiceTariffDependenciesInterface;
use Modules\Pricing\Application\Contracts\TariffMatrixCompilerInterface;
use Modules\Pricing\Application\Repositories\PricingAuditRepositoryInterface;
use Modules\Pricing\Application\Repositories\PricingChargeTypeRepositoryInterface;
use Modules\Pricing\Application\Repositories\PricingQuoteRepositoryInterface;
use Modules\Pricing\Application\Repositories\PricingVersionRepositoryInterface;
use Modules\Pricing\Application\Repositories\PricingZoneRepositoryInterface;
use Modules\Pricing\Application\Repositories\PricingZoneSetRepositoryInterface;
use Modules\Pricing\Application\Repositories\TariffRepositoryInterface;
use Modules\Pricing\Application\Services\ConsignmentQuoteAcceptor;
use Modules\Pricing\Application\Services\FreightMatrixRuleCompiler;
use Modules\Pricing\Application\Services\MatrixWorkbookPreviewService;
use Modules\Pricing\Application\Services\PricingAccessGuard;
use Modules\Pricing\Application\Services\PricingChangeRecorder;
use Modules\Pricing\Application\Services\PricingConfigurationWriter;
use Modules\Pricing\Application\Services\PricingDraftPreparation;
use Modules\Pricing\Application\Services\PricingFacts;
use Modules\Pricing\Application\Services\PricingInput;
use Modules\Pricing\Application\Services\PricingMatrixMatcher;
use Modules\Pricing\Application\Services\PricingReader;
use Modules\Pricing\Application\Services\PricingRuleCalculator;
use Modules\Pricing\Application\Services\PricingVersionGuard;
use Modules\Pricing\Application\Services\PricingZoneGuard;
use Modules\Pricing\Application\Services\PricingZoneResolver;
use Modules\Pricing\Application\Services\PricingZoneWriter;
use Modules\Pricing\Application\Services\QuoteCalculator;
use Modules\Pricing\Application\Services\QuoteWriter;
use Modules\Pricing\Application\Services\ServiceTariffDependencies;
use Modules\Pricing\Application\Services\TariffMatrixCompiler;
use Modules\Pricing\Infrastructure\Adapters\LaravelPricingSettings;
use Modules\Pricing\Infrastructure\Repositories\EloquentPricingAuditRepository;
use Modules\Pricing\Infrastructure\Repositories\EloquentPricingChargeTypeRepository;
use Modules\Pricing\Infrastructure\Repositories\EloquentPricingQuoteRepository;
use Modules\Pricing\Infrastructure\Repositories\EloquentPricingVersionRepository;
use Modules\Pricing\Infrastructure\Repositories\EloquentPricingZoneRepository;
use Modules\Pricing\Infrastructure\Repositories\EloquentPricingZoneSetRepository;
use Modules\Pricing\Infrastructure\Repositories\EloquentTariffRepository;
use Modules\Pricing\Infrastructure\Workbooks\XlsxMatrixWorkbookStorage;
use Modules\ServiceCatalog\Application\Contracts\CommitmentZoneResolverInterface;

final class PricingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PricingVersionRepositoryInterface::class, EloquentPricingVersionRepository::class);
        $this->app->bind(PricingAuditRepositoryInterface::class, EloquentPricingAuditRepository::class);
        $this->app->bind(PricingChargeTypeRepositoryInterface::class, EloquentPricingChargeTypeRepository::class);
        $this->app->bind(PricingQuoteRepositoryInterface::class, EloquentPricingQuoteRepository::class);
        $this->app->bind(PricingZoneRepositoryInterface::class, EloquentPricingZoneRepository::class);
        $this->app->bind(TariffRepositoryInterface::class, EloquentTariffRepository::class);
        $this->app->bind(PricingZoneSetRepositoryInterface::class, EloquentPricingZoneSetRepository::class);
        $this->app->bind(PricingRuleCalculatorInterface::class, PricingRuleCalculator::class);
        $this->app->bind(PricingFactsInterface::class, PricingFacts::class);
        $this->app->bind(FreightMatrixRuleCompilerInterface::class, FreightMatrixRuleCompiler::class);
        $this->app->bind(PricingZoneResolverInterface::class, PricingZoneResolver::class);
        $this->app->bind(QuoteCalculatorInterface::class, QuoteCalculator::class);
        $this->app->bind(QuoteWriterInterface::class, QuoteWriter::class);
        $this->app->bind(PricingConfigurationWriterInterface::class, PricingConfigurationWriter::class);
        $this->app->bind(PricingReaderInterface::class, PricingReader::class);
        $this->app->bind(TariffMatrixCompilerInterface::class, TariffMatrixCompiler::class);
        $this->app->bind(MatrixWorkbookPreviewServiceInterface::class, MatrixWorkbookPreviewService::class);
        $this->app->bind(PricingDraftPreparationInterface::class, PricingDraftPreparation::class);
        $this->app->bind(PricingZoneGuardInterface::class, PricingZoneGuard::class);
        $this->app->bind(PricingVersionGuardInterface::class, PricingVersionGuard::class);
        $this->app->bind(PricingChangeRecorderInterface::class, PricingChangeRecorder::class);
        $this->app->bind(ConsignmentQuoteAcceptorInterface::class, ConsignmentQuoteAcceptor::class);
        $this->app->bind(ServiceTariffDependenciesInterface::class, ServiceTariffDependencies::class);
        $this->app->bind(PricingMatrixMatcherInterface::class, PricingMatrixMatcher::class);
        $this->app->bind(PricingAccessGuardInterface::class, PricingAccessGuard::class);
        $this->app->bind(PricingInputInterface::class, PricingInput::class);
        $this->app->bind(PricingZoneWriterInterface::class, PricingZoneWriter::class);
        $this->app->singleton(MatrixWorkbookStorageInterface::class, XlsxMatrixWorkbookStorage::class);
        $this->app->singleton(PricingSettingsInterface::class, LaravelPricingSettings::class);
        $this->app->singleton(ConsignmentQuoteAcceptanceInterface::class, ConsignmentQuoteAcceptor::class);
        $this->app->singleton(CommitmentZoneResolverInterface::class, PricingZoneResolver::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
        $this->app->register(RouteServiceProvider::class);
    }
}
