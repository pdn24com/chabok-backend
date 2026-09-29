<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\ServiceCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogChangeRecorderInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogCodeInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogInputInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogReaderInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogRecordChangeRecorderInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogRecordReaderInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogResolverInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogResourceDefinitionInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogSelectionInspectorInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogSelectionReaderInterface;
use Modules\ServiceCatalog\Application\Contracts\CatalogVersionGuardInterface;
use Modules\ServiceCatalog\Application\Contracts\CommitmentClockInterface;
use Modules\ServiceCatalog\Application\Contracts\CommitmentSettingsInterface;
use Modules\ServiceCatalog\Application\Contracts\CurrentCatalogInterface;
use Modules\ServiceCatalog\Application\Contracts\FrozenCommitmentCompletionInterface;
use Modules\ServiceCatalog\Application\Contracts\FrozenCommitmentResolverInterface;
use Modules\ServiceCatalog\Application\Contracts\OfferingChildrenWriterInterface;
use Modules\ServiceCatalog\Application\Contracts\OfferingCommitmentResolverInterface;
use Modules\ServiceCatalog\Application\Contracts\OfferingContextInterface;
use Modules\ServiceCatalog\Application\Contracts\OfferingDependenciesInterface;
use Modules\ServiceCatalog\Application\Contracts\OfferingEligibilityInterface;
use Modules\ServiceCatalog\Application\Contracts\OfferingLegacyCommitmentInterface;
use Modules\ServiceCatalog\Application\Contracts\OfferingOptionsInterface;
use Modules\ServiceCatalog\Application\Contracts\OfferingReferenceGuardInterface;
use Modules\ServiceCatalog\Application\Contracts\QuoteCatalogGuardInterface;
use Modules\ServiceCatalog\Application\Contracts\ScheduleChangeRecorderInterface;
use Modules\ServiceCatalog\Application\Contracts\ScheduleChildrenWriterInterface;
use Modules\ServiceCatalog\Application\Contracts\ScheduleInputInterface;
use Modules\ServiceCatalog\Application\Contracts\ScheduleInputValidatorInterface;
use Modules\ServiceCatalog\Application\Contracts\SchedulePolicyInterface;
use Modules\ServiceCatalog\Application\Contracts\SchedulePolicyResolverInterface;
use Modules\ServiceCatalog\Application\Contracts\ScheduleReaderInterface;
use Modules\ServiceCatalog\Application\Contracts\ScheduleWindowsInterface;
use Modules\ServiceCatalog\Application\Contracts\ServiceEligibilityResolverInterface;
use Modules\ServiceCatalog\Application\Repositories\CatalogAuditRepositoryInterface;
use Modules\ServiceCatalog\Application\Repositories\CatalogRepositoryInterface;
use Modules\ServiceCatalog\Application\Repositories\CommitmentScheduleRepositoryInterface;
use Modules\ServiceCatalog\Application\Repositories\OfferingChildrenRepositoryInterface;
use Modules\ServiceCatalog\Application\Repositories\ScheduleChildrenRepositoryInterface;
use Modules\ServiceCatalog\Application\Services\CatalogAccessGuard;
use Modules\ServiceCatalog\Application\Services\CatalogChangeRecorder;
use Modules\ServiceCatalog\Application\Services\CatalogCode;
use Modules\ServiceCatalog\Application\Services\CatalogInput;
use Modules\ServiceCatalog\Application\Services\CatalogReader;
use Modules\ServiceCatalog\Application\Services\CatalogRecordChangeRecorder;
use Modules\ServiceCatalog\Application\Services\CatalogRecordReader;
use Modules\ServiceCatalog\Application\Services\CatalogResourceDefinition;
use Modules\ServiceCatalog\Application\Services\CatalogSelectionReader;
use Modules\ServiceCatalog\Application\Services\CatalogVersionGuard;
use Modules\ServiceCatalog\Application\Services\CommitmentClock;
use Modules\ServiceCatalog\Application\Services\CurrentCatalog;
use Modules\ServiceCatalog\Application\Services\FrozenCommitmentCompletion;
use Modules\ServiceCatalog\Application\Services\OfferingChildrenWriter;
use Modules\ServiceCatalog\Application\Services\OfferingCommitmentResolver;
use Modules\ServiceCatalog\Application\Services\OfferingContext;
use Modules\ServiceCatalog\Application\Services\OfferingDependencies;
use Modules\ServiceCatalog\Application\Services\OfferingEligibility;
use Modules\ServiceCatalog\Application\Services\OfferingLegacyCommitment;
use Modules\ServiceCatalog\Application\Services\OfferingOptions;
use Modules\ServiceCatalog\Application\Services\OfferingReferenceGuard;
use Modules\ServiceCatalog\Application\Services\ScheduleChangeRecorder;
use Modules\ServiceCatalog\Application\Services\ScheduleChildrenWriter;
use Modules\ServiceCatalog\Application\Services\ScheduleInput;
use Modules\ServiceCatalog\Application\Services\SchedulePolicy;
use Modules\ServiceCatalog\Application\Services\SchedulePolicyResolver;
use Modules\ServiceCatalog\Application\Services\ScheduleReader;
use Modules\ServiceCatalog\Application\Services\ScheduleWindows;
use Modules\ServiceCatalog\Infrastructure\Adapters\LaravelCommitmentSettings;
use Modules\ServiceCatalog\Infrastructure\Adapters\LaravelScheduleInputValidator;
use Modules\ServiceCatalog\Infrastructure\Adapters\ServiceEligibilityAdapter;
use Modules\ServiceCatalog\Infrastructure\Repositories\EloquentCatalogAuditRepository;
use Modules\ServiceCatalog\Infrastructure\Repositories\EloquentCatalogRepository;
use Modules\ServiceCatalog\Infrastructure\Repositories\EloquentCommitmentScheduleRepository;
use Modules\ServiceCatalog\Infrastructure\Repositories\EloquentOfferingChildrenRepository;
use Modules\ServiceCatalog\Infrastructure\Repositories\EloquentScheduleChildrenRepository;

