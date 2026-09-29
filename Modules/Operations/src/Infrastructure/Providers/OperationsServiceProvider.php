<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Operations\Application\Contracts\CoverageChangeRecorderInterface;
use Modules\Operations\Application\Contracts\CoveragePolicyReaderInterface;
use Modules\Operations\Application\Contracts\CoverageRuleGuardInterface;
use Modules\Operations\Application\Contracts\CoverageRuleWriterInterface;
use Modules\Operations\Application\Contracts\CoverageVersionGuardInterface;
use Modules\Operations\Application\Contracts\DeliveryAccessGuardInterface;
use Modules\Operations\Application\Contracts\DeliveryAssignmentGuardInterface;
use Modules\Operations\Application\Contracts\DeliveryNodeCapabilitiesInterface;
use Modules\Operations\Application\Contracts\DeliveryTaskReaderInterface;
use Modules\Operations\Application\Contracts\DeliveryTaskRecorderInterface;
use Modules\Operations\Application\Contracts\DestinationResolutionInputInterface;
use Modules\Operations\Application\Contracts\DriverCapabilityWriterInterface;
use Modules\Operations\Application\Contracts\FleetAccessGuardInterface;
use Modules\Operations\Application\Contracts\FleetChangeRecorderInterface;
use Modules\Operations\Application\Contracts\LastMileResolverInterface;
use Modules\Operations\Application\Contracts\ManifestDirectoryReaderInterface;
use Modules\Operations\Application\Contracts\ManifestExceptionAccessInterface;
use Modules\Operations\Application\Contracts\ManifestRouteAccessInterface;
use Modules\Operations\Application\Contracts\ManifestTaskAccessInterface;
use Modules\Operations\Application\Contracts\ManifestTaskBatchWriterInterface;
use Modules\Operations\Application\Contracts\MovementAccessGuardInterface;
use Modules\Operations\Application\Contracts\MovementRecorderInterface;
use Modules\Operations\Application\Contracts\NetworkAccessGuardInterface;
use Modules\Operations\Application\Contracts\OperationalDirectoryAccessInterface;
use Modules\Operations\Application\Contracts\ParcelLifecycleServiceInterface;
use Modules\Operations\Application\Contracts\PickupAccessGuardInterface;
use Modules\Operations\Application\Contracts\PickupTaskGuardInterface;
use Modules\Operations\Application\Contracts\PickupTaskReaderInterface;
use Modules\Operations\Application\Contracts\PickupTaskRecorderInterface;
use Modules\Operations\Application\Contracts\RouteChangeRecorderInterface;
use Modules\Operations\Application\Contracts\RouteDefinitionReaderInterface;
use Modules\Operations\Application\Contracts\RouteLegWriterInterface;
use Modules\Operations\Application\Contracts\RoutePlanGuardInterface;
use Modules\Operations\Application\Contracts\RoutePlanReaderInterface;
use Modules\Operations\Application\Contracts\RouteVersionGuardInterface;
use Modules\Operations\Application\Repositories\CoverageRepositoryInterface;
use Modules\Operations\Application\Repositories\DeliveryTaskRepositoryInterface;
use Modules\Operations\Application\Repositories\DriverRepositoryInterface;
use Modules\Operations\Application\Repositories\OperationalExceptionRepositoryInterface;
use Modules\Operations\Application\Repositories\PickupTaskRepositoryInterface;
use Modules\Operations\Application\Repositories\RouteDefinitionRepositoryInterface;
use Modules\Operations\Application\Repositories\RoutePlanRepositoryInterface;
use Modules\Operations\Application\Repositories\VehicleRepositoryInterface;
use Modules\Operations\Application\Services\CoverageChangeRecorder;
use Modules\Operations\Application\Services\CoveragePolicyReader;
use Modules\Operations\Application\Services\CoverageRuleGuard;
use Modules\Operations\Application\Services\CoverageRuleWriter;
use Modules\Operations\Application\Services\CoverageVersionGuard;
use Modules\Operations\Application\Services\DeliveryAccessGuard;
use Modules\Operations\Application\Services\DeliveryAssignmentGuard;
use Modules\Operations\Application\Services\DeliveryNodeCapabilities;
use Modules\Operations\Application\Services\DeliveryTaskReader;
use Modules\Operations\Application\Services\DeliveryTaskRecorder;
use Modules\Operations\Application\Services\DestinationResolutionInput;
use Modules\Operations\Application\Services\DriverCapabilityWriter;
use Modules\Operations\Application\Services\FleetAccessGuard;
use Modules\Operations\Application\Services\FleetChangeRecorder;
use Modules\Operations\Application\Services\LastMileResolver;
use Modules\Operations\Application\Services\ManifestTaskBatchWriter;
use Modules\Operations\Application\Services\MovementAccessGuard;
use Modules\Operations\Application\Services\MovementRecorder;
use Modules\Operations\Application\Services\NetworkAccessGuard;
use Modules\Operations\Application\Services\OperationalDirectoryAccess;
use Modules\Operations\Application\Services\ParcelLifecycleService;
use Modules\Operations\Application\Services\PickupAccessGuard;
use Modules\Operations\Application\Services\PickupTaskGuard;
use Modules\Operations\Application\Services\PickupTaskReader;
use Modules\Operations\Application\Services\PickupTaskRecorder;
use Modules\Operations\Application\Services\RouteChangeRecorder;
use Modules\Operations\Application\Services\RouteDefinitionReader;
use Modules\Operations\Application\Services\RouteLegWriter;
use Modules\Operations\Application\Services\RoutePlanGuard;
use Modules\Operations\Application\Services\RoutePlanReader;
use Modules\Operations\Application\Services\RouteVersionGuard;
use Modules\Operations\Infrastructure\Repositories\EloquentCoverageRepository;
use Modules\Operations\Infrastructure\Repositories\EloquentDeliveryTaskRepository;
use Modules\Operations\Infrastructure\Repositories\EloquentDriverRepository;
use Modules\Operations\Infrastructure\Repositories\EloquentManifestDirectoryReader;
use Modules\Operations\Infrastructure\Repositories\EloquentManifestExceptionAccess;
use Modules\Operations\Infrastructure\Repositories\EloquentManifestRouteAccess;
use Modules\Operations\Infrastructure\Repositories\EloquentManifestTaskAccess;
use Modules\Operations\Infrastructure\Repositories\EloquentOperationalExceptionRepository;
use Modules\Operations\Infrastructure\Repositories\EloquentPickupTaskRepository;
use Modules\Operations\Infrastructure\Repositories\EloquentRouteDefinitionRepository;
use Modules\Operations\Infrastructure\Repositories\EloquentRoutePlanRepository;
use Modules\Operations\Infrastructure\Repositories\EloquentVehicleRepository;

