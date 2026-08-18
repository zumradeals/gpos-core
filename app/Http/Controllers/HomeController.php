<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Commerce\CommercialPermission;
use App\Domain\Commerce\Quantity;
use App\Domain\Identity\CurrentActor;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use Illuminate\View\View;

/**
 * Accueil — jamais un tableau de bord KPI (docs/implementation/LOT-001-APP-SHELL-COMMERCE-
 * SLICE.md §17) : contexte actif, « à faire maintenant » (réel, pas fabriqué), actions rapides,
 * activité récente.
 */
final class HomeController extends Controller
{
    public function index(): View
    {
        /** @var CurrentActor $actor */
        $actor = app(CurrentActor::class);
        $context = $actor->activeContext();

        $todo = [];

        if ($actor->can(CommercialPermission::OPERATE_CASH)) {
            $hasActiveRegister = CashRegister::query()
                ->where('context_id', $context->id)
                ->where('status', CashRegister::STATUS_ACTIVE)
                ->exists();

            $hasOpenSession = CashSession::query()
                ->where('context_id', $context->id)
                ->where('responsible_core_reference', $actor->identity->reference)
                ->where('status', CashSession::STATUS_OPEN)
                ->exists();

            if ($hasActiveRegister && ! $hasOpenSession) {
                $todo[] = ['label' => "Ouvrez votre caisse avant d'encaisser en espèces", 'href' => route('cash.hub')];
            }
        }

        if ($actor->can(CommercialPermission::SELL)) {
            $draft = Sale::query()
                ->where('context_id', $context->id)
                ->where('status', Sale::STATUS_DRAFT)
                ->where('created_by_core_reference', $actor->identity->reference)
                ->whereHas('lines')
                ->latest('created_at')
                ->first();

            if ($draft !== null) {
                $todo[] = ['label' => 'Une vente est en cours, non encaissée', 'href' => route('sell.show')];
            }
        }

        if ($actor->can(CommercialPermission::RECEIVE_PURCHASES)) {
            $toReceive = PurchaseOrder::query()
                ->where('context_id', $context->id)
                ->whereIn('status', [PurchaseOrder::STATUS_ORDERED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED])
                ->orderBy('ordered_at')
                ->get();

            $partial = $toReceive->where('status', PurchaseOrder::STATUS_PARTIALLY_RECEIVED);
            $ordered = $toReceive->where('status', PurchaseOrder::STATUS_ORDERED);

            if ($partial->isNotEmpty()) {
                $todo[] = [
                    'label' => $partial->count() > 1 ? "{$partial->count()} commandes sont partiellement reçues" : 'Une commande est partiellement reçue',
                    'href' => $partial->count() === 1 ? route('purchases.receive', $partial->first()) : route('purchases.hub'),
                ];
            }

            if ($ordered->isNotEmpty()) {
                $todo[] = [
                    'label' => $ordered->count() > 1 ? "{$ordered->count()} commandes sont à réceptionner" : 'Une commande est à réceptionner',
                    'href' => $ordered->count() === 1 ? route('purchases.receive', $ordered->first()) : route('purchases.hub'),
                ];
            }
        }

        if ($actor->can(CommercialPermission::PAY_PURCHASES)) {
            $toPay = PurchaseOrder::query()
                ->where('context_id', $context->id)
                ->where('status', PurchaseOrder::STATUS_RECEIVED)
                ->where('total_xof', '>', 0)
                ->whereDoesntHave('payment')
                ->orderBy('ordered_at')
                ->get();

            if ($toPay->isNotEmpty()) {
                $todo[] = [
                    'label' => $toPay->count() > 1 ? "{$toPay->count()} achats reçus restent à marquer payés" : 'Un achat reçu reste à marquer payé',
                    'href' => $toPay->count() === 1 ? route('purchases.show', $toPay->first()) : route('purchases.hub'),
                ];
            }
        }

        if ($actor->can(CommercialPermission::MANAGE_PURCHASES)) {
            $lowStockCount = Product::query()
                ->where('context_id', $context->id)
                ->where('track_stock', true)
                ->where('active', true)
                ->whereNotNull('reorder_threshold')
                ->with('stockBalance')
                ->get()
                ->filter(fn (Product $product) => $product->stockBalance !== null
                    && Quantity::compare((string) $product->stockBalance->quantity, (string) $product->reorder_threshold) <= 0)
                ->count();

            if ($lowStockCount > 0) {
                $todo[] = [
                    'label' => $lowStockCount > 1 ? "{$lowStockCount} produits sont sous leur seuil de réassort" : '1 produit est sous son seuil de réassort',
                    'href' => route('stock.index'),
                ];
            }
        }

        if ($actor->can(CommercialPermission::VIEW_STOCK)) {
            $outOfStockCount = Product::query()
                ->where('context_id', $context->id)
                ->where('track_stock', true)
                ->where('active', true)
                ->whereHas('stockBalance', fn ($q) => $q->where('quantity', '<=', 0))
                ->count();

            if ($outOfStockCount > 0) {
                $todo[] = [
                    'label' => $outOfStockCount > 1 ? "{$outOfStockCount} produits sont en rupture de stock" : '1 produit est en rupture de stock',
                    'href' => route('stock.index'),
                ];
            }
        }

        $canViewDocuments = $actor->can(CommercialPermission::VIEW_DOCUMENTS);

        $recentSales = $canViewDocuments
            ? Sale::query()
                ->where('context_id', $context->id)
                ->where('status', Sale::STATUS_CONFIRMED)
                ->latest('confirmed_at')
                ->limit(5)
                ->get()
            : collect();

        return view('home.index', [
            'context' => $context,
            'todo' => array_slice($todo, 0, 3),
            'recentSales' => $recentSales,
            'canViewDocuments' => $canViewDocuments,
            'canSell' => $actor->can(CommercialPermission::SELL),
            'canViewStock' => $actor->can(CommercialPermission::VIEW_STOCK),
            'canManagePurchases' => $actor->can(CommercialPermission::MANAGE_PURCHASES),
        ]);
    }
}
