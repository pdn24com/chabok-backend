<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\DocumentStore\Presentation\Http\Controllers\DocumentCategoryController;
use Modules\DocumentStore\Presentation\Http\Controllers\DocumentController;

// The shared document archive. Everything here sits behind the crm.document permissions; the documents
// tab of a record is this same archive narrowed by the resource filters.
Route::prefix('api/v1/crm')->middleware(['api', 'access.auth', 'password.changed'])->group(function (): void {
    Route::get('/document-categories', [DocumentCategoryController::class, 'index'])->name('crm.document-categories.index');
    Route::get('/documents', [DocumentController::class, 'index'])->name('crm.documents.index');
    Route::post('/documents', [DocumentController::class, 'store'])->name('crm.documents.store');
});

Route::prefix('api/v1/crm/documents/{documentId}')
    ->middleware(['api', 'access.auth', 'password.changed'])
    ->whereNumber('documentId')
    ->group(function (): void {
        Route::patch('/', [DocumentController::class, 'update'])->name('crm.documents.update');
        // Retiring a document is its own action, so a routine edit cannot do it by accident.
        Route::post('/archive', [DocumentController::class, 'archive'])->name('crm.documents.archive');
        Route::post('/links', [DocumentController::class, 'link'])->name('crm.documents.links.store');
    });
