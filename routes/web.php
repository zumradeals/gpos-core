<?php

declare(strict_types=1);

use App\Domain\Commerce\CommercialPermission;
use App\Http\Controllers\CashClosureController;
use App\Http\Controllers\CashHubController;
use App\Http\Controllers\CashMovementController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\CashSessionController;
use App\Http\Controllers\ContextSwitchController;
use App\Http\Controllers\DevActorController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchasePaymentController;
use App\Http\Controllers\PurchaseReceiptController;
use App\Http\Controllers\PurchasingHubController;
use App\Http\Controllers\SellController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SupplierController;
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

        // docs/implementation/LOT-002-PURCHASING-SUPPLY.md §20-24 : « Acheter » n'apparaît que
        // si l'acteur possède une permission achat pertinente ; VIEW_PURCHASES suffit pour
        // consulter le hub et les commandes, les actions elles-mêmes restent gardées séparément.
        Route::middleware(['commercial.permission:'.CommercialPermission::VIEW_PURCHASES])->group(function (): void {
            Route::get('/acheter', [PurchasingHubController::class, 'index'])->name('purchases.hub');
            Route::get('/acheter/commandes/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchases.show');

            Route::get('/acheter/fournisseurs', [SupplierController::class, 'index'])->name('suppliers.index');
        });

        Route::middleware(['commercial.permission:'.CommercialPermission::MANAGE_PURCHASES])->group(function (): void {
            Route::post('/acheter/fournisseurs', [SupplierController::class, 'store'])->name('suppliers.store');

            Route::get('/acheter/nouveau', [PurchaseOrderController::class, 'create'])->name('purchases.create');
            Route::post('/acheter/commandes', [PurchaseOrderController::class, 'store'])->name('purchases.store');
            Route::post('/acheter/commandes/{purchaseOrder}/annuler', [PurchaseOrderController::class, 'cancel'])->name('purchases.cancel');
        });

        Route::middleware(['commercial.permission:'.CommercialPermission::RECEIVE_PURCHASES])->group(function (): void {
            Route::get('/acheter/commandes/{purchaseOrder}/receptionner', [PurchaseReceiptController::class, 'create'])->name('purchases.receive');
        });

        Route::middleware(['commercial.permission:'.CommercialPermission::PAY_PURCHASES])->group(function (): void {
            Route::post('/acheter/commandes/{purchaseOrder}/payer', [PurchasePaymentController::class, 'store'])->name('purchases.pay');
        });

        // docs/implementation/LOT-003-CASH-REGISTER-CLOSING.md §20-24 : « Caisse » n'apparaît que
        // si l'acteur possède au moins une permission caisse pertinente ; les actions elles-mêmes
        // restent gardées séparément par leur propre permission.
        $cashPermissions = implode(',', [
            CommercialPermission::VIEW_CASH, CommercialPermission::OPERATE_CASH,
            CommercialPermission::CLOSE_CASH, CommercialPermission::MANAGE_CASH,
        ]);

        Route::middleware(['commercial.permission:'.$cashPermissions])->group(function (): void {
            Route::get('/caisse', [CashHubController::class, 'index'])->name('cash.hub');
        });

        Route::middleware(['commercial.permission:'.CommercialPermission::MANAGE_CASH])->group(function (): void {
            Route::post('/caisse/registres', [CashRegisterController::class, 'store'])->name('cash.registers.store');
        });

        Route::middleware(['commercial.permission:'.CommercialPermission::OPERATE_CASH])->group(function (): void {
            Route::post('/caisse/registres/{cashRegister}/ouvrir', [CashSessionController::class, 'store'])->name('cash.sessions.open');
            Route::post('/caisse/mouvements', [CashMovementController::class, 'store'])->name('cash.movements.store');
        });

        Route::middleware(['commercial.permission:'.CommercialPermission::CLOSE_CASH])->group(function (): void {
            Route::get('/caisse/cloturer', [CashClosureController::class, 'create'])->name('cash.closure.create');
            Route::post('/caisse/cloturer', [CashClosureController::class, 'store'])->name('cash.closure.store');
        });
    });
});
