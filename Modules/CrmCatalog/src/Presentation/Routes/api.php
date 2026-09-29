<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\CrmCatalog\Presentation\Http\Controllers\CatalogItemController;
use Modules\CrmCatalog\Presentation\Http\Controllers\CatalogReferenceController;
use Modules\CrmCatalog\Presentation\Http\Controllers\IndustryController;

Route::prefix('api/v1/crm')->middleware(['api', 'access.auth', 'password.changed'])->group(function (): void {
    Route::get('/industries', [IndustryController::class, 'index'])->name('crm.industries.index');

    Route::get('/catalog-items', [CatalogItemController::class, 'index'])->name('crm.catalog-items.index');
    Route::post('/catalog-items', [CatalogItemController::class, 'store'])->name('crm.catalog-items.store');
    Route::get('/catalog-items/{catalogItemId}', [CatalogItemController::class, 'show'])->whereNumber('catalogItemId')->name('crm.catalog-items.show');
    Route::patch('/catalog-items/{catalogItemId}', [CatalogItemController::class, 'update'])->whereNumber('catalogItemId')->name('crm.catalog-items.update');

    Route::get('/catalog/categories', [CatalogReferenceController::class, 'categories'])->name('crm.catalog.categories.index');
    Route::get('/catalog/personas', [CatalogReferenceController::class, 'personas'])->name('crm.catalog.personas.index');
    Route::get('/catalog/sales-models', [CatalogReferenceController::class, 'salesModels'])->name('crm.catalog.sales-models.index');
});
