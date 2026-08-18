<?php

declare(strict_types=1);

use App\Domain\Commerce\CommercialPermission;
use App\Http\Controllers\ContextSwitchController;
use App\Http\Controllers\DevActorController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SellController;
use App\Http\Controllers\StockController;
use Illuminate\Support\Facades\Route;

// docs/implementation/LOT-001-APP-SHELL-COMMERCE-SLICE.md §6.3 : uniquement hors production.
if (! app()->environment('production')) {
    Route::get('/dev/identite', [DevActorController::class, 'show'])->name('dev.actor.show');
    Route::post('/dev/identite', [DevActorController::class, 'become'])->name('dev.actor.become');
}

Route::middleware(['actor.required'])->group(function (): void {
    Route::post('/contextes/selection', [ContextSwitchController::class, 'select'])->name('contexts.select');

    Route::middleware(['context.active', 'context.required'])->group(function (): void {
        Route::get('/', [HomeController::class, 'index'])->name('home');

        Route::middleware(['commercial.permission:'.CommercialPermission::SELL])->group(function (): void {
            Route::get('/vendre', [SellController::class, 'show'])->name('sell.show');
        });

        Route::get('/produits', [ProductController::class, 'index'])->name('products.index');
        Route::post('/produits', [ProductController::class, 'store'])->name('products.store');

        Route::middleware(['commercial.permission:'.CommercialPermission::VIEW_STOCK])->group(function (): void {
            Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
            Route::post('/stock/ajuster', [StockController::class, 'adjust'])->name('stock.adjust');
        });

        Route::middleware(['commercial.permission:'.CommercialPermission::VIEW_DOCUMENTS])->group(function (): void {
            Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
            Route::get('/documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
        });
    });
});
