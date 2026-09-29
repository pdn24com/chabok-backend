<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\CrmSales\Presentation\Http\Controllers\ContractController;
use Modules\CrmSales\Presentation\Http\Controllers\SalesDocumentController;
use Modules\CrmSales\Presentation\Http\Controllers\SalesDocumentVersionController;

// Sales documents and their revisions. Everything here sits behind the crm.sales_document permissions.
Route::prefix('api/v1/crm')->middleware(['api', 'access.auth', 'password.changed'])->group(function (): void {
    Route::get('/sales-documents', [SalesDocumentController::class, 'index'])->name('crm.sales-documents.index');
    Route::post('/sales-documents', [SalesDocumentController::class, 'store'])->name('crm.sales-documents.store');
});

Route::prefix('api/v1/crm/sales-documents/{documentId}')
    ->middleware(['api', 'access.auth', 'password.changed'])
    ->whereNumber('documentId')
    ->group(function (): void {
        Route::get('/', [SalesDocumentController::class, 'show'])->name('crm.sales-documents.show');
        // Correcting an issued document is a new revision, so it is posted rather than patched onto it.
        Route::post('/versions', [SalesDocumentController::class, 'storeVersion'])->name('crm.sales-documents.versions.store');
    });

Route::prefix('api/v1/crm/sales-document-versions/{versionId}')
    ->middleware(['api', 'access.auth', 'password.changed'])
    ->whereNumber('versionId')
    ->group(function (): void {
        Route::post('/issue', [SalesDocumentVersionController::class, 'issue'])->name('crm.sales-document-versions.issue');
        Route::post('/accept', [SalesDocumentVersionController::class, 'accept'])->name('crm.sales-document-versions.accept');
        Route::post('/cancel', [SalesDocumentVersionController::class, 'cancel'])->name('crm.sales-document-versions.cancel');
    });

// The contract file of a customer. Behind the crm.contract permissions, apart from the sales documents.
Route::prefix('api/v1/crm/customers/{customerId}/contracts')
    ->middleware(['api', 'access.auth', 'password.changed'])
    ->whereNumber('customerId')
    ->group(function (): void {
        Route::get('/', [ContractController::class, 'index'])->name('crm.customers.contracts.index');
        Route::post('/', [ContractController::class, 'store'])->name('crm.customers.contracts.store');
    });
