<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\CrmFinance\Application\Contracts\FinanceAccessGuardInterface;
use Modules\CrmFinance\Application\Repositories\BankAccountRepositoryInterface;
use Modules\CrmFinance\Application\Repositories\ExternalInvoiceRepositoryInterface;
use Modules\CrmFinance\Application\Repositories\FinancialAllocationRepositoryInterface;
use Modules\CrmFinance\Application\Repositories\FinancialEntryRepositoryInterface;
use Modules\CrmFinance\Application\Services\FinanceAccessGuard;
use Modules\CrmFinance\Infrastructure\Adapters\CustomerFinanceHistory;
use Modules\CrmFinance\Infrastructure\Repositories\EloquentBankAccountRepository;
use Modules\CrmFinance\Infrastructure\Repositories\EloquentExternalInvoiceRepository;
use Modules\CrmFinance\Infrastructure\Repositories\EloquentFinancialAllocationRepository;
use Modules\CrmFinance\Infrastructure\Repositories\EloquentFinancialEntryRepository;
use Modules\Customer\Application\Ports\CustomerFinanceHistoryInterface;

final class CrmFinanceServiceProvider extends ServiceProvider
{
    /** One repository per table, so a use case injects only the tables it actually touches. */
    private const REPOSITORIES = [
        BankAccountRepositoryInterface::class => EloquentBankAccountRepository::class,
        ExternalInvoiceRepositoryInterface::class => EloquentExternalInvoiceRepository::class,
        FinancialEntryRepositoryInterface::class => EloquentFinancialEntryRepository::class,
        FinancialAllocationRepositoryInterface::class => EloquentFinancialAllocationRepository::class,
    ];

    public function register(): void
    {
        foreach (self::REPOSITORIES as $contract => $implementation) {
            $this->app->bind($contract, $implementation);
        }
        $this->app->bind(FinanceAccessGuardInterface::class, FinanceAccessGuard::class);
        $this->app->bind(CustomerFinanceHistoryInterface::class, CustomerFinanceHistory::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__, 3).'/database/migrations');
        $this->app->register(RouteServiceProvider::class);
    }
}