final class OperationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(OperationalExceptionRepositoryInterface::class, EloquentOperationalExceptionRepository::class);
        $this->app->bind(RoutePlanRepositoryInterface::class, EloquentRoutePlanRepository::class);
        $this->app->bind(RouteDefinitionRepositoryInterface::class, EloquentRouteDefinitionRepository::class);
        $this->app->bind(CoverageRepositoryInterface::class, EloquentCoverageRepository::class);
        $this->app->bind(DeliveryTaskRepositoryInterface::class, EloquentDeliveryTaskRepository::class);
        $this->app->bind(PickupTaskRepositoryInterface::class, EloquentPickupTaskRepository::class);
        $this->app->bind(VehicleRepositoryInterface::class, EloquentVehicleRepository::class);
        $this->app->bind(DriverRepositoryInterface::class, EloquentDriverRepository::class);
        $this->app->bind(ManifestTaskBatchWriterInterface::class, ManifestTaskBatchWriter::class);
        $this->app->bind(RouteChangeRecorderInterface::class, RouteChangeRecorder::class);
        $this->app->bind(MovementAccessGuardInterface::class, MovementAccessGuard::class);
        $this->app->bind(FleetAccessGuardInterface::class, FleetAccessGuard::class);
        $this->app->bind(DeliveryNodeCapabilitiesInterface::class, DeliveryNodeCapabilities::class);
        $this->app->bind(RouteVersionGuardInterface::class, RouteVersionGuard::class);
        $this->app->bind(LastMileResolverInterface::class, LastMileResolver::class);
        $this->app->bind(PickupTaskReaderInterface::class, PickupTaskReader::class);
        $this->app->bind(OperationalDirectoryAccessInterface::class, OperationalDirectoryAccess::class);
        $this->app->bind(PickupAccessGuardInterface::class, PickupAccessGuard::class);
        $this->app->bind(CoverageRuleGuardInterface::class, CoverageRuleGuard::class);
        $this->app->bind(CoverageRuleWriterInterface::class, CoverageRuleWriter::class);
        $this->app->bind(MovementRecorderInterface::class, MovementRecorder::class);
        $this->app->bind(CoverageChangeRecorderInterface::class, CoverageChangeRecorder::class);
        $this->app->bind(RouteDefinitionReaderInterface::class, RouteDefinitionReader::class);
        $this->app->bind(DeliveryTaskRecorderInterface::class, DeliveryTaskRecorder::class);
        $this->app->bind(CoveragePolicyReaderInterface::class, CoveragePolicyReader::class);
        $this->app->bind(NetworkAccessGuardInterface::class, NetworkAccessGuard::class);
        $this->app->bind(DeliveryTaskReaderInterface::class, DeliveryTaskReader::class);
        $this->app->bind(RoutePlanGuardInterface::class, RoutePlanGuard::class);
        $this->app->bind(PickupTaskGuardInterface::class, PickupTaskGuard::class);
        $this->app->bind(PickupTaskRecorderInterface::class, PickupTaskRecorder::class);
        $this->app->bind(DriverCapabilityWriterInterface::class, DriverCapabilityWriter::class);
        $this->app->bind(DestinationResolutionInputInterface::class, DestinationResolutionInput::class);
        $this->app->bind(FleetChangeRecorderInterface::class, FleetChangeRecorder::class);
        $this->app->bind(CoverageVersionGuardInterface::class, CoverageVersionGuard::class);
        $this->app->bind(RoutePlanReaderInterface::class, RoutePlanReader::class);
        $this->app->bind(RouteLegWriterInterface::class, RouteLegWriter::class);
        $this->app->bind(DeliveryAssignmentGuardInterface::class, DeliveryAssignmentGuard::class);
        $this->app->bind(ParcelLifecycleServiceInterface::class, ParcelLifecycleService::class);
        $this->app->bind(DeliveryAccessGuardInterface::class, DeliveryAccessGuard::class);
        $this->app->singleton(ManifestExceptionAccessInterface::class, EloquentManifestExceptionAccess::class);
        $this->app->singleton(ManifestDirectoryReaderInterface::class, EloquentManifestDirectoryReader::class);
        $this->app->singleton(ManifestRouteAccessInterface::class, EloquentManifestRouteAccess::class);
        $this->app->singleton(ManifestTaskAccessInterface::class, EloquentManifestTaskAccess::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
        $this->app->register(RouteServiceProvider::class);
    }
}
