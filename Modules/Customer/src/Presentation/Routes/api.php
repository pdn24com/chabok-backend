<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Customer\Presentation\Http\Controllers\CustomerAddressController;
use Modules\Customer\Presentation\Http\Controllers\CustomerContactPointController;
use Modules\Customer\Presentation\Http\Controllers\CustomerController;
use Modules\Customer\Presentation\Http\Controllers\CustomerDuplicateController;
use Modules\Customer\Presentation\Http\Controllers\CustomerHistoryController;
use Modules\Customer\Presentation\Http\Controllers\CustomerIndustryController;
use Modules\Customer\Presentation\Http\Controllers\CustomerLeadController;
use Modules\Customer\Presentation\Http\Controllers\CustomerOrgStructureController;
use Modules\Customer\Presentation\Http\Controllers\CustomerRelationshipController;

Route::prefix('api/v1/customers')->middleware(['api', 'access.auth', 'password.changed'])->group(function (): void {
    Route::get('/', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('/', [CustomerController::class, 'store'])->middleware('idempotent:customer.create')->name('customers.store');
});

Route::prefix('api/v1/crm/customers')->middleware(['api', 'access.auth', 'password.changed'])->group(function (): void {
    Route::get('/{customerId}/detail', [CustomerController::class, 'detail'])->whereNumber('customerId')->name('crm.customers.detail.show');
    Route::get('/{customerId}/profile', [CustomerController::class, 'profile'])->whereNumber('customerId')->name('crm.customers.profile.show');
    Route::patch('/{customerId}/profile', [CustomerController::class, 'updateProfile'])->whereNumber('customerId')->name('crm.customers.profile.update');
    Route::get('/{customerId}/extended-details', [CustomerController::class, 'extendedDetails'])->whereNumber('customerId')->name('crm.customers.extended-details.show');
    Route::put('/{customerId}/extended-details', [CustomerController::class, 'saveExtendedDetails'])->whereNumber('customerId')->name('crm.customers.extended-details.update');
    // The financial summary is read and written under its own permission, not the customer one.
    Route::get('/{customerId}/financial-details', [CustomerController::class, 'financialDetails'])->whereNumber('customerId')->name('crm.customers.financial-details.show');
    Route::put('/{customerId}/financial-details', [CustomerController::class, 'saveFinancialDetails'])->whereNumber('customerId')->name('crm.customers.financial-details.update');
});

Route::prefix('api/v1/crm/customers/{customerId}/addresses')
    ->middleware(['api', 'access.auth', 'password.changed'])
    ->whereNumber(['customerId', 'addressId'])
    ->group(function (): void {
        Route::get('/', [CustomerAddressController::class, 'index'])->name('crm.customers.addresses.index');
        Route::post('/', [CustomerAddressController::class, 'store'])->name('crm.customers.addresses.store');
        Route::get('/{addressId}', [CustomerAddressController::class, 'show'])->name('crm.customers.addresses.show');
        Route::patch('/{addressId}', [CustomerAddressController::class, 'update'])->name('crm.customers.addresses.update');
    });

// Tab 3 of the customer file: the department chart of a company and the posts inside it.
Route::prefix('api/v1/crm/customers/{customerId}')
    ->middleware(['api', 'access.auth', 'password.changed'])
    ->whereNumber(['customerId', 'departmentId', 'positionId'])
    ->group(function (): void {
        Route::get('/org-structure', [CustomerOrgStructureController::class, 'show'])->name('crm.customers.org-structure.show');
        Route::post('/departments', [CustomerOrgStructureController::class, 'storeDepartment'])->name('crm.customers.departments.store');
        Route::patch('/departments/{departmentId}', [CustomerOrgStructureController::class, 'updateDepartment'])->name('crm.customers.departments.update');
        Route::post('/departments/{departmentId}/positions', [CustomerOrgStructureController::class, 'storePosition'])->name('crm.customers.positions.store');
        Route::patch('/positions/{positionId}', [CustomerOrgStructureController::class, 'updatePosition'])->name('crm.customers.positions.update');
    });

// Tab 6 of the customer file: the six history cards, one drawer at a time, and the linear timeline.
Route::prefix('api/v1/crm/customers/{customerId}/history')
    ->middleware(['api', 'access.auth', 'password.changed'])
    ->whereNumber('customerId')
    ->group(function (): void {
        Route::get('/', [CustomerHistoryController::class, 'show'])->name('crm.customers.history.show');
        // Declared before the category route, so "timeline" is never read as a drawer name.
        Route::get('/timeline', [CustomerHistoryController::class, 'timeline'])->name('crm.customers.history.timeline');
        Route::get('/{category}', [CustomerHistoryController::class, 'category'])
            ->whereIn('category', ['work', 'finance', 'correspondence', 'tickets', 'operations', 'changes'])
            ->name('crm.customers.history.category');
    });

// Lead form: who already holds this mobile number or email address. Declared before every {customerId} route
// of the prefix, so "duplicates" is never read as an ID (the ID segments are whereNumber as well).
Route::get('api/v1/crm/customers/duplicates', [CustomerDuplicateController::class, 'index'])
    ->middleware(['api', 'access.auth', 'password.changed'])
    ->name('crm.customers.duplicates.index');

// Tab "person's channels": the whole contact-point set of a person, replaced in one write.
Route::prefix('api/v1/crm/customers/{customerId}/contact-points')
    ->middleware(['api', 'access.auth', 'password.changed'])
    ->whereNumber('customerId')
    ->group(function (): void {
        Route::get('/', [CustomerContactPointController::class, 'index'])->name('crm.customers.contact-points.index');
        Route::put('/', [CustomerContactPointController::class, 'save'])->name('crm.customers.contact-points.save');
    });

// The industries of a customer, one of them primary, replaced in one write.
Route::prefix('api/v1/crm/customers/{customerId}/industries')
    ->middleware(['api', 'access.auth', 'password.changed'])
    ->whereNumber('customerId')
    ->group(function (): void {
        Route::get('/', [CustomerIndustryController::class, 'index'])->name('crm.customers.industries.index');
        Route::put('/', [CustomerIndustryController::class, 'save'])->name('crm.customers.industries.save');
    });

// Relationships between people and companies: listed from either end, created from the company.
Route::prefix('api/v1/crm/customers/{customerId}/relationships')
    ->middleware(['api', 'access.auth', 'password.changed'])
    ->whereNumber('customerId')
    ->group(function (): void {
        Route::get('/', [CustomerRelationshipController::class, 'index'])->name('crm.customers.relationships.index');
        // A new person, their mobile and the relationship are one write, so a repeated request must not repeat it.
        Route::post('/', [CustomerRelationshipController::class, 'store'])->middleware('idempotent:customer.relationship.create')->name('crm.customers.relationships.store');
    });
Route::post('api/v1/crm/relationships/{relationshipId}/end', [CustomerRelationshipController::class, 'end'])
    ->middleware(['api', 'access.auth', 'password.changed'])
    ->whereNumber('relationshipId')
    ->name('crm.relationships.end');

// Lead decisions: convert it into a customer, or close it. The lifecycle call is generic (any phase), and
// it is the same call the lead-close button makes.
Route::prefix('api/v1/crm/customers/{customerId}')
    ->middleware(['api', 'access.auth', 'password.changed'])
    ->whereNumber('customerId')
    ->group(function (): void {
        Route::post('/convert', [CustomerLeadController::class, 'convert'])->name('crm.customers.convert');
        Route::post('/lifecycle', [CustomerLeadController::class, 'changeLifecycle'])->name('crm.customers.lifecycle.change');
    });
