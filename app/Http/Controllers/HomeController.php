<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Commerce\CommercialPermission;
use App\Domain\Identity\CurrentActor;
use App\Models\Product;
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
        ]);
    }
}