final class ServiceCatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ScheduleChildrenRepositoryInterface::class, EloquentScheduleChildrenRepository::class);
        $this->app->bind(OfferingChildrenRepositoryInterface::class, EloquentOfferingChildrenRepository::class);
        $this->app->bind(CatalogAuditRepositoryInterface::class, EloquentCatalogAuditRepository::class);
        $this->app->bind(CommitmentScheduleRepositoryInterface::class, EloquentCommitmentScheduleRepository::class);
        $this->app->bind(CatalogRepositoryInterface::class, EloquentCatalogRepository::class);
        $this->app->bind(OfferingCommitmentResolverInterface::class, OfferingCommitmentResolver::class);
        $this->app->bind(CommitmentClockInterface::class, CommitmentClock::class);
        $this->app->bind(OfferingEligibilityInterface::class, OfferingEligibility::class);
        $this->app->bind(OfferingLegacyCommitmentInterface::class, OfferingLegacyCommitment::class);
        $this->app->bind(CatalogReaderInterface::class, CatalogReader::class);
        $this->app->bind(SchedulePolicyResolverInterface::class, SchedulePolicyResolver::class);
        $this->app->bind(OfferingContextInterface::class, OfferingContext::class);
        $this->app->bind(ScheduleChangeRecorderInterface::class, ScheduleChangeRecorder::class);
        $this->app->bind(ScheduleReaderInterface::class, ScheduleReader::class);
        $this->app->bind(CatalogRecordChangeRecorderInterface::class, CatalogRecordChangeRecorder::class);
        $this->app->bind(OfferingDependenciesInterface::class, OfferingDependencies::class);
        $this->app->bind(CatalogChangeRecorderInterface::class, CatalogChangeRecorder::class);
        $this->app->bind(CatalogRecordReaderInterface::class, CatalogRecordReader::class);
        $this->app->bind(CatalogSelectionReaderInterface::class, CatalogSelectionReader::class);
        $this->app->bind(CatalogResourceDefinitionInterface::class, CatalogResourceDefinition::class);
        $this->app->bind(ScheduleWindowsInterface::class, ScheduleWindows::class);
        $this->app->bind(ScheduleInputInterface::class, ScheduleInput::class);
        $this->app->bind(OfferingReferenceGuardInterface::class, OfferingReferenceGuard::class);
        $this->app->bind(CatalogVersionGuardInterface::class, CatalogVersionGuard::class);
        $this->app->bind(CatalogInputInterface::class, CatalogInput::class);
        $this->app->bind(OfferingChildrenWriterInterface::class, OfferingChildrenWriter::class);
        $this->app->bind(ScheduleChildrenWriterInterface::class, ScheduleChildrenWriter::class);
        $this->app->bind(CatalogCodeInterface::class, CatalogCode::class);
        $this->app->bind(SchedulePolicyInterface::class, SchedulePolicy::class);
        $this->app->bind(FrozenCommitmentCompletionInterface::class, FrozenCommitmentCompletion::class);
        $this->app->bind(CurrentCatalogInterface::class, CurrentCatalog::class);
        $this->app->bind(CatalogAccessGuardInterface::class, CatalogAccessGuard::class);
        $this->app->bind(OfferingOptionsInterface::class, OfferingOptions::class);
        $this->app->singleton(CatalogSelectionInspectorInterface::class, CatalogSelectionReader::class);
        $this->app->singleton(CatalogResolverInterface::class, CurrentCatalog::class);
        $this->app->singleton(ScheduleInputValidatorInterface::class, LaravelScheduleInputValidator::class);
        $this->app->singleton(CommitmentSettingsInterface::class, LaravelCommitmentSettings::class);
        $this->app->singleton(QuoteCatalogGuardInterface::class, CurrentCatalog::class);
        $this->app->singleton(FrozenCommitmentResolverInterface::class, FrozenCommitmentCompletion::class);
        $this->app->singleton(ServiceEligibilityResolverInterface::class, ServiceEligibilityAdapter::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
        $this->app->register(RouteServiceProvider::class);
    }
}
