<?php

declare(strict_types=1);

namespace Modules\Geography\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Foundation\Application\Contracts\CanonicalGeographyResolverInterface;
use Modules\Geography\Application\Contracts\GeographyResolverInterface;
use Modules\Geography\Application\Contracts\PolygonGeometryInterface;
use Modules\Geography\Application\Contracts\SpatialTopologyInterface;
use Modules\Geography\Application\Repositories\CityRepositoryInterface;
use Modules\Geography\Application\Repositories\CountryRepositoryInterface;
use Modules\Geography\Application\Repositories\ProvinceRepositoryInterface;
use Modules\Geography\Application\Services\GeographyResolver;
use Modules\Geography\Application\Services\PolygonGeometry;
use Modules\Geography\Infrastructure\Adapters\MySqlSpatialTopology;
use Modules\Geography\Infrastructure\Repositories\EloquentCityRepository;
use Modules\Geography\Infrastructure\Repositories\EloquentCountryRepository;
use Modules\Geography\Infrastructure\Repositories\EloquentProvinceRepository;

final class GeographyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CityRepositoryInterface::class, EloquentCityRepository::class);
        $this->app->bind(CountryRepositoryInterface::class, EloquentCountryRepository::class);
        $this->app->bind(ProvinceRepositoryInterface::class, EloquentProvinceRepository::class);
        $this->app->singleton(SpatialTopologyInterface::class, MySqlSpatialTopology::class);
        $this->app->singleton(CanonicalGeographyResolverInterface::class, GeographyResolver::class);
        $this->app->bind(GeographyResolverInterface::class, GeographyResolver::class);
        $this->app->bind(PolygonGeometryInterface::class, PolygonGeometry::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
        $this->app->register(RouteServiceProvider::class);
    }
}
