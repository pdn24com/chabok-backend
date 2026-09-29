<?php

declare(strict_types=1);

namespace Modules\Manifest\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Manifest\Application\Contracts\ManifestAccessGuardInterface;
use Modules\Manifest\Application\Contracts\ManifestContextAccessInterface;
use Modules\Manifest\Application\Contracts\ManifestContextNormalizerInterface;
use Modules\Manifest\Application\Contracts\ManifestContextOptionsInterface;
use Modules\Manifest\Application\Contracts\ManifestContextProjectionInterface;
use Modules\Manifest\Application\Contracts\ManifestContextReferencesInterface;
use Modules\Manifest\Application\Contracts\ManifestEligibilityEvaluatorInterface;
use Modules\Manifest\Application\Contracts\ManifestExceptionWriterInterface;
use Modules\Manifest\Application\Contracts\ManifestNumberAllocatorInterface;
use Modules\Manifest\Application\Contracts\ManifestParcelWriterInterface;
use Modules\Manifest\Application\Contracts\ManifestReaderInterface;
use Modules\Manifest\Application\Contracts\ManifestRoutePlannerInterface;
use Modules\Manifest\Application\Contracts\ManifestRowProcessorInterface;
use Modules\Manifest\Application\Contracts\ManifestTargetApplierInterface;
use Modules\Manifest\Application\Contracts\ManifestTransitionRecorderInterface;
use Modules\Manifest\Application\Contracts\ManifestWorkflowGuardInterface;
use Modules\Manifest\Application\Repositories\ManifestCandidateRepositoryInterface;
use Modules\Manifest\Application\Repositories\ManifestEvidenceRepositoryInterface;
use Modules\Manifest\Application\Repositories\ManifestNumberRepositoryInterface;
use Modules\Manifest\Application\Repositories\ManifestParcelRepositoryInterface;
use Modules\Manifest\Application\Repositories\ManifestRepositoryInterface;
use Modules\Manifest\Application\Services\ManifestAccessGuard;
use Modules\Manifest\Application\Services\ManifestContextAccess;
use Modules\Manifest\Application\Services\ManifestContextNormalizer;
use Modules\Manifest\Application\Services\ManifestContextOptions;
use Modules\Manifest\Application\Services\ManifestContextProjection;
use Modules\Manifest\Application\Services\ManifestContextReferences;
use Modules\Manifest\Application\Services\ManifestEligibilityEvaluator;
use Modules\Manifest\Application\Services\ManifestExceptionWriter;
use Modules\Manifest\Application\Services\ManifestNumberAllocator;
use Modules\Manifest\Application\Services\ManifestParcelWriter;
use Modules\Manifest\Application\Services\ManifestReader;
use Modules\Manifest\Application\Services\ManifestRoutePlanner;
use Modules\Manifest\Application\Services\ManifestRowProcessor;
use Modules\Manifest\Application\Services\ManifestTargetApplier;
use Modules\Manifest\Application\Services\ManifestTransitionRecorder;
use Modules\Manifest\Application\Services\ManifestWorkflowGuard;
use Modules\Manifest\Infrastructure\Repositories\EloquentManifestCandidateRepository;
use Modules\Manifest\Infrastructure\Repositories\EloquentManifestEvidenceRepository;
use Modules\Manifest\Infrastructure\Repositories\EloquentManifestNumberRepository;
use Modules\Manifest\Infrastructure\Repositories\EloquentManifestParcelRepository;
use Modules\Manifest\Infrastructure\Repositories\EloquentManifestRepository;

final class ManifestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ManifestEvidenceRepositoryInterface::class, EloquentManifestEvidenceRepository::class);
        $this->app->bind(ManifestNumberRepositoryInterface::class, EloquentManifestNumberRepository::class);
        $this->app->bind(ManifestCandidateRepositoryInterface::class, EloquentManifestCandidateRepository::class);
        $this->app->bind(ManifestParcelRepositoryInterface::class, EloquentManifestParcelRepository::class);
        $this->app->bind(ManifestRepositoryInterface::class, EloquentManifestRepository::class);
        $this->app->bind(ManifestParcelWriterInterface::class, ManifestParcelWriter::class);
        $this->app->bind(ManifestRowProcessorInterface::class, ManifestRowProcessor::class);
        $this->app->bind(ManifestEligibilityEvaluatorInterface::class, ManifestEligibilityEvaluator::class);
        $this->app->bind(ManifestTargetApplierInterface::class, ManifestTargetApplier::class);
        $this->app->bind(ManifestWorkflowGuardInterface::class, ManifestWorkflowGuard::class);
        $this->app->bind(ManifestReaderInterface::class, ManifestReader::class);
        $this->app->bind(ManifestContextAccessInterface::class, ManifestContextAccess::class);
        $this->app->bind(ManifestRoutePlannerInterface::class, ManifestRoutePlanner::class);
        $this->app->bind(ManifestContextProjectionInterface::class, ManifestContextProjection::class);
        $this->app->bind(ManifestContextNormalizerInterface::class, ManifestContextNormalizer::class);
        $this->app->bind(ManifestExceptionWriterInterface::class, ManifestExceptionWriter::class);
        $this->app->bind(ManifestTransitionRecorderInterface::class, ManifestTransitionRecorder::class);
        $this->app->bind(ManifestAccessGuardInterface::class, ManifestAccessGuard::class);
        $this->app->bind(ManifestContextReferencesInterface::class, ManifestContextReferences::class);
        $this->app->bind(ManifestContextOptionsInterface::class, ManifestContextOptions::class);
        $this->app->bind(ManifestNumberAllocatorInterface::class, ManifestNumberAllocator::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
        $this->app->register(RouteServiceProvider::class);
    }
}
