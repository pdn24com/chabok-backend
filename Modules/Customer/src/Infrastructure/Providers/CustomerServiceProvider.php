<?php

declare(strict_types=1);

namespace Modules\Customer\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\CrmTask\Application\Ports\CustomerDirectoryInterface;
use Modules\Customer\Application\Contracts\CustomerAccessGuardInterface;
use Modules\Customer\Application\Contracts\CustomerAddressValidatorInterface;
use Modules\Customer\Application\Contracts\CustomerContactPointValidatorInterface;
use Modules\Customer\Application\Contracts\CustomerDraftValidatorInterface;
use Modules\Customer\Application\Contracts\CustomerHistoryReaderInterface;
use Modules\Customer\Application\Contracts\CustomerIndustryValidatorInterface;
use Modules\Customer\Application\Contracts\CustomerOrgStructureValidatorInterface;
use Modules\Customer\Application\Contracts\CustomerPrimaryIndustryManagerInterface;
use Modules\Customer\Application\Contracts\CustomerRelationshipAssemblerInterface;
use Modules\Customer\Application\Contracts\CustomerRelationshipValidatorInterface;
use Modules\Customer\Application\Repositories\ContactPointRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerAddressRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerDepartmentRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerExtendedDetailRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerFinancialDetailRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerIndustryRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerPositionRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Customer\Application\Repositories\RelationshipRepositoryInterface;
use Modules\Customer\Application\Services\CustomerAccessGuard;
use Modules\Customer\Application\Services\CustomerHistoryReader;
use Modules\Customer\Application\Services\CustomerPrimaryIndustryManager;
use Modules\Customer\Application\Services\CustomerRelationshipAssembler;
use Modules\Customer\Application\Validators\CustomerAddressValidator;
use Modules\Customer\Application\Validators\CustomerContactPointValidator;
use Modules\Customer\Application\Validators\CustomerDraftValidator;
use Modules\Customer\Application\Validators\CustomerIndustryValidator;
use Modules\Customer\Application\Validators\CustomerOrgStructureValidator;
use Modules\Customer\Application\Validators\CustomerRelationshipValidator;
use Modules\Customer\Infrastructure\Adapters\TaskCustomerDirectory;
use Modules\Customer\Infrastructure\Repositories\EloquentContactPointRepository;
use Modules\Customer\Infrastructure\Repositories\EloquentCustomerAddressRepository;
use Modules\Customer\Infrastructure\Repositories\EloquentCustomerDepartmentRepository;
use Modules\Customer\Infrastructure\Repositories\EloquentCustomerExtendedDetailRepository;
use Modules\Customer\Infrastructure\Repositories\EloquentCustomerFinancialDetailRepository;
use Modules\Customer\Infrastructure\Repositories\EloquentCustomerIndustryRepository;
use Modules\Customer\Infrastructure\Repositories\EloquentCustomerPositionRepository;
use Modules\Customer\Infrastructure\Repositories\EloquentCustomerRepository;
use Modules\Customer\Infrastructure\Repositories\EloquentRelationshipRepository;

final class CustomerServiceProvider extends ServiceProvider
{
    /** One repository per table, so a use case injects only the tables it actually touches. */
    private const REPOSITORIES = [
        CustomerRepositoryInterface::class => EloquentCustomerRepository::class,
        CustomerAddressRepositoryInterface::class => EloquentCustomerAddressRepository::class,
        ContactPointRepositoryInterface::class => EloquentContactPointRepository::class,
        CustomerIndustryRepositoryInterface::class => EloquentCustomerIndustryRepository::class,
        CustomerDepartmentRepositoryInterface::class => EloquentCustomerDepartmentRepository::class,
        CustomerPositionRepositoryInterface::class => EloquentCustomerPositionRepository::class,
        CustomerExtendedDetailRepositoryInterface::class => EloquentCustomerExtendedDetailRepository::class,
        CustomerFinancialDetailRepositoryInterface::class => EloquentCustomerFinancialDetailRepository::class,
        RelationshipRepositoryInterface::class => EloquentRelationshipRepository::class,
    ];

    public function register(): void
    {
        foreach (self::REPOSITORIES as $contract => $implementation) {
            $this->app->bind($contract, $implementation);
        }
        $this->app->bind(CustomerDirectoryInterface::class, TaskCustomerDirectory::class);
        $this->app->bind(CustomerAccessGuardInterface::class, CustomerAccessGuard::class);
        $this->app->bind(CustomerPrimaryIndustryManagerInterface::class, CustomerPrimaryIndustryManager::class);
        $this->app->bind(CustomerAddressValidatorInterface::class, CustomerAddressValidator::class);
        $this->app->bind(CustomerDraftValidatorInterface::class, CustomerDraftValidator::class);
        $this->app->bind(CustomerOrgStructureValidatorInterface::class, CustomerOrgStructureValidator::class);
        $this->app->bind(CustomerHistoryReaderInterface::class, CustomerHistoryReader::class);
        $this->app->bind(CustomerContactPointValidatorInterface::class, CustomerContactPointValidator::class);
        $this->app->bind(CustomerIndustryValidatorInterface::class, CustomerIndustryValidator::class);
        $this->app->bind(CustomerRelationshipValidatorInterface::class, CustomerRelationshipValidator::class);
        $this->app->bind(CustomerRelationshipAssemblerInterface::class, CustomerRelationshipAssembler::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
        $this->app->register(RouteServiceProvider::class);
    }
}
