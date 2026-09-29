<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\CrmFinance\Presentation\Http\Controllers\CustomerBankAccountController;
use Modules\CrmFinance\Presentation\Http\Controllers\CustomerExternalInvoiceController;
use Modules\CrmFinance\Presentation\Http\Controllers\CustomerFinancialEntryController;

// The financial tab of the customer file. Everything here sits behind the crm.finance permissions, not
// the customer ones: seeing a customer record is not by itself seeing its money.
Route::prefix('api/v1/crm/customers/{customerId}')
    ->middleware(['api', 'access.auth', 'password.changed'])
    ->whereNumber('customerId')
    ->group(function (): void {
        Route::get('/bank-accounts', [CustomerBankAccountController::class, 'index'])->name('crm.customers.bank-accounts.index');
        Route::post('/bank-accounts', [CustomerBankAccountController::class, 'store'])->name('crm.customers.bank-accounts.store');
        Route::get('/external-invoices', [CustomerExternalInvoiceController::class, 'index'])->name('crm.customers.external-invoices.index');
        Route::post('/external-invoices', [CustomerExternalInvoiceController::class, 'store'])->name('crm.customers.external-invoices.store');
        Route::get('/financial-entries', [CustomerFinancialEntryController::class, 'index'])->name('crm.customers.financial-entries.index');
        Route::post('/financial-entries', [CustomerFinancialEntryController::class, 'store'])->name('crm.customers.financial-entries.store');
    });

// An allocation hangs off the receipt rather than the customer, because the receipt is what it consumes.
Route::prefix('api/v1/crm/financial-entries/{entryId}')
    ->middleware(['api', 'access.auth', 'password.changed'])
    ->whereNumber('entryId')
    ->group(function (): void {
        Route::post('/allocations', [CustomerFinancialEntryController::class, 'allocate'])->name('crm.financial-entries.allocations.store');
    });
